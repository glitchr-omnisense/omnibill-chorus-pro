# omnibill/chorus-pro

Chorus Pro, the public sector's invoicing portal, for
[omnisense/omnibill](https://github.com/glitchr-omnisense/omnibill), through PISTE: a supplier's
invoices to a public entity (B2G), deposited as flows and followed. The gateway calls Chorus Pro's
"Factures" API:

| Operation | Route |
|---|---|
| token | `POST https://oauth.piste.gouv.fr/api/oauth/token` (client credentials, scope `openid`) |
| submit | `POST https://api.piste.gouv.fr/cpro/factures/v1/deposer/flux` |
| fetchStatus | `POST https://api.piste.gouv.fr/cpro/factures/v1/rechercher/fournisseur` |

and, with `sandbox: true`, `https://sandbox-oauth.piste.gouv.fr/api/oauth/token` and
`https://sandbox-api.piste.gouv.fr/cpro/factures` (Chorus Pro's qualification). Each call carries
`Authorization: Bearer <token>`, `cpro-account: base64(login:password)` and `Accept:
application/json`, and a JSON body (`idUtilisateurCourant` added when `user_id` is set).

## Installation

```sh
composer require omnibill/chorus-pro
```

PHP 8.2 or later, `omnisense/omnibill` and `symfony/http-client`. A PISTE application subscribed to
the Chorus Pro APIs, and a Chorus Pro technical account attached to the supplier's structure (see
Chorus Pro's guides "Se raccorder à Chorus Pro" and "création d'un compte technique").

## The sources

Read on 2026-10-08:

- Chorus Pro's "Factures" OpenAPI, as data.gouv.fr points to it (`api_gouv_swaggers`,
  `api-chorus-pro.json`): the routes `/v1/deposer/flux`, `/v1/rechercher/fournisseur`,
  `/v1/consulter/historique`, the `cpro-account` header, the bodies, `statutCourantCode`;
- the annex on the API services of Chorus Pro's external specifications (V5.00,
  communaute.chorus-pro.gouv.fr): `deposerFluxFacture` (its syntaxes, 10 MB a file, 30 compressed,
  its errors), `consulterCR`, `consulterCRDetaille`;
- the DGFiP's external specifications v3.2, file "Chorus Pro" v1.1: PISTE, the API channel;
- PISTE's addresses: `https://oauth.piste.gouv.fr/api/oauth/token`, `https://api.piste.gouv.fr`,
  and their sandboxes.

## Credentials

- A **PISTE application** (piste.gouv.fr) subscribed to the Chorus Pro APIs: its client id and
  secret (OAuth 2.0 client credentials). The sandbox has applications of its own.
- A **Chorus Pro technical account** (`TECH_1_...@cpro.fr`) attached to the supplier's structure,
  and its password; `user_id` (`idUtilisateurCourant`) when the account asks for it.

## Options

| Option | Default | |
|---|---|---|
| `client_id`, `client_secret` | required | the PISTE application's (OAuth 2.0 client credentials, scope `openid`) |
| `login`, `password` | required | the technical account: sent as `cpro-account: base64(login:password)` |
| `sandbox` | `false` | PISTE's sandbox (`sandbox-oauth.piste.gouv.fr`, `sandbox-api.piste.gouv.fr`), Chorus Pro's qualification |
| `user_id` | | `idUtilisateurCourant`, when the account asks for it |
| `syntaxes` | `{Factur-X: IN_DP_E2_CII_FACTURX, UBL: IN_DP_E1_UBL_INVOICE, CII: IN_DP_E1_CII_16B}` | the `syntaxeFlux` of each syntax; `IN_DP_E2_CII_MIN_16B`, `IN_DP_E2_UBL_INVOICE_MIN` for a minimal flow with a PDF |
| `api_url`, `oauth_url` | PISTE's | |

## Capabilities

| Request | Supported |
|---|---|
| `Submit` | yes: Factur-X, UBL, CII (10 MB, said not enforced) |
| `FetchStatus` | yes, by the invoice's number |
| `Receive`, `SetStatus`, `Lookup`, `Report`, `Notify` | no: `NotSupportedException` |

`capabilities()`: Factur-X, UBL, CII; not an approved platform; public sector; 10 MB.

## Operations

| | Route | |
|---|---|---|
| `submit()` | `POST /cpro/factures/v1/deposer/flux`: `fichierFlux` (base64), `nomFichier`, `syntaxeFlux`, `avecSignature: false` | `numeroFluxDepot` names the flow, `PENDING` |
| `fetchStatus()` | `POST /cpro/factures/v1/rechercher/fournisseur`: `numeroFacture` | the invoice Chorus Pro integrated - the one from this flow when several bear the number -, its `statut`: `ACCEPTED`, and the status below; not found yet: still `PENDING` |

Every answer carries `codeRetour` (0: done) and `libelle`; another code is a `ProviderException`
with it (`20001`, `GDP_MSG_11.022`: the file is too large). HTTP 401 or 403 is an
`InvalidKeyException`: PISTE or the technical account refused.

Chorus Pro's statuses read as the reform's - this package's reading, the code always kept in
`StatusChange::$code`:

