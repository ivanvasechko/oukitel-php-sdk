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

1. Move relevant `Unreleased` entries into `## [X.Y.Z] - YYYY-MM-DD` with
   nonempty release notes. Commit through a pull request; do not set a Composer
   `version` field.
2. Merge after all PR checks pass. Wait for the **main-push CI run on that exact
   merge commit** to succeed too.
3. Open GitHub **Releases → Draft a new release**. Choose `vX.Y.Z` and target
   the reviewed commit, not a newer unchecked branch tip. Copy the changelog
   notes, check the prerelease setting, and publish. GPG signing is optional.
4. The read-only **Release validation** workflow checks the version, tagged
   changelog, ancestry on `main`, and latest exact-commit main-push CI result.
   It neither creates releases nor needs a protected deployment approval.
5. Check the validation result and Packagist indexing. Published `v*` tags are
   immutable: never move or delete them; publish a new patch version for fixes.

Packagist consumes Git tags independently through its webhook, possibly before
release validation finishes. Post-publication checks do **not** prevent indexing;
checking exact-commit CI before creating a tag is the maintainer's responsibility.
For an existing tag, run **Actions → Release validation → Run workflow** on
`main`, supplying the tag, before publishing its release. This also validates
older tags whose commit predates the UI-release workflow.

### Where to check publication

- **Releases:** published version and release notes.
- **Actions → Release validation:** validation outcome, not publication itself.
- **Packagist:** indexed version available to Composer consumers.

The repository's **Deployments** page is not a release dashboard. This package
is a library, not a deployed application; the release workflow creates no
deployment records. A successful release therefore does not add a green entry
there. For new tags containing this workflow, UI publication triggers validation
automatically; older tags may need the manual workflow described above.

### Release access

Keep repository write/admin access limited to intended release maintainers.
Audit installed Apps and tokens as well as collaborators and write-enabled deploy
keys. Workflows use read-only tokens and do not hold release credentials.
Protect maintainer accounts with 2FA or passkeys. An unused deployment environment
is not an authorization gate for releases created through GitHub UI.

The maintainer must register the repository with Packagist and enable automatic
updates. Do not publish an empty placeholder release to reserve a name.
After publication, check the indexed version and install that exact version in a
fresh PHP 8.2 consumer with Guzzle. Run the README quick start with a mock HTTP
transport; never validate a release through automated live device writes.
