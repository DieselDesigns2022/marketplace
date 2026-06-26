# Contributing

## Core rules

- GitHub is the source of truth.
- The VPS is the deployment and testing target.
- Codex is temporary.
- Preserve existing behavior unless a change is explicitly requested.
- Do not make unrelated changes.
- Do not merge before testing.
- Every phase must update documentation when relevant.

## Coding standards

- Use PSR-12 formatting for PHP changes.
- Use 4-space indentation for PHP.
- Use one statement per line.
- Do not commit compressed one-line source files.
- Do not commit minified code unless the project explicitly adds a vendor/build process that requires it.
- Never wrap imports in try/catch blocks.
- Keep readable names and straightforward control flow.
- Prefer small focused changes over broad rewrites.

## PHP checks

- Run `php -l` on every modified PHP file before committing.
- If a change touches routing, manually test each affected route.
- If a change touches uploads/downloads, test both positive and negative permission cases.

## Security requirements

- Use prepared statements for database queries.
- Validate and sanitize uploads.
- Protect private routes with login and role checks.
- Protect seller routes from non-sellers.
- Protect admin routes from non-admins.
- Protect downloads so only buyers who purchased a product can access files.
- Never commit `.env`.
- Never commit public uploads.
- Never commit protected uploads.

## Branch workflow

1. Create a branch for each phase or fix.
2. Confirm the branch before making changes with `git branch --show-current`.
3. Keep commits focused.
4. Push the branch to GitHub.
5. Open a PR against the intended target branch only after review/approval workflow requirements are satisfied.

## Developer Workflow

1. Confirm current branch and status.
2. Create verified backups before phase work begins.
3. Create or switch to the correct feature/phase branch.
4. Make only the requested changes.
5. Run required checks.
6. Test the changed workflow.
7. Run regression testing for previous completed phases.
8. Update all affected documentation.
9. Request completion review before PR creation.
10. Review changed files before PR creation.
11. Create PR only after approval.
12. Merge only after review and testing.
13. Pull merged branch/main onto VPS.
14. Run final smoke tests.
15. Confirm documentation and changelog/phase history are current.

## PR workflow

- Summarize what changed.
- List files changed.
- List tests and checks performed.
- Confirm whether migrations are required.
- Confirm whether backups were created before phase work.
- Confirm documentation was updated when relevant.
- Do not merge until reviewed and tested.

## Testing requirements

Minimum checks depend on the change type:

- PHP change: `php -l` on modified PHP files.
- Route/controller change: route smoke tests.
- Database change: migration and schema verification.
- Upload/download change: file permission and access tests.
- Formatting-only PR: verify no behavior changes and run syntax checks.
- Documentation-only PR: confirm only documentation files changed.

## Backup requirements

Before each phase:

1. Remove old scattered backup files from the project tree.
2. Create a fresh project `.tar.gz` backup.
3. Create a fresh database `.sql` backup.
4. Store backups outside committed source control.
5. Verify backups exist before beginning phase work.

## Phase Completion Checklist

A phase is NOT complete until all of the following are done:

- Feature implementation complete.
- Manual testing complete.
- Regression testing complete.
- Database migrations verified, if applicable.
- Upload folders and permissions verified, if applicable.
- VPS deployment verified.
- Documentation reviewed and updated.
- `README.md` updated, if applicable.
- `CHANGELOG.md` updated, if applicable.
- `DEVELOPMENT.md` updated, if applicable.
- `docs/PHASE_HISTORY.md` updated.
- `docs/DATABASE.md` updated, if applicable.
- `docs/ROUTES.md` updated, if applicable.
- `docs/DEPLOYMENT.md` updated, if applicable.
- `docs/TESTING.md` updated, if applicable.
- `docs/TROUBLESHOOTING.md` updated, if applicable.
- Pull Request reviewed.
- Pull Request merged into the correct target branch.
- VPS synchronized with GitHub.
- Final smoke test passed.

## Documentation requirement

Documentation is part of the project and is not optional. After every completed development phase, all documentation must be reviewed, and any affected documentation must be updated before the phase is considered complete.

Documentation must reflect the current codebase. It must clearly distinguish implemented functionality from planned or future functionality, and it must not claim planned/future features are currently implemented.

Future phases must update the relevant documentation files when behavior, workflows, routes, database structure, deployment steps, testing expectations, security posture, troubleshooting guidance, phase history, or changelog entries change.

## Lessons Learned

- Verify the current Git branch before making changes.
- Verify the Pull Request target branch before merging.
- Formatting-only changes can still introduce regressions.
- Public pages must be smoke tested after routing/controller/view changes.
- GitHub is always the source of truth.
- The VPS is the deployment/testing target.
- Codex is temporary and may use an internal `work` branch.
- Never assume Codex's internal branch equals the GitHub branch.
- Always verify GitHub commits and PR contents before merging.
- Always create verified backups before beginning phase work.
- Do not continue a phase if branch state, PR state, or deployment state is unclear.
