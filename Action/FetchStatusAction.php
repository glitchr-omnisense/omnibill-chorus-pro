<?php

namespace Omnibill\ChorusPro\Action;

use Omnibill\Action\ActionInterface;
use Omnibill\Action\ApiAwareInterface;
use Omnibill\Action\ApiAwareTrait;
use Omnibill\ChorusPro\Api;
use Omnibill\ChorusPro\Data;
use Omnibill\ChorusPro\Statuses;
use Omnibill\Exception\InvalidConfigException;
use Omnibill\Exception\ProviderException;
use Omnibill\Exception\UnreachableException;
use Omnibill\Model\FlowState;
use Omnibill\Model\StatusChange;
use Omnibill\Request\FetchStatus;
use Omnibill\Request\Request;

/**
 * POST /v1/rechercher/fournisseur on the invoice's number: the invoice as
 * Chorus Pro integrated it - from the flow it was deposited in, when
 * several bear that number - and its current status (statut). Not found
 * yet: the flow is still being processed, PENDING.
 *
 * Since omnibill 1.2, then POST /v1/consulter/historique on its
 * identifiantFactureCPP (idFacture): every status it went through
 * (historiquesDesStatuts.histoStatut: histoStatutCode,
 * histoStatutDatePassage, histoStatutCommentaire), oldest first, as the
 * flow's history - the ones the reform has a status for. Chorus Pro's own
 * error on that call keeps the current status alone.
 */
final class FetchStatusAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<Api> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = Api::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof FetchStatus;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof FetchStatus);
        $flow = $request->flow;
        $number = $flow->invoiceNumber ?? throw new InvalidConfigException('Chorus Pro finds an invoice by its number: the Flow has none (give the Invoice a number when submitting it).');
        $found = $this->api->call('/v1/rechercher/fournisseur', ['numeroFacture' => $number])['listeFactures'] ?? [];
        $mine = array_values(array_filter((array) $found, static fn ($f) => \is_array($f) && ($f['numeroFluxDepot'] ?? null) === $flow->reference)) ?: array_values(array_filter((array) $found, 'is_array'));
        if (!$mine) {
            $request->setResult($flow);

            return;
        }
        $invoice = $mine[0];
        $code = isset($invoice['statut']) ? Data::cast($invoice['statut']) : null;
        $status = Statuses::of($code);
        $at = self::date($invoice['dateHeureEtatCourant'] ?? null);
        $history = $this->history($invoice, $number) ?? $flow->history;
        if (null !== $status && (!$history || $history[array_key_last($history)]->code !== $code)) {
            $history[] = new StatusChange($status, $number, $at, Data::of($invoice)->textOrNull('commentaireEtatCourant'), code: $code);
        }
        $request->setResult($flow->with(state: FlowState::ACCEPTED, status: $status ?? $flow->status, history: $history, updatedAt: $at, data: ['facture' => $invoice] + $flow->data));
    }

    /**
     * The statuses the invoice went through, oldest first; null when Chorus
     * Pro gives none (no identifiantFactureCPP, its own error).
     *
     * @param array<mixed> $invoice
     *
     * @return list<StatusChange>|null
     */
    private function history(array $invoice, string $number): ?array
    {
        $id = $invoice['identifiantFactureCPP'] ?? null;
        if (!\is_int($id)) {
            return null;
        }
        try {
            $answer = $this->api->call('/v1/consulter/historique', ['idFacture' => $id, 'paramRechercheHistoStatuts' => ['nbResultatsParPageListeHistoStatut' => 100]]);
        } catch (UnreachableException $e) {
            throw $e;
        } catch (ProviderException) {
            return null;
        }
        $rows = Data::of($answer)->get('historiquesDesStatuts.histoStatut');
        $history = [];
        foreach (\is_array($rows) ? $rows : [] as $row) {
            $read = Data::of(\is_array($row) ? $row : []);
            $code = $read->textOrNull('histoStatutCode');
            if (null !== ($status = Statuses::of($code))) {
                $history[] = new StatusChange($status, $number, self::date($read->get('histoStatutDatePassage')), $read->textOrNull('histoStatutCommentaire') ?: null, code: $code);
            }
        }
        usort($history, static fn (StatusChange $a, StatusChange $b) => ($a->at?->getTimestamp() ?? 0) <=> ($b->at?->getTimestamp() ?? 0));

        return $history ?: null;
    }

    /** A date as Chorus Pro writes it; null when PHP cannot read it. */
    private static function date(mixed $value): ?\DateTimeImmutable
    {
        try {
            return \is_string($value) && '' !== trim($value) ? new \DateTimeImmutable($value) : null;
        } catch (\Exception) {
            return null;
        }
    }
}
