# Troubleshooting

## HTTP 500 checks

1. Reproduce the failing page/action.
2. Check `/var/log/nginx/marketplace.error.log`.
3. Run `php -l` on recently modified PHP files.
4. Check recent commits and changed files.
5. Verify `.env` database settings.
6. Verify migrations were applied.

## Nginx/PHP error log

```bash
sudo tail -n 100 /var/log/nginx/marketplace.error.log
```

Use the stack trace/file/line from the log as the primary debugging clue.

## 419 CSRF troubleshooting

- Confirm the form includes `_csrf`.
- Confirm the session is active.
- Confirm cookies are not blocked.
- Confirm POST requests are not cached/replayed with old tokens.
- Confirm the route is not receiving POST data from a form missing the shared layout token pattern.

## Missing controller method troubleshooting

- Check `public/index.php` route definition.
- Confirm the controller class exists.
- Confirm the method name matches exactly.
- Confirm method visibility is `public` for routed actions.
- Run `php -l` on the controller.

## Preview image troubleshooting

- Confirm upload folder exists and is writable.
- Confirm image path is stored in `product_images`.
- Confirm public product/store views use the stored path.
- Confirm Nginx can serve the public upload path.
- Confirm invalid file types are rejected.

## Upload folder troubleshooting

- Confirm ownership matches the web runtime user.
- Confirm permissions allow writes by PHP.
- Confirm upload size limits in PHP/Nginx are large enough.
- Confirm public uploads and protected uploads are in separate locations.
- Confirm uploads are ignored by Git.

## Git branch mismatch troubleshooting

- Run `git branch --show-current`.
- Run `git status --short`.
- Run `git fetch origin`.
- Compare local branch with expected GitHub branch.
- Do not merge or deploy until the branch/target is clear.

## Codex PR conflict troubleshooting

- Treat GitHub as source of truth.
- Fetch latest remote state.
- Resolve conflicts one file at a time.
- Avoid broad rewrites.
- Re-run checks and smoke tests.
- Document conflict resolution in the PR.

## Rollback from backup

1. Stop traffic or pause changes if needed.
2. Save current logs and note current commit.
3. Restore project `.tar.gz` backup if source is broken.
4. Restore database `.sql` backup if data/schema is broken.
5. Pull verified GitHub source after restore if needed.
6. Reapply only safe migrations.
7. Smoke test public, buyer, seller, admin, cart, checkout, and downloads.
