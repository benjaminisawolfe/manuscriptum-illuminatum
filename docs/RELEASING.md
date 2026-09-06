# Releasing

Build releases from reviewed canonical source. Keep the theme and plugin versions
aligned and preserve historical release assets, licensing, authorship, and required
third-party notices.

## Preparation checks

Run these commands before committing a release:

    npm run check:repository
    npm run test:repository-guard
    npm run test:release-hygiene
    git diff --check
    npm run build:packages
    npm run validate:packages
    npm run check:release-hygiene

The build and package-validation commands also run the hygiene checker automatically.
The checker uses the existing ZIP reader and scans distributable source plus the actual
archive entries. It rejects stale, extra, duplicate, or missing files and requires
byte-for-byte agreement with the source selected by the builder.

## Hygiene policy

Packages must not include agent-control files, test suites, authentication state,
caches, task briefs, handoffs, QA scratch reports, deployment notes, recovery documents,
or temporary regression probes. Workstation home paths, obvious credential literals,
private keys, and recognizable access tokens are rejected. Failure messages identify
the package, file, rule, and text line without printing matched secret values.

Tooling terms such as Codex, OpenAI, ChatGPT, LLM, and generation labels are checked
with word boundaries. Generic terms such as agent, assistant, prompt, and token need
contextual review; they are not automatically prohibited. Any necessary tooling
reference requires an exact package path, matching term, and written rationale in the
checker's toolingAllowlist. There are no current exceptions, and that allowlist
cannot exempt credentials or development files.

Text and PNG text/EXIF metadata are inspected, including compressed PNG text.
Compressed image pixels are not treated as prose. These checks catch known artifact
and credential patterns; maintainers must still review file lists, newly added binary
formats, documentation, and suspicious matches.

The guarded demo-content commands remain supported maintenance functionality for
non-production installations. Synthetic campaign lore and ordinary WordPress
password checks, same-origin fetch credentials, and permalink tokens are product
code, not embedded credentials.

## Inspecting the artifacts

To print both complete ZIP file lists and their byte sizes and SHA-256 identities:

    node scripts/check-release-hygiene.mjs --list

For a machine-readable report including findings:

    node scripts/check-release-hygiene.mjs --json

Both canonical ZIPs in packages/ must be tracked. After any runtime change, deploy
the committed packages to an authorized staging installation and run the relevant
existing Playwright tests. Keep authentication local and ignored. Diagnostic test
plugins are installed only for the tests that need them and removed during teardown.

Review README, CHANGELOG, developer instructions, and technical documentation as well
as the packages. Keep useful technical guidance while removing unnecessary local paths
and task narratives. Do not rewrite Git history or alter factual attribution.

## Publication

After review and merge, verify that local main equals accepted origin/main. Revalidate
both ZIPs and compare their identities with the reviewed artifacts before tagging.
Investigate unexpected hash differences before publication.

Create the release from the accepted main commit and attach exactly the two canonical
theme and plugin ZIPs. Check the published asset URLs, sizes, and SHA-256 digests against
the README destinations and reviewed packages. Do not replace historical assets.
