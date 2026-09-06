import { execFileSync } from 'node:child_process';

const expected = [
    'https://github.com/benjaminisawolfe/manuscriptum-illuminatum.git',
    'git@github.com:benjaminisawolfe/manuscriptum-illuminatum.git',
];

console.log('Expected canonical origin: ' + expected.join(' or '));

try {
    // Check the active working directory, even when this script is invoked by absolute path.
    const remoteUrls = (args) => execFileSync('git', args, {
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'pipe'],
    }).trim().split(/\r?\n/).filter(Boolean);
    const fetchUrls = remoteUrls(['remote', 'get-url', '--all', 'origin']);
    const pushUrls = remoteUrls(['remote', 'get-url', '--push', '--all', 'origin']);
    console.log('Detected origin (fetch): ' + fetchUrls.join(', '));
    console.log('Detected origin (push): ' + pushUrls.join(', '));

    if (!fetchUrls.length || !pushUrls.length || [...fetchUrls, ...pushUrls].some((url) => !expected.includes(url))) {
        console.error('Repository identity check failed. Stop before editing, committing, pushing, or deploying.');
        process.exitCode = 1;
    } else {
        console.log('Repository identity check passed.');
    }
} catch {
    console.error('Detected origin: unavailable in the active working directory.');
    console.error('Repository identity check failed. Run this command in a Git checkout with the canonical origin.');
    process.exitCode = 1;
}
