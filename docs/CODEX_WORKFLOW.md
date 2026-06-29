# Codex Workflow

## Core principles

- Codex is a temporary work environment.
- GitHub is the source of truth.
- The VPS is the deployment/testing target.
- Always work one step at a time.

## Branch awareness

Codex may use an internal branch named `work`. Always verify:

```bash
git branch --show-current
git log --oneline -1
```

Do not assume the Codex branch name is the final GitHub branch or PR target.

## Before PR

Before creating a PR, provide:

- Current branch.
- Latest commit.
- Files created/changed.
- Summary of each file.
- Confirmation whether application code changed.
- Confirmation whether only documentation files were created when documentation-only was requested.
- Testing/checks performed.

## Review requirement

Completion review is required before PR when the phase instructions require approval. Do not create a PR until approved by the project owner in that workflow. Do not merge a PR until it has been reviewed and tested.

## Update Branch

If GitHub shows an Update Branch button:

1. Stop making new changes.
2. Confirm the target branch.
3. Update from the GitHub UI or by merging/rebasing carefully.
4. Re-run required checks.
5. Re-review changed files.

## Conflicts

When conflicts occur:

1. Identify which files conflict.
2. Preserve current intended project behavior.
3. Avoid overwriting unrelated work.
4. Resolve conflicts in the smallest safe change.
5. Re-run tests/checks.
6. Document what was resolved.

## Disconnected Codex environment

If Codex state appears disconnected from GitHub or VPS:

1. Check current branch and latest commit.
2. Fetch from origin.
3. Compare local branch with remote branch.
4. Do not force push unless explicitly directed and safe.
5. Treat GitHub as the authority.

## Lessons learned from Phase 4.5

- Formatting-only changes can introduce regressions.
- Public product previews and sell pages must be smoke tested after broad source formatting.
- PR conflicts should be handled carefully and sequentially.
- Verify the actual branch/PR target rather than relying on assumptions.

## One step at a time workflow

1. Confirm scope.
2. Confirm branch.
3. Inspect source of truth.
4. Make the smallest required change.
5. Review diff.
6. Run checks.
7. Commit.
8. Report status.
9. PR only when approved by the governing workflow.
10. Merge only after review/testing.

## Phase 7 Codex workflow note

For Phase 7, Codex must not jump directly into broad redesign work. The first Codex task should be inspection and planning only: identify launch-polish issues, placeholder copy, confusing empty states, unfinished public content, and dashboard clarity problems. After inspection, Codex should provide a proposed file-by-file scope for approval before implementation.
