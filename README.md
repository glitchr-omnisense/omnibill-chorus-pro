# omnibill/chorus-pro

**Chorus Pro**, the French public sector's invoicing portal, for
[omniscient/omnibill](https://github.com/glitchr-studio/omnibill), through PISTE: a supplier's
invoices to public entities (B2G), deposited as flows (`deposer/flux`) and followed
(`rechercher/fournisseur`).

```php
use Omnibill\ChorusPro\ChorusProGatewayFactory;

$public = (new ChorusProGatewayFactory($httpClient))->create([
    'client_id' => $pisteClientId, 'client_secret' => $pisteSecret,     // a PISTE application
    'login' => 'TECH_1_atelier@cpro.fr', 'password' => $password,        // a Chorus Pro technical account
    'sandbox' => true,
]);

$flow = $public->submit($invoice);               // numeroFluxDepot: the flow's reference
$public->fetchStatus($flow)->status;             // MISE_A_DISPOSITION read as MADE_AVAILABLE...
```

Written from Chorus Pro's "Factures" OpenAPI (as published on api.gouv.fr) and its annex on the API
services. **Not verified in real: no PISTE account.**

[Documentation](docs/index.md): the options, the routes, the statuses' reading, what was verified.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by the AIFE (Chorus Pro) or the PISTE platform. It reaches their services on behalf of
whoever runs it, and it does not redistribute their data beyond the short excerpts recorded as test
fixtures. Its MIT licence covers this package's code only and grants no right to the service or to
its data.

Whoever installs and runs this package is responsible for complying with the terms of Chorus Pro
([Chorus Pro CGU](https://portail.chorus-pro.gouv.fr/aife_csm?id=aife_cgu)) and of PISTE
([piste.gouv.fr/cgu](https://piste.gouv.fr/cgu)), for obtaining whatever they require (an account, a
key, a licence or subscription, an attribution or a citation, a rate limit to respect), and for any
use made of what it returns. The authors publish it free of charge, make no commercial use of it,
and accept no liability for how it is used (see [LICENSE](LICENSE)).

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
