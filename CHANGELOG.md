# Changelog

## [Unreleased]

### Fixed

- A `dateDepot` or `dateHeureEtatCourant` PHP cannot read as a date is no date (`submittedAt` the
  time of the deposit, the status change's `at` null), where PHP's own `\Exception` escaped the
  gateway after the deposit.
