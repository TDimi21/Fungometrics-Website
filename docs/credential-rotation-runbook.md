# FMTRX credential exposure and rotation runbook

## Exposure inventory

`.env.example` historically contained a database username/password and Laravel
`APP_KEY`. Treat both as exposed to every repository reader and every clone,
fork, cache, backup, and CI log that contained the file. Removing the current
text does not remove Git history.

## Database password rotation (separate maintenance)

1. Confirm current production database user, grants, application hosts, backup
   jobs, workers, cron jobs and monitoring clients.
2. Create a new long random password in the approved secret manager.
3. Prefer creating a replacement least-privilege database user so old and new
   credentials can overlap briefly.
4. Update only the production secret store/environment, restart application and
   queue workers, and verify read/write/transaction behavior.
5. Revoke the old user/password after all clients have moved.
6. Review database and web access logs from the first exposed commit onward.
7. Record the rotation in the security audit system without recording secrets.

## APP_KEY rotation (separate planned migration)

Do not use `php artisan key:generate --force` directly in production.

1. Inventory encrypted cookies, queued payloads, encrypted model attributes,
   signed URLs, password-reset flows and any custom `Crypt` usage.
2. Establish a maintenance window and tested rollback.
3. In a production clone, verify whether the framework/version supports
   previous-key fallback. If not, plan to invalidate sessions and re-encrypt
   durable encrypted values explicitly.
4. Generate a new key in the secret manager, never in source control.
5. Deploy code/config supporting the old key only for decrypt fallback and the
   new key for encryption, or intentionally invalidate all affected state.
6. Restart web/queue/scheduler processes, validate login, reset, cookies,
   signed URLs, queues and encrypted records.
7. Remove the previous key after the defined migration window.
8. Record completion without recording either key.
