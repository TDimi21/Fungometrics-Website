# Free access release

All signed-in coaches and players receive the implemented features for their role, with no subscription requirement or expiration. Player, coach, and team subscription limits are unlimited. Authentication, team membership, ownership, and administrator permissions still apply.

Deploy the web/backend changes first. Set `FMTRX_FREE_ACCESS_ENABLED=true` in the production environment and run `php artisan config:cache` to refresh cached configuration. Build/deploy web assets with `npm run build`. No database migration is needed for this change. Release a new mobile build to remove purchase menus and screens from installed apps. Existing clients obtain the new access on their next access refresh; the products endpoint returns no purchasable products.

The setting defaults to true independently of the older temporary access settings. Tests explicitly disable it except when testing this mode so paid-mode authorization regressions remain covered.

Existing billing records and provider webhooks are retained. This change does not cancel existing Apple or Google subscriptions or issue refunds. Existing customers can still use the store's subscription-management page to cancel renewals. Returning to a paid product would require restoring purchase UI as well as disabling free access.