| Chorus Pro | Reform |
|---|---|
| `DEPOSEE` | `DEPOSITED` |
| `EN_COURS_ACHEMINEMENT` | `ISSUED` |
| `MISE_A_DISPOSITION` | `MADE_AVAILABLE` |
| `PRISE_EN_COMPTE_DESTINATAIRE` | `TAKEN_OVER` |
| `SERVICE_FAIT`, `MANDATEE` | `APPROVED` |
| `SUSPENDUE`, `A_COMPLETER` | `SUSPENDED` |
| `COMPLETEE` | `COMPLETED` |
| `REJETEE` | `REFUSED` |
| `MISE_EN_PAIEMENT` | `PAYMENT_SENT` |

The validation circuits (`A_VALIDER_1`, `VALIDEE_2`...) and Chorus Pro's own states have no
counterpart: the flow keeps its last status, the code is in `data`.

Not done: receiving (the public entities' side), the directory of structures (another API),
e-reporting, callbacks; the deposit report (`consulterCR`) - its route on PISTE was not found in
the documentation read.

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnibill\ChorusPro\ChorusProGatewayFactory;
use Omnibill\Model\Invoice;
use Omnibill\Model\Party;
use Omnibill\Model\Syntax;
use Symfony\Component\HttpClient\HttpClient;

$public = (new ChorusProGatewayFactory(HttpClient::create()))->create([
    'client_id' => getenv('PISTE_CLIENT_ID'),
    'client_secret' => getenv('PISTE_CLIENT_SECRET'),
    'login' => getenv('CPRO_LOGIN'),
    'password' => getenv('CPRO_PASSWORD'),
    'sandbox' => true,
]);

$invoice = new Invoice(file_get_contents('F-2026-042.pdf'), 'F-2026-042.pdf', Syntax::FACTURX, 'F-2026-042', buyer: new Party('Mairie', siret: '21670482500019'));
$flow = $public->submit($invoice);                 // PENDING, numeroFluxDepot as reference
$flow = $public->fetchStatus($flow);               // ACCEPTED, the invoice's statut read as the reform's
```

Run on 2026-10-26 on a `MockHttpClient` serving this package's fixtures (the harness's
`recorded.php`), PHP 8.4, no network; the requests (`>`, the body cut at 120 characters) and what
each call answered, then `supports(Receive::class)`, `receive()` and a CDAR submitted:

```
  > POST https://sandbox-oauth.piste.gouv.fr/api/oauth/token
  > POST https://sandbox-api.piste.gouv.fr/cpro/factures/v1/deposer/flux  {"fichierFlux":"JVBERi0xLjcKLi4u","nomFichier":"F-2026-042.pdf","syntaxeFlux":"IN_DP_E2_CII_FACTURX","avecSignature":fal
CPP0021100000000000000023 pending 2026-10-08 00:00
  > POST https://sandbox-api.piste.gouv.fr/cpro/factures/v1/rechercher/fournisseur  {"numeroFacture":"F-2026-042"}
accepted made_available, history: made_available (MISE_A_DISPOSITION)
bool(false)
The "chorus-pro" gateway does not support Receive.
Chorus Pro takes no CDAR file through this gateway.
```

In Symfony:

```yaml
omnibill:
    gateways:
        public:
            factory: chorus-pro
            options:
                client_id: '%env(PISTE_CLIENT_ID)%'
                client_secret: '%env(PISTE_CLIENT_SECRET)%'
                login: '%env(CPRO_LOGIN)%'
                password: '%env(CPRO_PASSWORD)%'
                sandbox: false                  # true: PISTE's sandbox
```

## Errors

| Answer | Exception |
|---|---|
| the token endpoint's 400, 401, 403, or no `access_token` | `InvalidKeyException` |
| 401 | the token asked again once; then `InvalidKeyException` |
| 403 | `InvalidKeyException` (`PISTE or the technical account refused the call (HTTP 403): <libelle>`) |
| `codeRetour` other than 0, or another 4xx | `ProviderException` (`/v1/deposer/flux answered 20001: GDP_MSG_11.022`, `providerCode` the code) |
| a body that is not JSON | `ProviderException` |
| 5xx, 429, no answer, a timeout (10 s) | `UnreachableException` |
| a syntax without a `syntaxeFlux`; a flow without an invoice number for `fetchStatus()` | `InvalidConfigException`, before any call |

> [!WARNING]
> Known defect: a `dateDepot` or `dateHeureEtatCourant` PHP cannot read as a date throws PHP's own `\Exception` (`new \DateTimeImmutable()` in `SubmitAction` and `FetchStatusAction`), where an `OmnibillException` or a null date is expected.

## Verified, and not

| | |
|---|---|
| Against PISTE and Chorus Pro | **not verified in real: no PISTE application, no technical account.** The answers in `Tests/Fixtures` are written from the OpenAPI and the annex |
| The token, the `cpro-account` header, the bodies, the syntaxes | by the tests |
| The OpenAPI's host | it names `api.aife.economie.gouv.fr`; PISTE's current address, `api.piste.gouv.fr`, is the default here - `api_url` changes it |

The package's tests (`Tests/ChorusProGatewayTest.php`, 5 tests) pass on 2026-10-26 (PHP 8.4).

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by the AIFE (Chorus Pro) or the PISTE platform. Whoever runs it complies with the
[Chorus Pro CGU](https://portail.chorus-pro.gouv.fr/aife_csm?id=aife_cgu) and the
[PISTE terms](https://piste.gouv.fr/cgu); see the README's Responsibility section.
