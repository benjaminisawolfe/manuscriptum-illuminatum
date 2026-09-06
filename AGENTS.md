# Repository preflight and development

Before any edits, commits, pushes, or deployments for every task, run:

```text
npm run check:repository
git rev-parse --show-toplevel
git remote -v
git branch --show-current
git rev-parse main
git rev-parse origin/main
git status --short
```

Stop immediately if the identity check fails. The canonical origin is
`https://github.com/benjaminisawolfe/manuscriptum-illuminatum.git`; its equivalent
`git@github.com:benjaminisawolfe/manuscriptum-illuminatum.git` SSH form is accepted.
The guard checks both fetch and push destinations in the active checkout.

Fetch canonical origin, confirm a clean or understood working tree, and start a new
scoped feature branch from current accepted canonical main unless the user explicitly
asks to continue an existing branch. Preserve unrelated changes. Do not merge into main,
force-push, rewrite history, or import another repository wholesale without explicit instructions.

The theme owns presentation. The plugin owns campaign data, roles, capabilities and
privacy. Preserve WordPress escaping, sanitization and authorization checks.

This computer is a development and browser-testing workstation. Do not install or
configure a local WordPress/server/database stack. Use the existing Playwright setup
against the authenticated remote staging installation supplied for the task.
Keep authentication state local and ignored; never print or commit its contents.
Do not weaken login or Storyguide controls for testing.

For application changes: edit locally, run appropriate static checks, inspect the diff,
rebuild both canonical ZIPs with `npm run build:packages`, and validate them with
`npm run validate:packages`. Commit and push the feature branch, then deploy packages
built from this canonical checkout to the authorized staging installation. Do not edit
theme/plugin source directly on the server. An approved deployment helper or the normal
WordPress administrator package replacement flow may be used. Do not deploy to production
unless explicitly authorized.

Run targeted regressions and the applicable existing Playwright suites after deployment.
Inspect visual mismatches; do not update baselines merely to make failures pass. Report
implementation, deployment, automated results, visual/accessibility verification, and
remaining limitations separately. Do not alter existing content as a regression workaround.

## Release hygiene

Before releasing, run `npm run test:release-hygiene` and `npm run check:release-hygiene`.
The build and package-validation commands also enforce hygiene and source/ZIP identity.
Keep test probes under excluded test support, never in distributable runtime code.
Review suspicious matches in context and preserve licensing and factual attribution.
See [Releasing](docs/RELEASING.md) for package inspection and publication checks.
