# Contributing

Use Conventional Commits and open a pull request against `main`. Keep changes
focused, add regression tests and run the commands in README before submitting.
Contributions are provided under this repository's licence.

Never commit real credentials, device/product keys, account emails, tokens,
`authKey` values, Tuya access IDs/keys or UIDs. Fixtures must contain generated
values only. Protocol work must follow EPIC-15's clean-room boundary and document
the provenance of bundled public app constants. Never copy reference implementation
code or publish account secrets.

## Release procedure

1. Wait for every CI matrix job and secret scan to pass.
2. Move the relevant `Unreleased` entries into `## [X.Y.Z] - YYYY-MM-DD`.
3. Commit through a pull request. Do not set a Composer `version` field.
4. Create and verify a signed annotated tag: `git tag -s vX.Y.Z`.
5. Push the tag. The release workflow checks its signature and changelog,
   repeats validation, then creates a GitHub Release in the protected `release`
   environment. Packagist consumes Git tags independently through its webhook:
   only push a tag after CI and review, not to test a workflow.

The maintainer must register the repository with Packagist and enable automatic
updates. Do not publish an empty placeholder release to reserve a name.
