import { execFileSync, spawnSync } from 'node:child_process';
import { fileURLToPath, pathToFileURL } from 'node:url';

export function validateTag(tag) {
  if (!/^v(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/.test(tag)) {
    throw new Error('Release tag must have the form vX.Y.Z without leading zeros.');
  }
}

export function validateMetadata({ tag, commit, onMain, changelog, runs, workflowId, repository }) {
  validateTag(tag);
  if (!onMain) {
    throw new Error('Release commit is not an ancestor of origin/main.');
  }
  const latest = changelog.match(/^## \[(\d+\.\d+\.\d+)\] - \d{4}-\d{2}-\d{2}\r?\n([\s\S]*?)(?=^## |(?![\s\S]))/m);
  if (!latest || latest[1] !== tag.slice(1) || latest[2].trim() === '') {
    throw new Error('Latest dated changelog release must match the tag and contain notes.');
  }
  const matching = runs.filter((run) => run.head_sha === commit
    && run.head_branch === 'main' && run.event === 'push'
    && run.workflow_id === workflowId && run.head_repository?.full_name === repository);
  matching.sort((a, b) => b.id - a.id);
  const latestRun = matching[0];
  if (!latestRun || latestRun.status !== 'completed' || latestRun.conclusion !== 'success') {
    throw new Error('Latest main-push CI run for the exact release commit must have succeeded.');
  }
  return latestRun.html_url;
}

function github(path, args = []) {
  return JSON.parse(execFileSync('gh', ['api', path, ...args], { encoding: 'utf8' }));
}

export function main(tag, repository) {
  validateTag(tag);
  if (!/^[\w.-]+\/[\w.-]+$/.test(repository ?? '')) {
    throw new Error('GITHUB_REPOSITORY must identify the repository being validated.');
  }
  process.chdir(fileURLToPath(new URL('../../', import.meta.url)));
  const commit = execFileSync('git', ['rev-parse', '--verify', `refs/tags/${tag}^{commit}`], { encoding: 'utf8' }).trim();
  const ancestry = spawnSync('git', ['merge-base', '--is-ancestor', commit, 'origin/main'], { encoding: 'utf8' });
  if (ancestry.status !== 0 && ancestry.status !== 1) {
    throw new Error(`Cannot check release ancestry: ${ancestry.stderr}`);
  }
  const changelog = execFileSync('git', ['show', `${commit}:CHANGELOG.md`], { encoding: 'utf8' });
  const workflow = github(`repos/${repository}/actions/workflows/ci.yml`);
  const result = github(`repos/${repository}/actions/workflows/${workflow.id}/runs`, [
    '--method', 'GET', '-f', `head_sha=${commit}`, '-f', 'branch=main',
    '-f', 'event=push', '-f', 'per_page=100',
  ]);
  const ci = validateMetadata({
    tag, commit, onMain: ancestry.status === 0, changelog,
    runs: result.workflow_runs, workflowId: workflow.id, repository,
  });
  console.log(`Validated ${tag} at ${commit}; successful main CI: ${ci}`);
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  main(process.argv[2], process.env.GITHUB_REPOSITORY);
}
