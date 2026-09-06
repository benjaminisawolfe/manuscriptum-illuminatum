import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { test } from 'node:test';

const guard = fileURLToPath(new URL('./check-repository.mjs', import.meta.url));
const canonical = 'https://github.com/benjaminisawolfe/manuscriptum-illuminatum.git';
const cases = [
    { name: 'accepts canonical HTTPS', origin: canonical, status: 0 },
    { name: 'accepts canonical SSH', origin: 'git@github.com:benjaminisawolfe/manuscriptum-illuminatum.git', status: 0 },
    { name: 'rejects the old repository', origin: 'https://github.com/benjaminisawolfe/ars-magica-theme.git', status: 1 },
    { name: 'rejects an unrelated push destination', origin: canonical, push: 'https://github.com/example/wrong.git', status: 1 },
    { name: 'rejects a missing origin', status: 1 },
    { name: 'rejects a non-repository working directory', noRepository: true, status: 1 },
];

for (const scenario of cases) {
    test(scenario.name, (t) => {
        const tempRoot = path.resolve(os.tmpdir());
        const prefix = path.join(tempRoot, 'manuscriptum-repository-guard-');
        const checkout = mkdtempSync(prefix);
        t.after(() => {
            const resolved = path.resolve(checkout);
            assert.equal(path.dirname(resolved), tempRoot);
            assert.ok(resolved.startsWith(prefix));
            rmSync(resolved, { recursive: true, force: true });
        });
        const git = (...args) => execFileSync('git', args, { cwd: checkout, stdio: 'pipe' });
        if (!scenario.noRepository) {
            git('init', '--quiet');
            if (scenario.origin) git('remote', 'add', 'origin', scenario.origin);
            if (scenario.push) git('remote', 'set-url', '--push', 'origin', scenario.push);
        }
        const result = spawnSync(process.execPath, [guard], { cwd: checkout, encoding: 'utf8' });
        assert.equal(result.status, scenario.status, result.stdout + result.stderr);
        assert.match(result.stdout, /Expected canonical origin:/);
        assert.match(result.stdout + result.stderr, /Detected origin/);
        if (scenario.origin) assert.ok(result.stdout.includes(scenario.origin));
    });
}
