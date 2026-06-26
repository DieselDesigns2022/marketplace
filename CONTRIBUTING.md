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

## Documentation requirement

After every completed development phase, documentation must be reviewed and updated before the phase is considered complete. Future phases must update the relevant documentation files when behavior, workflows, routes, database structure, deployment steps, testing expectations, security posture, or troubleshooting guidance changes.
