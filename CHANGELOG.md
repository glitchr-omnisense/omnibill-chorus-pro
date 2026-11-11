# Changelog

## [Unreleased]

### Changed

- PHPStan at level max with an empty baseline (`phpstan.neon`, `phpstan-baseline.neon`, as the
  family's harness and CI run it): types only, Chorus Pro's answers and the options read through
  an internal `Data` that gives the values the casts gave. No behaviour change.

### Fixed

- A `dateDepot` or `dateHeureEtatCourant` PHP cannot read as a date is no date (`submittedAt` the
  time of the deposit, the status change's `at` null), where PHP's own `\Exception` escaped the
  gateway after the deposit.
