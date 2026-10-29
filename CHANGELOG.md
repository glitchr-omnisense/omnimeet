# Changelog

## [Unreleased]

### Fixed

- The models' constructors throw an `InvalidModelException` (an `OmnimeetException`, and an
  `\InvalidArgumentException` as before) for a meeting without a key or ending before it starts, a
  participant without an identifier, an access without its script or its address: a
  `catch (OmnimeetException)` missed the plain `\InvalidArgumentException`.
