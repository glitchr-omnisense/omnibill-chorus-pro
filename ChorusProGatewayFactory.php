<?php

namespace Omnibill\ChorusPro;

use Omnibill\ChorusPro\Action\FetchStatusAction;
use Omnibill\ChorusPro\Action\SubmitAction;
use Omnibill\Config;
use Omnibill\GatewayFactory;
use Omnibill\Http\ClientCredentials;
use Omnibill\Model\Capabilities;
use Omnibill\Model\Syntax;
use Omnisense\Contracts\Policy\UsagePolicy;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Chorus Pro, the public sector's invoicing portal, through PISTE: a
 * supplier's invoices to a public entity (B2G), deposited as flows and
 * followed.
 *
 *   options:
 *     client_id: '%env(PISTE_CLIENT_ID)%'          # required: the PISTE application's
 *     client_secret: '%env(PISTE_CLIENT_SECRET)%'  # required
 *     login: '%env(CPRO_LOGIN)%'                   # required: the Chorus Pro technical account (TECH_1_...@cpro.fr)
 *     password: '%env(CPRO_PASSWORD)%'             # required
 *     sandbox: false                               # true: PISTE's sandbox and Chorus Pro's qualification
 *     user_id: ~                                   # idUtilisateurCourant, when the account asks for it
 *     syntaxes: { Factur-X: IN_DP_E2_CII_FACTURX, UBL: IN_DP_E1_UBL_INVOICE, CII: IN_DP_E1_CII_16B }
 *     api_url: ~                                   # https://api.piste.gouv.fr/cpro/factures by default
 *     oauth_url: ~                                 # https://oauth.piste.gouv.fr/api/oauth/token by default
 *
 * It submits and follows. Receiving (the public entities' side), the
 * directory, e-reporting and callbacks are not Chorus Pro's to give a
 * supplier through this API.
 */
final class ChorusProGatewayFactory extends GatewayFactory
{
    private static ?UsagePolicy $policy = null;

    public function __construct(private readonly ?HttpClientInterface $http = null)
    {
    }

    /** Its usage policy, as policy.json declares it (read once). */
    public static function usagePolicy(): UsagePolicy
    {
        return self::$policy ??= UsagePolicy::fromFile(__DIR__.'/policy.json');
    }

    protected function populateConfig(Config $config): void
    {
        $config->defaults([
            'omnibill.factory_name' => 'chorus-pro',
            'omnibill.factory_title' => 'Chorus Pro',
            'omnibill.required_options' => ['client_id', 'client_secret', 'login', 'password'],
            'sandbox' => false,
            'user_id' => null,
            'syntaxes' => [],
            'api_url' => null,
            'oauth_url' => null,
            // 10 MB a file uncompressed, 30 MB compressed (annex on the API services, deposerFluxFacture).
            'omnibill.capabilities' => new Capabilities([Syntax::FACTURX, Syntax::UBL, Syntax::CII], approved: false, publicSector: true, maxSize: 10 * 1024 * 1024, policy: self::usagePolicy()),
            'omnibill.api' => function (Config $c): Api {
                $http = $this->http ?? HttpClient::create();
                $sandbox = $c->bool('sandbox');

                return new Api(
                    $http,
                    new ClientCredentials($http, Api::PROVIDER, Data::cast($c->get('oauth_url') ?: ($sandbox ? Api::SANDBOX_OAUTH : Api::OAUTH)), Data::cast($c['client_id']), Data::cast($c['client_secret']), 'openid', null, $c->backoff()),
                    base64_encode(Data::cast($c['login']).':'.Data::cast($c['password'])),
                    rtrim(Data::cast($c->get('api_url') ?: ($sandbox ? Api::SANDBOX_API : Api::API)), '/'),
                    null !== $c->get('user_id') && '' !== $c->get('user_id') ? Data::integer($c['user_id']) : null,
                    $c->backoff(),
                );
            },
            'omnibill.action.submit' => static fn (Config $c) => new SubmitAction(array_replace(SubmitAction::defaults(), Data::textsByKey((array) $c['syntaxes']))),
            'omnibill.action.fetch_status' => new FetchStatusAction(),
        ]);
    }
}
