# Changelog

## [Unreleased]

## [1.0.0] - 2026-11-13

### Added

- `fetchStatus()` gives the invoice's whole lifecycle: after `rechercher/fournisseur`,
  `consulter/historique` on its `identifiantFactureCPP` (Chorus Pro's "Factures" OpenAPI,
  api_gouv_swaggers, read 2026-11-11) - every status, oldest first, as the flow's history.
- A 5xx or a timeout to `deposer/flux` is omnibill 1.2's `OutcomeUnknownException`; the searches
  are reads.

### Changed

- Requires `omnisense/contracts: ^1.0`, `omnisense/omnibill: ^1.0`: stable versions, so `composer require omnibill/chorus-pro` installs in a project at the default minimum-stability. `extra.branch-alias` names 1.x (`1.x-dev`); the CI (`.github/workflows/ci.yml`, glitchr-studio/ci's shared workflow) and the release on a tag (`release.yml`).
- PHPStan at level max with an empty baseline (`phpstan.neon`, `phpstan-baseline.neon`, as the
  family's harness and CI run it): types only, Chorus Pro's answers and the options read through
  an internal `Data` that gives the values the casts gave. No behaviour change.

### Fixed

- A `dateDepot` or `dateHeureEtatCourant` PHP cannot read as a date is no date (`submittedAt` the
  time of the deposit, the status change's `at` null), where PHP's own `\Exception` escaped the
  gateway after the deposit.
