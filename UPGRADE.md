# UPGRADE

## > v7.x

- Run `php bin/console doctrine:migrations:diff && php bin/console doctrine:migrations:migrate` to add the new `replaces`/`archivedSlug` columns on `site_page` (see "Applied template as a copy" / "publishAsReplacement" in the ChangeLog)
- If any site already set `theme-stylesheet` to `warm-artisan`, reset it to `warm` (the preset and its shape stylesheet were renamed) - e.g. via the theme admin screen, or directly in the `site_config` table
- If you inject `c975L\SiteBundle\Management\SitePageTemplateProvider` directly anywhere in your own code (controller, command...), switch to `c975L\SiteBundle\Management\PageTemplateRegistry` instead (same `getTemplate()`/`getTemplates()` intent, now named `get()`/`all()`) - it aggregates SiteBundle's own templates with any other bundle's, `SitePageTemplateProvider` itself is unchanged otherwise
- New `ROLE_SUPER_ADMIN` role: requires `c975l/config-bundle` >= v5.4 (adds the `restricted` config criterion, see its own UPGRADE.md). The DB backup credentials (`site-backup-db-host/user/password`) are now flagged `"restricted": true` and hidden from the Config admin (index/detail/edit/export) to anyone without `ROLE_SUPER_ADMIN`. `site:create` grants it automatically to the bootstrap user, but on an existing site you must add it yourself:
  - Add `"ROLE_SUPER_ADMIN"` to the `user-roles-available` config value (not synced automatically, existing config values are never overwritten), then grant it to the account(s) that should manage backup credentials
- Add `'@c975l/site-bundle/controllers-admin.js' => ['path' => './vendor/c975l/site-bundle/assets/controllers-admin.js', 'entrypoint' => true]` to `importmap.php` - needed for the title/slug confirm in the pages admin
- Requires `c975l/ui-bundle` >= v1.5 - see its own UPGRADE.md for the full list of `importmap.php` entries needed
- Run `php bin/console doctrine:migrations:diff && php bin/console doctrine:migrations:migrate` to add the new columns
- Migrate static file pages (`templates/pages/*.html.twig`) to DB pages
- Migrate redirect files (`templates/pages/redirected/`) to DB pages with `redirectTo` set to the target slug
- Migrate deleted files (`templates/pages/deleted/`) to DB pages with `isDeleted = true`
- The "Delete" action in the admin now soft-deletes instead of removing the row
- `<twig:c975LSite:General:CookieConsent/>` now wraps `vanilla-cookieconsent` v3 instead of the abandoned `cookieconsent2`. If your app's own translations override `text.cookies_dismiss`, switch to `label.cookies_reject`/`label.cookies_accept` (the old lib's single "OK" button is now a proper accept/reject pair)
- If you use `c975l/ui-bundle`'s `video_iframe` block, upgrade it too - its iframe is now gated behind this banner's consent (via a `window.CookieConsent` contract, see its own README/UPGRADE.md). If your CSS specifically targets that block's markup (a bare `<iframe>`), update it - it's now a wrapping `<div>` with a JS-created iframe
- `cookies` legal_model copy (fr/en/es) was rewritten - if you've customized it in an override, reconcile with the new version (removes the invalid "browsing implies consent" phrasing, documents Matomo/third-party content as separate categories)
- `Menu` now owns a `blocks` collection (like `Page`), used by the "footer" location (replacing the hardcoded `<twig:c975LSocial:SocialLinks/>` + `display-footer-social` config - add a `social_links_display` block in the footer's own menu edit screen instead) and by the new "email-footer" location (rendered in `templates/emails/footer.html.twig`, independent from the site footer's blocks). Run `php bin/console doctrine:migrations:diff && php bin/console doctrine:migrations:migrate` to add the `site_menu_blocks` table. The `display-footer-social` config value is removed - not synced automatically, delete it yourself if you keep a local copy of `configs.json`
- `MenuItem` and the `site_menu_item` table are removed - navbar/footer/email-footer no longer have a separate `items` collection, only `blocks` (see above). Menu links are now a "menu_link" Block kind, sortable alongside any other block using the same drag & drop as a `Page`'s blocks. Run `php bin/console doctrine:migrations:diff && php bin/console doctrine:migrations:migrate` to drop `site_menu_item` - **existing menu links are not migrated automatically, recreate them as "Menu link" blocks in each Menu's edit screen after upgrading**

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
