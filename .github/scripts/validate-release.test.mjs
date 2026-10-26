import assert from 'node:assert/strict';
import { test } from 'node:test';
import { validateMetadata, validateTag } from './validate-release.mjs';

const run = {
  id: 10, head_sha: 'example-commit', head_branch: 'main', event: 'push',
  workflow_id: 7, head_repository: { full_name: 'example/sdk' },
  status: 'completed', conclusion: 'success', html_url: 'https://example.test/ci/10',
};
const metadata = {
  tag: 'v0.3.0', commit: 'example-commit', onMain: true,
  changelog: '# Changelog\n\n## [Unreleased]\n\n## [0.3.0] - 2026-01-01\n\nRelease notes.\n\n## [0.2.0] - 2025-01-01\n\nOlder notes.\n',
  runs: [run], workflowId: 7, repository: 'example/sdk',
};

test('accepts a matching release with exact-commit main CI', () => {
  assert.equal(validateMetadata(metadata), run.html_url);
});

for (const tag of ['v01.2.3', 'v1.2', '1.2.3', 'v1.2.3-rc1', 'v1.2.3\n', 'v1.2.3;echo x', undefined]) {
  test(`rejects invalid version ${JSON.stringify(tag)}`, () => {
    assert.throws(() => validateTag(tag), /Release tag/);
  });
}

test('rejects a commit outside main', () => {
  assert.throws(() => validateMetadata({ ...metadata, onMain: false }), /ancestor/);
});

for (const changelog of [
  '# No releases',
  '## [0.2.0] - 2026-01-01\n\nWrong release.\n',
  '## [0.3.0] - 2026-01-01\n\n## [0.2.0] - 2025-01-01\n\nOld notes.\n',
]) {
  test(`rejects absent, mismatched or empty notes: ${JSON.stringify(changelog)}`, () => {
    assert.throws(() => validateMetadata({ ...metadata, changelog }), /changelog/);
  });
}

for (const change of [
  { head_sha: 'another-commit' }, { head_branch: 'other' }, { event: 'pull_request' },
  { workflow_id: 99 }, { head_repository: { full_name: 'other/sdk' } },
  { status: 'in_progress', conclusion: null }, { conclusion: 'failure' },
  { conclusion: 'cancelled' }, { conclusion: 'skipped' },
]) {
  test(`rejects unsuitable CI: ${JSON.stringify(change)}`, () => {
    assert.throws(() => validateMetadata({ ...metadata, runs: [{ ...run, ...change }] }), /CI run/);
  });
}

test('rejects missing CI', () => {
  assert.throws(() => validateMetadata({ ...metadata, runs: [] }), /CI run/);
});

test('does not reuse an older success when the newest run fails or is pending', () => {
  for (const status of ['completed', 'in_progress']) {
    assert.throws(() => validateMetadata({
      ...metadata, runs: [run, { ...run, id: 11, status, conclusion: 'failure' }],
    }), /CI run/);
  }
});
