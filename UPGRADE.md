# UPGRADE

## > v7.x

- New `ROLE_SUPER_ADMIN` role: requires `c975l/config-bundle` >= v5.4 (adds the `restricted` config criterion, see its own UPGRADE.md). The DB backup credentials (`site-backup-db-host/user/password`) are now flagged `"restricted": true` and hidden from the Config admin (index/detail/edit/export) to anyone without `ROLE_SUPER_ADMIN`. `site:create` grants it automatically to the bootstrap user, but on an existing site you must add it yourself:
  - Add `"ROLE_SUPER_ADMIN"` to the `user-roles-available` config value (not synced automatically, existing config values are never overwritten), then grant it to the account(s) that should manage backup credentials
- Add `'@c975l/site-bundle/controllers-admin.js' => ['path' => './vendor/c975l/site-bundle/assets/controllers-admin.js', 'entrypoint' => true]` to `importmap.php` - needed for the title/slug confirm in the pages admin
- Requires `c975l/ui-bundle` >= v1.5 - see its own UPGRADE.md for the full list of `importmap.php` entries needed
- Run `php bin/console doctrine:migrations:diff && php bin/console doctrine:migrations:migrate` to add the new columns
- Migrate static file pages (`templates/pages/*.html.twig`) to DB pages
- Migrate redirect files (`templates/pages/redirected/`) to DB pages with `redirectTo` set to the target slug
- Migrate deleted files (`templates/pages/deleted/`) to DB pages with `isDeleted = true`
- The "Delete" action in the admin now soft-deletes instead of removing the row

## v3.x > v4.x

Changed compatibility to PHP 8

## v2.x > v3.x

Changed `localizeddate` to `format_datetime`.

## v1.x > v2.x

When upgrading from v1.x to v2.x you should(must) do the following if they apply to your case:

- The following parameters used to be defined in the template `layout.html.twig` are not used anymore, as set via c975L/ConfigBundle, so you can delete them:
  - {% set site = 'YOUR_SITE_NAME' %}
  - {% set author = 'THE_AUTHOR' %}
  - {% set firstOnlineDate = 'YYYY-MM-DD' %}
  - {% set logo = absolute_url(asset('images/og-image.png')) %}
  - {% set favicon = absolute_url(asset('favicon.ico')) %}
  - {% set appleTouchIcon = absolute_url(asset('apple-touch-icon.png')) %}
- Before the first use of parameters, you **MUST** use the console command `php bin/console config:create` to create the config files with default data.
- You have to enable the Routes in `app/config/routing.yml`, see README.md
