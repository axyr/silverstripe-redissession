# Change log
All notable changes to this project will be documented in this file.

## [Unreleased]
### Added
- Some code documentation

### Fixed
- PHP7 bug: No boolean return values of open() and close()
- PHP7 bug: Data serialization in write()
- Check for existing Predis Client instance before invoking it (caused NOTICE)