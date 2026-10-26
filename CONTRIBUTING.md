# Contributing

Use Conventional Commits and open a pull request against `main`. Keep changes
focused, add regression tests and run the commands in README before submitting.
Contributions are provided under this repository's licence.

Never commit real credentials, device/product keys, account emails, tokens,
`authKey` values, Tuya access IDs/keys or UIDs. Fixtures must contain generated
values only. Protocol work must use a clean-room implementation and document
the provenance of bundled public app constants. Never copy reference implementation
code or publish account secrets.

Run `npm ci && npm run docs:check` and `composer docs` for documentation changes.
The link checker skips only the authenticated private-report creation form;
verify that destination manually while signed in.
Examples must be synthetic; never publish operator evidence or captures.
Development-only npm overrides select patched `basic-ftp` and `katex` releases
until the Markdown tools update their transitive ranges. Keep `npm audit` clean.

## Release procedure

1. Wait for every CI matrix job and secret scan to pass.
2. Move the relevant `Unreleased` entries into `## [X.Y.Z] - YYYY-MM-DD`.
3. Commit through a pull request. Do not set a Composer `version` field.
4. On the reviewed merge commit, create and verify a signed annotated tag:
   `git tag -s vX.Y.Z -m "Release vX.Y.Z"` then `git tag -v vX.Y.Z`.
   The signing key must also be registered with GitHub.
5. Push the tag. The release workflow checks its signature and changelog,
   repeats validation, then creates a GitHub Release in the protected `release`
   environment. Packagist consumes Git tags independently through its webhook:
   only push a tag after CI and review, not to test a workflow.

The maintainer must register the repository with Packagist and enable automatic
updates. Do not publish an empty placeholder release to reserve a name.
After publication, check the indexed version and install that exact version in a
fresh PHP 8.2 consumer with Guzzle. Run the README quick start with a mock HTTP
transport; never validate a release through automated live device writes.
