# Changelog

## v8.1.4

The fallback navbar carries the site name too

- `Navbar.html.twig` reads `site-navbar-show-name` on the fallback navbar as well (08/08/2026)
- Added `.nav-simple-name`, colored with `--navbar-site-name-color` (08/08/2026)
- README documents the name stacked under the fallback logo (08/08/2026)
- `NavbarHeightTest` covers both (08/08/2026)

## v8.1.3

A menu item points at a bundle's own row, not just at its screens

- A `menu_link` target can be an entry standing for one row of a bundle's data, its url generated from the route and parameters that entry declares (08/08/2026)
- `MenuExtension` labels a contributed target through `LinkableRouteRegistry::label()` (08/08/2026)
- The target picker and `SiteCreateCommand` label it through `LinkableRouteRegistry::pickerLabel()` (08/08/2026)
- An entry's `picker_label` names it in the target select, the rendered menu item keeping its bare `label` (08/08/2026)
- The target picker numbers a choice whose label another one already took, instead of dropping it (08/08/2026)
- `MenuExtension` no longer takes a `TranslatorInterface` (08/08/2026) [BC-Break]
- The navbar site name keeps `--navbar-site-name-color` on hover and focus (08/08/2026)
- Requires `c975l/core-bundle` v1.4 for the labelled linkable route entries (08/08/2026)
- README documents contributing one target per row (08/08/2026)

## v8.1.2.1

Added missing changelog release

## v8.1.2

The navbar keeps the same room above and below its line

- `sass/_navbar.scss` styles the fallback navbar on its own `.nav-simple` class, the bare `nav` element no longer (07/08/2026) [BC-Break]
- Added `--navbar-padding-y`, `--navbar-content-height` and `--navbar-height` to `sass/_variables.scss` and the scaffolded `themes/site.css` (07/08/2026)
- `.menu-header` holds the bar to `--navbar-height` (07/08/2026)
- `.menu-toggle` stays in the header's flow (07/08/2026)
- The mobile dropdown hangs off the navbar (`absolute` / `top: 100%`) (07/08/2026)
- `body.navbar-fixed` gives back `--navbar-height` (07/08/2026)
- A navbar set to `static` is written out as `relative` (07/08/2026)
- `.menu-fixed` hides the site tagline (07/08/2026)
- Added `NavbarHeightTest` (07/08/2026)

## v8.1.1.1

- Changed group's config (05/08/2026)

## v8.1.1

The title-change confirmation is left to UiBundle

- Removed `assets/js/title-confirm.js`, the controller now shipped by UiBundle (05/08/2026)
- Requires `c975l/core-bundle` ^1.2.5, which ships that controller (05/08/2026)
- Added `AdminControllersRegistrationTest` (05/08/2026)
- Documented the back-office controllers the bundle still ships in the readme (05/08/2026)

## v8.1.0

The footer menu is laid out from its own edit screen

- Added `Menu::$style`, the layout a menu's items are rendered with, `null` leaving it to the site's theme (05/08/2026) [Needs db update]
- Added `Menu::STYLE_INLINE`/`STYLE_BLOCK`, `Menu::setStyle()` storing anything else as `null` (05/08/2026)
- `MenuCrudController` offers a "Display style" select on the footer alone, its placeholder being the theme's own choice (05/08/2026)
- Added `MenuExtension::getMenuStyle()` and the `menu_style()` Twig function, cached like `menu_blocks()` (05/08/2026)
- `MenuCacheInvalidationListener` now also invalidates on the `Menu` row itself, which no `Block` event signals (05/08/2026)
- `Footer.html.twig` now writes `.menu-items--inline`/`--block` for the picked style (05/08/2026)
- Added the `footer .menu-items--inline`/`--block` rules, retuning `--footer-items-direction`/`--footer-items-justify` (05/08/2026)
- A menu's export/import now carries `style`, an export predating it leaving the layout to the theme (05/08/2026)
- Added the `label.menu_style`, `label.menu_style_help`, `label.menu_style_inline`, `label.menu_style_block` and `label.menu_style_theme` translations (05/08/2026)
- Added `MenuTest` and `FooterItemsStyleTest` (05/08/2026)
- Documented the display style in the readme's menus and themes sections (05/08/2026)
- Documented the `style` column's migration in UPGRADE.md (05/08/2026)

## v8.0.7

Pages tell the content-quality check whether they are indexable

- `ContentQualityHealthCheckProvider` carries each page's own `isIndexable`, the key turning the analyzer's `noindex` check on (05/08/2026)
- `ContentQualityHealthCheckProviderTest` covers the flag travelling with each url (05/08/2026)
- Documented the `noindex` check in the readme's content-quality sections (05/08/2026)

## v8.0.6

The trash stops offering switches over values it holds fixed

- `PageCrudController` hides the `isIndexable` column on the trash's index, next to `isPublished` (04/08/2026)
- `PageCrudControllerTest` covers both hidden columns and the unreferencing of a trashed page (04/08/2026)
- Documented in the readme that the trash's index carries neither publication column (04/08/2026)

## v8.0.5

The pages tell the site's llms.txt what they are

- `SitePageSitemapProvider` carries each page's `title` and social network summary, the optional keys `SeoFilesWriter` builds `public/llms.txt` from (04/08/2026)
- `SitePageSitemapProviderTest` covers the two keys (04/08/2026)
- Restyled the email footer as one centered inline row of links, in `sass/_email-footer.scss` (04/08/2026)
- Added `EmailFooterTest` (04/08/2026)
- Added `scaffold/removed.json`, the withdrawn scaffold files `c975l:scaffold:install` deletes (04/08/2026)
- Added `ScaffoldRemovedJsonTest` (04/08/2026)
- Documented the withdrawn-files deletion in the readme's scaffold section (04/08/2026)
- Raised `c975l/core-bundle` to `^1.2` (04/08/2026)

## v8.0.4

Slider, audio and readmore labels follow their components to UiBundle

- Removed the twelve `label.*` entries UiBundle's Slider, Audio and Readmore components used, now served from its own `ui` domain (03/08/2026)

## v8.0.3

Database pages render through the site's own layout

- `pages/page.html.twig` extends `['layout.html.twig', '@c975LSite/layout.html.twig']`, the app's file first (03/08/2026) [BC-Break]
- Added `PageLayoutInheritanceTest`, locking the order of that list and the `container` contract (03/08/2026)
- Documented in the readme the contract a replacement `layout.html.twig` has to hold up (03/08/2026)
- Added `SiteMaintenanceTaskProvider`, scheduling the smoke test nightly in a 5am-7am window (03/08/2026)
- Added `SiteMaintenanceTaskProviderTest` (03/08/2026)
- The readme's scheduler section no longer says this bundle schedules nothing (03/08/2026)
- `c975l/core-bundle` required at `^1.1`, where `SubmissionIntegrity` lives (03/08/2026)
- Added the `cs`, `fixer`, `stan`, `stan-scaffold` and `qa` Composer scripts, which the CI workflow now calls (03/08/2026)
- Added `bin/ci.sh`, replaying the CI checks on dependencies freshly resolved from Packagist (03/08/2026)
- `failOnPhpunitNotice` enabled in `phpunit.xml.dist` (03/08/2026)
- `SiteCreateCommandTest` stubs `ScaffoldInstaller` instead of mocking it (03/08/2026)
- `ScaffoldThemeTest` counts the `--card-accent` trio as per-variant, UiBundle setting it per hue (03/08/2026)

## v8.0.2

The wizard delivers the scaffold it says it delivered

- `c975l:site:create` installs the scaffold forcefully, a first install having nothing of the site's own to preserve (03/08/2026)
- Added `SiteCreateCommandTest::testExecuteInstallsTheScaffoldForcefully` (03/08/2026)
- `c975l/core-bundle` required at `^1.0.1`, where `ScaffoldInstaller::install()` takes `$force` (03/08/2026)
- Documented `c975l:scaffold:install`'s no-force default in the readme, the wizard no longer being its exact equivalent (03/08/2026)

## v8.0.1

A page cut short by PHP is refused instead of losing its blocks

- `ContentAccessTest` imports `Redirect` and `RedirectRepository` from ConfigBundle, where they moved (03/08/2026)
- `--footer-margin-top` reads UiBundle's `--section-space` instead of a fixed `3em` (03/08/2026)
- `FooterMarginTopTest` and the scaffolded `site.css` follow, the token's default no longer being a bare length (03/08/2026)
- `PageCrudController` refuses a submission PHP truncated at `max_input_vars` instead of deleting the blocks that fell past the cut (03/08/2026)
- Added the `text.page_submission_truncated` message in the three locales (03/08/2026)
- Added `PageSubmissionGuardTest`, locking the guard ahead of the pruning and the normalization (03/08/2026)
- Documented `max_input_vars` in the readme's page management section (03/08/2026)

## v8.0.0

A satellite bundle like the others, sitting on Core (Config + Ui)

- Requires `c975l/core-bundle`, which ships ConfigBundle and UiBundle as one package, instead of the two separate requirements (03/08/2026) [BC-Break]
- Added `ManagementTargetsTest`, checking every screen this bundle's management providers point at (03/08/2026)
- The Packagist description rewritten around what stays here after the move (03/08/2026)
- The tests locate UiBundle through its bundle class, `vendor/c975l/ui-bundle` no longer being where it sits (03/08/2026)
- `ImportmapProvider` declares its two entrypoints relative to this bundle, `ImportmapRegistry` resolving where it sits under `vendor/` (03/08/2026) [BC-Break]
- The theme compiler moved to UiBundle: `ThemeVariablesCssListener`, `theme_variables_css()` and the ten `theme-*` configs (02/08/2026) [BC-Break]
- The site graphics moved to UiBundle: `SiteGraphicCrudController`, its alert/export/import providers and `OgImageType` (02/08/2026) [BC-Break]
- The cookie banner moved to UiBundle, guard included, along with `site-enable-cookie-consent` and `url-cookies-policy` (02/08/2026) [BC-Break]
- `Redirect` moved to ConfigBundle, entity, subscriber, CRUD, export/import and `redirect-chains` check (02/08/2026) [BC-Break]
- `SessionNonceGenerator` and `site_copyright()` moved to ConfigBundle, with `site-author` and `site-first-online-date` (02/08/2026) [BC-Break]
- The content-quality machinery moved to ConfigBundle: analyzer, client, `urls-<bundle>` check and its pass (02/08/2026) [BC-Break]
- `PageExistenceChecker` became ConfigBundle's `UrlStatusChecker` (02/08/2026) [BC-Break]
- Added `PageContentOffenceLocator`, tracing a page's image/link back to its block for that analyzer (02/08/2026)
- `SitePageSitemapProvider` implements `SelfCheckedSitemapProviderInterface` (02/08/2026)
- `PagePublicUrlResolver::resolveSiteRoot()` removed, ConfigBundle's `SiteUrlResolver` spelling it now (02/08/2026) [BC-Break]
- `AssetController` and its `/asset/{file}` route removed (02/08/2026) [BC-Break]
- `svg-fonts` health check moved to UiBundle, reading only Media rows (02/08/2026) [BC-Break]
- The front "Edit" button of a `legal_model` block now opens its customization screen, on the very section hovered (02/08/2026)
- The legal models moved to UiBundle (02/08/2026) [BC-Break]
- Added `SiteBlockLocationProvider`, telling UiBundle which page a block sits on, and at which public address (02/08/2026)
- The six legal identity configs moved to ConfigBundle (02/08/2026) [BC-Break]
- `site-other-copyright` and `site-other-cookies` moved to UiBundle, its legal models being their only readers (03/08/2026) [BC-Break]
- `PageRepository::findWithLegalModelBlocks()` removed, UiBundle walking the blocks themselves (02/08/2026) [BC-Break]
- The account layer moved to ConfigBundle: CRUD, voter, three services, two templates, and the account half of the scaffold (02/08/2026) [BC-Break]
- The scaffolded `base.html.twig`/`layout.html.twig` moved to ConfigBundle, which ships one file resolving to this bundle's layout when installed (02/08/2026) [BC-Break]
- The two account email templates are gone, both being composed from their EmailTemplate now (02/08/2026) [BC-Break]
- The Fonts stack moved to UiBundle, along with the `nl2br`/`linkify`/`route_exists`/`template_exists`/`asset_exists` helpers (02/08/2026) [BC-Break]
- `MenuProvider` no longer declares UiBundle's or ConfigBundle's own entries (02/08/2026) [BC-Break]
- The failed-Messenger stack, `ExportTablesCommand` and `ScaffoldInstaller` moved to ConfigBundle (02/08/2026) [BC-Break]
- The `deployment`, `ssl-certificate`, `security-headers` and `seo-files` health checks moved to ConfigBundle, none of them needing a Page (02/08/2026) [BC-Break]
- `SiteShortcutProvider` keeps "Create a page" alone, the two others moving to ConfigBundle (02/08/2026) [BC-Break]
- `SiteMaintenanceTaskProvider` removed, its only task being declared by ConfigBundle now (02/08/2026) [BC-Break]
- The scaffolded `App\Scheduler\MaintenanceSchedule` is shipped by ConfigBundle now (02/08/2026)
- `DefaultPagesImporter` delegates its seeding to UiBundle's `FormSeeder` and ConfigBundle's `UserFormSeeder` (02/08/2026)
- `SiteCreateCommand` delegates its admin creation to ConfigBundle's `AdminUserCreator` (02/08/2026)
- `SiteCreateCommand` answers "no" by default when offering a route (login form, backoffice dashboard…) in the footer menu, the legal pages keeping their "yes" (02/08/2026)
- Added `SiteFormPageUrlProvider`, answering UiBundle's `form_url()` with the real Page (02/08/2026)
- Fixed `publishAsReplacement()` publishing the copy unreferenced, taking the replaced page's url out of the sitemap (02/08/2026)
- Dropped eight Composer requirements that followed the code using them (02/08/2026)
- The email address configs, `site-name` and the "made by" pair moved to ConfigBundle, `site-form-delay`/`site-form-gdpr` to UiBundle (02/08/2026) [BC-Break]
- Only the five `email-text-*` keys stay in the `email` group (02/08/2026)
- `url-terms-of-use` is declared by ConfigBundle, this bundle's identical copy being dropped (02/08/2026) [BC-Break]
- `config/configs-css.json` merged into `configs.json` (02/08/2026)
- `ConfigsJsonTest` now globs `configs*.json` (02/08/2026)
- Nine shared pieces left for UiBundle or ConfigBundle: Vich options, unique slug, block focus/move helpers, block cache abstraction, block export/import, download controller, health check error row, canonical url (02/08/2026) [BC-Break]
- `DefaultPagesImporter` implements UiBundle's `FormBlockDependencyProviderInterface` instead of being called directly by the importer (02/08/2026)
- Documented the whole move in the readme and in UPGRADE.md (02/08/2026)
- Added `Page::unreferenceWhenUnpublished()`, a `PreFlush` callback dropping `isIndexable` on any unpublished page (02/08/2026)
- Republishing a page leaves it unreferenced until "Référencer la page" is checked back (02/08/2026) [BC-Break]
- `sitemap-fields.js` now unchecks and locks the referencing switch when the page isn't published (02/08/2026)
- Added `assets/js/publication-switch.js`, doing the same to the referencing toggle of a page index row (02/08/2026)
- Documented the rule in the field's help text, in the three translations (02/08/2026)
- Shortened the scaffolded `themes/site.css` header to four one-line comments (02/08/2026)
- Added `SvgFontsHealthCheckProvider` (kind `svg-fonts`), reporting the uploaded SVG files whose text isn't vectorized (02/08/2026)
- `SiteGraphicCrudController` now shows its index actions inlined, like every other CRUD of the ecosystem (02/08/2026)

## v7.16.0

One theme file per bundle, concatenated into a single request

- The scaffolded `theme.css` became `site.css`, pruned to this bundle's own chrome (01/08/2026) [BC-Break]
- Everything UiBundle reads moved to its own `themes/ui.css` (01/08/2026)
- Added `scaffold/src/Service/ThemeStylesheetProvider.php`, contributing `assets/styles/themes/*.css` to the compiled stylesheet (01/08/2026)
- A theme no longer needs an import in `app.js` (01/08/2026)
- `ScaffoldInstaller::themeImportReminder()` now warns about a stale theme import instead of reminding to add one (01/08/2026) [BC-Break]
- That warning keeps the import until `App\Service\ThemeStylesheetProvider` is installed (01/08/2026)
- Dropped `--font-body-weight` from `site.css`, UiBundle's `ui.css` offering it (01/08/2026)
- `ScaffoldThemeTest` checks `site.css` and the installed UiBundle's `ui.css` together (01/08/2026)
- `ScaffoldThemeTest` now rejects a token offered by both theme files (01/08/2026)
- Raised the `c975l/ui-bundle` requirement to `^1.17`, for its app-asset stylesheet registry (01/08/2026)

## v7.15.5

Card accent hues offered by the scaffolded theme.css

- The scaffolded `theme.css` now lists UiBundle's twelve `--block-accent-*` tokens (01/08/2026)
- Documented the accent hues in the readme's `theme.css` section (01/08/2026)

## v7.15.4

One health check row for the site root

- `SslCertificateHealthCheckProvider` now records the home page's own url instead of raw `site-url` (01/08/2026) [BC-Break]
- Added `PagePublicUrlResolver::resolveSiteRoot()` (01/08/2026)
- Fixed `PagePublicUrlResolver` doubling the slash of a `site-url` saved with a trailing one (01/08/2026)
- `ContentQualityAnalyzer::TITLE_MIN_LENGTH` lowered from 30 to 10 characters (01/08/2026)
- A missing `og:title`/`og:description` is no longer listed on top of the empty field it comes from (01/08/2026)
- Updated the readme's content-quality rows on the title window and the share tags (01/08/2026)

## v7.15.3

Health check advice pointing at the field to fill

- The content quality advice and summary now name the `Social network summary` field instead of "meta description" (01/08/2026)
- Added `ContentQualityAnalyzer::DESCRIPTION_FIELD_LABEL` (01/08/2026)
- The description advice now opens the page's edit form on that field, through UiBundle's `focusField` param (01/08/2026)
- The advice line is left unlinked for a url with no `Page` behind it (01/08/2026)
- The w3c html/css advice lines now list the validator's own messages under their count (01/08/2026)
- Raised the `c975l/ui-bundle` requirement to `^1.15.2`, for its `fieldFocus` controller (01/08/2026)
- Condensed the scaffolded `theme.css` header from 27 lines to 4 (01/08/2026)
- Documented the `theme.css` format and both advice changes in the readme (01/08/2026)
- Added the `PageHealthCheckAdviceBuilderTest` cases covering the listed messages and the field link (01/08/2026)
- Added the `ContentQualityHealthCheckProviderTest` case covering the field named in the summary (01/08/2026)

## v7.15.2

Drop the dead RichSnippet component

- Removed `templates/components/General/RichSnippet.html.twig`, a copy of UiBundle's own, itself now removed (01/08/2026)

## v7.15.1

The scaffold's `ContentAccessTest` skipping redirect-covered pages

- The scaffold's `ContentAccessTest` now skips the deleted and draft pages whose url is answered by a redirect row (01/08/2026)

## v7.15.0

Maintenance commands declared by the bundles, not by the scaffold

- The scaffold's `MaintenanceSchedule` now delegates to ConfigBundle's `MaintenanceScheduleBuilder` and lists no command of its own (01/08/2026)
- Added `SiteMaintenanceTaskProvider`, declaring `c975l:site:messenger-cleanup` (01/08/2026)
- The scaffold's `MaintenanceScheduleTest` now asserts what stays the app's responsibility, not the bundles' own commands (01/08/2026)
- Added `SiteMaintenanceTaskProviderTest` (01/08/2026)
- Added `--path` to `c975l:scaffold:install`, restricting a run to a directory or a single file (01/08/2026)
- Added `--dry-run` to `c975l:scaffold:install`, listing what would be copied and backed up without writing anything (01/08/2026)
- `ScaffoldInstaller::install()` now takes those two arguments and returns the list of files alongside its counts (01/08/2026)
- `c975l:scaffold:install` now fails on a `--path` no scaffold file answers to, instead of reporting zero counts (01/08/2026)
- `ScaffoldInstaller::install()` now returns those unmatched paths (01/08/2026)
- Documented the scaffolded schedule's new constructor in the upgrade notes (01/08/2026)
- Raised the `c975l/config-bundle` requirement to `^5.16` (01/08/2026)
- Updated the readme's scheduler and scaffold sections (01/08/2026)
- Added the `ScaffoldInstallerTest` cases covering `--path` and `--dry-run` (01/08/2026)
- Added the `ScaffoldInstallCommandTest` cases covering both options reaching the installer (01/08/2026)
- `PageSpeedInsightsClient` exceptions no longer carry the requested url, which held the api key (01/08/2026)
- The delete action on a page now asks its own confirmation, stating the page goes to the trash and stays restorable (01/08/2026)
- Fixed the container compilation of an app without `nelmio/security-bundle` (01/08/2026)
- The `SessionNonceGenerator` decoration moved to `config/services_nelmio.yaml`, imported by `c975LSiteBundle` only when `NelmioSecurityBundle` is installed (01/08/2026)
- Added `SessionNonceGeneratorRegistrationTest` (01/08/2026)

## v7.14.1

The scaffold's `ContentAccessTest` catching up with the canonical url and the gone redirects

- The scaffold's `ContentAccessTest` now requests pages on their canonical (slashless) url, the trailing slash form having become a 301 in v7.14.0 (31/07/2026)
- Added its `testTrailingSlashRedirectsToCanonicalUrl()` case covering that redirect (31/07/2026)
- Its redirect case now expects 410 on a gone row and fabricates one, so that path is covered on a site with none (31/07/2026)
- `LegalModelRenderer` now serializes through the document instead of `Dom\Element::$outerHTML`, absent in PHP 8.4 (31/07/2026)

## v7.14.0

Legal models customizable section by section, without losing updates

- The legal models now tag every `<section>` and `<h3>` with a stable `data-legal-id`, identical across `fr`/`en`/`es` (31/07/2026)
- The legal models now emit `%site-name%` and the other identity values through a new `legal_var()` Twig function instead of `config()` (31/07/2026)
- Added `LegalModelPlaceholders`, resolving those values and substituting the markers left in client-authored text (31/07/2026)
- A model included directly from an app's own template keeps printing resolved values (31/07/2026)
- `c975l:site:create` now declares the `^/management` `access_control` rule in `security.yaml` (31/07/2026)
- Added the `SiteCreateCommandTest` cases covering that rule (31/07/2026)
- Added `LegalModelRenderer`, applying a sparse customization delta (hide, rewrite, move, add) over the bundle's own template (31/07/2026)
- Added `LegalModelCustomizer` and `LegalModelCatalog` (31/07/2026)
- Added `LegalModelExtension`, exposing `legal_model()` and `legal_var()` (31/07/2026)
- Added `LegalModelController` and its "Management → Legal models" screen (31/07/2026)
- `LegalModelType` now builds its model choices from `LegalModelCatalog` (31/07/2026)
- `MenuProvider::getLinks()` now contributes that screen to the dashboard, gated on `site-role-editor` and tiered `advanced` (31/07/2026)
- The customization screen now shows the bundle's own title and text, editable in place (31/07/2026)
- Added `LegalModelRenderer::comparable()`, ignoring `class` and `style` when comparing against the bundle's wording (31/07/2026)
- The screen now reorders by drag and drop, reusing UiBundle's `ea-sortable`, scoped per level (31/07/2026)
- Added the per-unit "back to the bundle's text" button (31/07/2026)
- Added the button removing a section the client added (31/07/2026)
- An added section lands at the end of the level it is attached to (31/07/2026)
- A unit the model ships without a heading now gets one when the client titles it (31/07/2026)
- Added `LegalModelDriftHealthCheckProvider`, reporting as `ok` the rewritten sections whose bundle wording has since changed (31/07/2026)
- `LegalModel.html.twig` now delegates to `legal_model()` (31/07/2026)
- A locale with no template of its own falls back on `fr` (31/07/2026)
- `PageRepository::findWithLegalModelBlocks()` added (31/07/2026)
- Added `Redirect::$gone`, answering 410 instead of redirecting (31/07/2026) [Needs db update]
- `Redirect::$toUrl` is now nullable, a conditional constraint still requiring it on every other row (31/07/2026)
- `Redirect::$fromPath` now accepts a trailing `*`, covering every url below it (31/07/2026)
- Added `RedirectRepository::findCandidatesForPath()`, fetching the exact row and every prefix one at once (31/07/2026)
- `RedirectSubscriber` now takes the exact row first, then the longest matching prefix (31/07/2026)
- `RedirectCrudController` now offers the "gone" switch, `toUrl` no longer required at form level (31/07/2026)
- The redirect export/import now carries `gone` (31/07/2026)
- Deleting a page permanently now leaves a gone redirect behind on its own url (31/07/2026)
- Redirects pointing at that page are now turned gone rather than deleted (31/07/2026)
- Fixed `RedirectChainHealthCheckProvider` failing on a gone row's null `toUrl` (31/07/2026)
- Added the `RedirectTest`, `RedirectSubscriberTest`, `RedirectImportProviderTest`, `PageCrudControllerTest` and `RedirectChainHealthCheckProviderTest` cases covering gone rows and prefixes (31/07/2026)
- Added the `label.gone`/`label.gone_help` translations (31/07/2026)
- Added `CanonicalUrlExtension`, exposing `canonical_url()` (31/07/2026)
- `layout.html.twig` now builds `<link rel="canonical">` and `og:url` with it, instead of `app.request.uri` (31/07/2026)
- Removed the layout's `hreflang` tags, built on that same `app.request.uri` (31/07/2026) [BC-Break]
- `/pages/{slug}/` now answers a 301 to the slashless form (31/07/2026)
- An error page now defaults to `noindex, follow` (31/07/2026)
- Added `CanonicalUrlExtensionTest` and `RobotsMetaTest` (31/07/2026)
- The site graphics index now shows one button per graphic still missing, replacing the alerts panel (31/07/2026)
- A graphic's upload form now arrives with its role pre-filled and frozen (31/07/2026)
- `SiteGraphicAlertProvider`'s alert now links to that same pre-filled form (31/07/2026)
- Added `SiteGraphicCrudControllerTest` and the `label.create_site_graphic` translation (31/07/2026)
- The seeded legal and contact pages now carry a meta description of their own (31/07/2026)
- The seeded form fields no longer carry a placeholder (31/07/2026)
- Added the `DefaultPagesImporterTest` cases covering both (31/07/2026)
- New default palette: deep blue primary, azure secondary, tinted background and near-black blue text (31/07/2026)
- `--button-secondary-color` is now white, matching the primary button's own label (31/07/2026)
- Dark mode now lightens `--link-color`, `--navbar-active-color` and `--navbar-site-name-color` (31/07/2026)
- The scaffold's `theme.css` restates those defaults (31/07/2026)
- The scaffold's `MaintenanceSchedule` now asks for a cadence instead of listing kinds (31/07/2026)
- Removed the scaffold's health check entries naming `urls-book`, `urls-shop`, `urls-crowdfunding` and `urls-gallery` (31/07/2026)
- `DeclaredUrlsHealthCheckProvider` now carries its cadence per instance (`HealthCheckFrequencyAwareInterface`), weekly by default (31/07/2026)
- `DeclaredUrlsHealthCheckPass` now reads each bundle's cadence off its own `SitemapProviderInterface` (31/07/2026)
- Updated the scaffold's `MaintenanceScheduleTest` accordingly (31/07/2026)
- Added the `DeclaredUrlsHealthCheckPassTest` and `DeclaredUrlsHealthCheckProviderTest` cases covering the cadence (31/07/2026)
- `c975l:scaffold:install` now also gitignores `public/medias` and the singleton graphics written at the root of `public/` (31/07/2026)
- Added the `ScaffoldInstallerTest` cases covering those rules (31/07/2026)
- Added `LegalModelCatalogTest`, `LegalModelExtensionTest`, `LegalModelControllerTest` and `LegalModelDriftHealthCheckProviderTest` (31/07/2026)
- Fixed the README's "Legal models" section (31/07/2026)
- Added the README's "Back-office access control", "Canonical url" and "Error pages are not indexable" sections (31/07/2026)
- Removed the README's "Alternate languages (hreflang)" section (31/07/2026)
- The README's demo links now point at `bundles.975l.com`, the ecosystem's dedicated site (31/07/2026)
- `Service\GalleryShowcaseProvider` skips its `articles_slider` preview when no placeholder image is available (31/07/2026)
- Requires `c975l/config-bundle` >= 5.15 (31/07/2026)
- Requires `c975l/ui-bundle` >= 1.14 (31/07/2026)

## v7.13.3

Analyse the scaffold at PHPStan level 6, the level the apps run

- Added `phpstan-scaffold.dist.neon`, running the static analysis at level 6 on the scaffold only (30/07/2026)
- The `CI` workflow now runs that second static analysis pass (30/07/2026)
- The scaffold's `App\Entity\User::$roles` and `setRoles()` now declare `string[]` (30/07/2026)
- The scaffold's `ResetPasswordRequestRepository` now declares `@extends ServiceEntityRepository<ResetPasswordRequest>` (30/07/2026)
- The scaffold's tests now type their `$client` property `KernelBrowser` (30/07/2026)
- The scaffold's tests now fetch `PageRepository` from the container instead of `EntityManagerInterface::getRepository()` (30/07/2026)
- `MaintenanceScheduleTest::recurringMessages()` now declares its return type (30/07/2026)

## v7.13.2

Add the Codacy grade badge to the README

- Added the Codacy grade badge to the README (30/07/2026)

## v7.13.1

Pin the CI's third-party actions to a commit SHA

- `sass/_normalize.scss` (normalize.css v8.0.1) is replaced by `sass/_modern-normalize.scss` (modern-normalize v3.0.1) (30/07/2026) [BC-Break]
- The MIT attribution of that file now survives minification, `/*!` replacing `/*` (30/07/2026)
- Added a `.stylelintrc.json`, read by Codacy in place of its default ruleset (30/07/2026)
- The `CI` workflow now uses `actions/checkout@v5` instead of `v4` (30/07/2026)
- `shivammathur/setup-php` and `codacy/codacy-coverage-reporter-action` are now pinned to a commit SHA (30/07/2026)

## v7.13.0

Require PHP 8.4 and Symfony 8, and type User on ConfigBundle's contract

- `php` is now required in `>=8.4` instead of `>=8.1` (30/07/2026) [BC-Break]
- The `symfony/*` requirements are now constrained to `^8.0` instead of `*` (30/07/2026) [BC-Break]
- The `symfony/ux-*` requirements are now constrained to `^3.3` instead of `*` (30/07/2026)
- The third-party requirements left in `*` are now bounded on their installed version (30/07/2026)
- `c975l/config-bundle` is now required in `^5.13`, the version declaring `Contract\UserInterface` (30/07/2026)
- `c975l/ui-bundle` is now required in `^1.12` instead of `*` (30/07/2026)
- `Page::$user` and `CollectionItem::$user` are now typed `c975L\ConfigBundle\Contract\UserInterface` instead of `App\Entity\User` (30/07/2026) [BC-Break]
- The scaffold's `App\Entity\User` now implements `c975L\ConfigBundle\Contract\UserInterface` (30/07/2026)
- `UserManagementVoter` now type-checks its subject against `c975L\ConfigBundle\Contract\UserInterface` (30/07/2026)
- `PageCrudController::cloneBlock()`, `cloneMedia()` and `DefaultPagesImporter::importDefinition()`, `buildPage()` now type their `$user` argument instead of taking `mixed` (30/07/2026) [BC-Break]
- Added `.codacy.yaml`, `phpcs.xml.dist` and `eslint.config.mjs` (30/07/2026)
- Applied PSR-12 to the codebase (30/07/2026)
- Added `.php-cs-fixer.dist.php`, applying the Symfony coding standards (30/07/2026)
- Added `phpstan.dist.neon`, running the static analysis at level 5 (30/07/2026)
- Added `phpstan-baseline.neon`, freezing the errors that predate the analysis (30/07/2026)
- Added the `CI` GitHub Actions workflow, running PSR-12, the static analysis, the tests and the coverage upload (30/07/2026)
- The local Codacy CLI now runs `eslint@9.39.5` (30/07/2026)
- Added `ComponentCenteringCascadeTest`, running UiBundle's `ComponentCenteringAnalyzer` over both bundles' stylesheets in the order a page loads them (30/07/2026)
- Catches what neither bundle can see from inside itself: this bundle's page-wide `section { margin: 1em auto }` against a UiBundle component's own centering, the pair UiBundle's block reset was written for (30/07/2026)
- Skips itself until the installed `c975l/ui-bundle` carries that analyzer (v1.12.3), rather than failing a suite over a version it cannot ask for (30/07/2026)
- The `Exception/*.html.twig` pages now serve their images through `asset()`, in place of `absolute_url(asset())` (30/07/2026)
- `phpcs`, `php-cs-fixer` and `phpstan` now cover `scaffold/` too, which this bundle ships but never linted (30/07/2026)
- `phpstan` skips `scaffold/tests/Controller`, as `phpunit.xml.dist` already does (30/07/2026)
- Applied PSR-12 to `scaffold/` (30/07/2026)
- Removed the scaffolded `SecurityController::login()` guard on a null `getLastUsername()`, which is typed `string` and would have redirected the route onto itself (30/07/2026)
- `html` now clips its horizontal overflow with `clip` instead of `hidden` (30/07/2026)
- Fixed the root becoming a scroll container of its own, `hidden` also pinning `overflow-y` (30/07/2026)
- The stylesheets and the importmap now carry `data-turbo-track="reload"` (30/07/2026)
- Fixed a visitor already on the site keeping the old assets after a deploy, Turbo Drive never re-evaluating the `<head>` (30/07/2026)
- Added `HorizontalOverflowTest` and `HeadAssetTrackingTest` (30/07/2026)
- The requirements section of the readme now states PHP 8.4, Symfony 8 and the `UserInterface` a site's `User` must implement (30/07/2026)
- Documented `--reading-max-width` in the readme's `theme.css` section (30/07/2026)
- The `articles_slider` block now reads an article's hook through `plain_text`, in place of `striptags` (30/07/2026)
- Fixed a slide's caption showing `&amp;` where the hook holds an `&`, Twig escaping the entity `striptags` had left behind (30/07/2026)
- The `description`/`og:description` meta tags now read that same filter, having carried the doubly-escaped text too (30/07/2026)
- Needs `c975l/ui-bundle` v1.12.3, the version declaring `plain_text` and `.text-hook--article` (30/07/2026)
- `.site-article > div` now excludes `.text-hook`, that rule being one point more specific and having taken back the margin the hook sets (30/07/2026)
- Added the `--text-hook-*` tokens to the scaffolded `theme.css`, none of them documented there before (30/07/2026)
- Added `--reading-max-width`, the measure `.legal div`/`.text`/`.site-article` are laid out on, previously a bare 800px (30/07/2026)
- It defaults to `min(75ch, 90vw)` and the `min-width: 768px` breakpoint restating the measure is gone, one rule in place of two (30/07/2026) [BC-Break]
- Body copy read at 90 characters to the line on the 800px it had, past the 45-75 a line is comfortably read on (30/07/2026)
- Set in `ch` rather than `px`, so the measure follows the body font (30/07/2026)
- A design that kept the old measure sets `--reading-max-width: min(800px, 90vw)` in its own `theme.css` (30/07/2026)
- Fixed an article rendering edge to edge between 768px and ~890px, the 800px matching or outrunning the viewport and leaving `margin: auto` nothing to split (30/07/2026)
- Added the measure tokens to the scaffolded `theme.css`, alongside the five UiBundle reads for its own sections (30/07/2026)
- Added `ReadingMeasureTest`, locking the three rules to one tokenised measure and keeping a bare length out of the compiled sheets (30/07/2026)
- `ScaffoldThemeTest` now also reads every `var(--x, …)` of this bundle's and UiBundle's sass, a token carrying an inline fallback reaching no `:root` and having been invisible to the existing check (30/07/2026)
- Fixed the scaffolded `theme.css` having shipped a whole release without the `--form-width`/`--input-*`/`--alert-color` batch, none of them declared in `sass/_variables.scss` (30/07/2026)

## v7.12.1

Added the guided projects, and made SocialBundle optional

- Moved `c975l/social-bundle` from the requirements to the suggestions (29/07/2026) [BC-Break]
- `layout.html.twig` now includes `@c975LSocial/shareButtons/default.html.twig` with `ignore_missing`, instead of calling `share_buttons_default()` (29/07/2026)
- Fixed a site without SocialBundle answering 500 on every front page, Twig resolving a function call at compile time (29/07/2026)
- Added `OptionalBundleTemplateTest`, keeping a suggested bundle's Twig function out of this bundle's templates (29/07/2026)
- Added `SiteGuidedProjectProvider`, contributing this bundle's guided projects to the dashboard (29/07/2026)
- Added the "Créer une page", "Mettre une page dans un menu", "Créer une collection" and "Réviser une page déjà en ligne" projects (29/07/2026)
- Added the `label.guided_project_*`/`description.guided_project_*` and `label.guided_step_*`/`description.guided_step_*` translations (29/07/2026)
- The four guided projects are now gated by `site-role-editor` (29/07/2026)
- Fixed the guided steps highlighting `.action-save`, EasyAdmin naming that button `action-saveAndReturn` (29/07/2026)
- Fixed the "create the menu" step highlighting the first create button whatever its location (29/07/2026)
- The `publishAsReplacement` action group now states its own `action-publishAsReplacement` class, EasyAdmin only giving a default one to an action (29/07/2026)
- Added `SiteGuidedProjectProviderTest` (29/07/2026)
- `PageServiceInterface`'s comments are now PHPDoc blocks, `findAll()` declaring its `Page[]` return (29/07/2026)

## v7.12.0

- Removed SocialBundle's `--social-share-*` custom properties from the scaffolded `theme.css` (29/07/2026)
- Declaring them in `:root` flattened every share buttons style into one (29/07/2026)
- A page answering 404 is now a `content-quality` error instead of a "not tested" row (29/07/2026) [BC-Break]
- A page answering 410 is now a `content-quality` warning, and any other 4xx/5xx an error carrying its own code (29/07/2026)
- A host that never answered is now told apart from a page that answers 404 (29/07/2026)
- Added `PageExistenceChecker::status()`, returning the code where `exists()` only returns a bool (29/07/2026)
- `content-quality` now warns on a url that redirects before serving its content (29/07/2026)
- `PageHealthCheckAdviceBuilder` now advises on a redirecting url (29/07/2026)
- `seo-files` now warns on a sitemap declaring no url (29/07/2026)
- `seo-files` now warns on a sitemap file not rewritten for over 30 days (29/07/2026)
- `SeoFilesClient::fetch()` now also returns the response's `Last-Modified` date (29/07/2026)
- Added `Page::$options`, a JSON payload holding the per-page display options (29/07/2026)
- Added `Page::$isTitleDisplayed`, the first of them, shown as a switch on the page edit screen (29/07/2026)
- `Page`'s export/import now carries `options` (29/07/2026)
- A page answering 403, 405, 429, 501 or 999 is now a `content-quality` warning instead of an error (29/07/2026)
- `ContentQualityClient::INCONCLUSIVE_STATUSES` is now public (29/07/2026)
- Added the `label.health_check_url_inconclusive` translation (29/07/2026)
- Added the `label.health_check_url_not_found`/`_http_error`/`_unreachable`, `label.health_check_content_quality_redirects`, `label.health_check_advice_redirects`, `label.health_check_sitemap_empty`/`_stale`/`_ok_urls` translations (29/07/2026)
- Moved the `site-url` config entry and its translations to ConfigBundle (29/07/2026) [BC-Break]
- Moved `BackupCommand` to ConfigBundle (29/07/2026) [BC-Break]
- Moved the `site-backup-*` config entries and their translations to ConfigBundle, slugs unchanged (29/07/2026) [BC-Break]
- The scaffold's `MaintenanceSchedule` now runs `c975l:config:backup`, the former name kept as an alias (29/07/2026)
- The scaffold's `MaintenanceSchedule` now runs ConfigBundle's `c975l:config:backup:digest` on Mondays, instead of `c975l:config:backup --report` (29/07/2026)
- `PageHealthCheckAdviceBuilder` no longer logs the `backup` kind as unmapped (29/07/2026)
- Moved `sass/_forms.scss` to UiBundle (29/07/2026) [BC-Break]
- Moved the password behaviours out of the `basic` controller into UiBundle's own `password` one, along with `assets/js/handlers.js` and `translations.js` (29/07/2026) [BC-Break]
- `layout.html.twig` now writes `data-controller="basic password"` (29/07/2026)
- Moved `public/icons/eye.svg`/`eye-slash.svg` to UiBundle (29/07/2026)
- Added `color-scheme` to `:root` and to the dark-mode block (29/07/2026)
- Dark mode now restates every form token (`--form-input-color`, `--label-color`, `--required-color`, `--input-*`) (29/07/2026)
- Fixed a dark site showing `#555` field text on its `#121212` page, an invisible hover glow and a field with no surface of its own (29/07/2026)
- Added `--input-background`, `--input-valid-border-color`/`--input-invalid-border-color` and their `-shadow-` pair, `--input-icon-filter` and `--form-width` (29/07/2026)
- `sass/_dimensions.scss` is now used by `emails.scss` alone, its width classes duplicating UiBundle's `sass/_sizes.scss` (29/07/2026) [BC-Break]
- Moved `sass/_badges.scss`, `_blockquotes.scss`, `_alignments.scss`, `_colors.scss` and `_iframe.scss` to UiBundle (29/07/2026) [BC-Break]
- Added `--alert-color`, and dark mode now restates it along with the four alert backgrounds and `--alert-danger-color` (29/07/2026)
- `--alert-danger-color` darkened to `#9c3a44`, `#b64450` failing WCAG AA on its own tint (29/07/2026)
- Added `--input-invalid-text-color`, lightened in dark mode (29/07/2026)
- Added `--input-margin-block`, the room kept above and below a field (29/07/2026)
- Added `--input-glyph-offset`, the distance from the bottom of a field's wrapper to its validation glyph (29/07/2026)
- `sass/emails.scss` now holds this site's branding alone, the email base coming from UiBundle's own (29/07/2026) [BC-Break]
- Removed `sass/_email-overrides.scss` (29/07/2026) [BC-Break]
- `emails/fullLayout.html.twig` now sources `@c975LUiCss/emails.min.css` before this bundle's own (29/07/2026)
- `emails/fullLayout.html.twig` now runs the whole `<style>` through `resolve_css_variables` before inlining (29/07/2026)
- `sass/emails.scss` keeps `_variables.scss` for the footer's own tokens (29/07/2026)
- Moved `tests/TranslationsJsTest` to UiBundle with the file it guards (29/07/2026)
- Removed the "new menu" form, the index now offering one create button per location not created yet (29/07/2026) [BC-Break]
- Removed `templates/management/menu_crud_new.html.twig` (29/07/2026) [BC-Break]
- Added `MenuCrudController::create()`, creating the row for the posted location and opening its edit screen (29/07/2026)
- A menu's location is now frozen on every screen (29/07/2026)
- The menu index now shows its row actions inlined (29/07/2026)
- Added the `label.create_menu` translation (29/07/2026)
- A navbar now only offers `menu_link`, through UiBundle's exclusive `menu_navbar` context (29/07/2026) [BC-Break]
- `menu_link` is now tagged `contexts: 'menu,menu_navbar'` (29/07/2026)
- `MenuLinkType` now builds its anchor choices with UiBundle's `BlockAnchorCollector`, a container's nested sections included (29/07/2026)
- `MenuLinkType` now eager-joins the blocks' slots (29/07/2026)
- `MenuExtension` now resolves an anchor's label through that same collector, memoized per page (29/07/2026)
- The blocks collection now takes the full row on the page and menu edit screens (29/07/2026)
- `body` is now a flex column, keeping the footer at the bottom of a page too short to fill the viewport (29/07/2026)
- The share buttons moved out of `<main>` into their own `<aside class="page-share">` (29/07/2026)
- Added `StickyFooterTest` (29/07/2026)
- `user-roles-available` no longer ships ROLE_SUPER_ADMIN, `UserCrudController` offering it to an acting super admin alone (29/07/2026)
- Added a TL;DR and a contents index to the readme (29/07/2026)
- Trimmed the comments across the bundle (29/07/2026)

## v7.11.0

- `--section-bg-muted`, `--footer-border` and `--footer-link-hover-background` are now mixed out of the site's own colors in `sass/_variables.scss` itself, where they were only tinted in the scaffolded theme: the muted flat comes from `--primary` instead of `--surface-alt`'s grey, the footer rule and its link hover from `--footer-background` instead of a flat grey and a black wash (29/07/2026)
- A site with no `assets/styles/themes/theme.css` of its own therefore renders those three differently - see UPGRADE (29/07/2026)
- `--surface-alt` keeps its neutral definition, still backing what genuinely wants a grey (a portfolio thumbnail's empty frame) (29/07/2026)
- Added `--frame-background`, the canvas on both sides of the page once the viewport is wider than `--body-max-width`, with `html` now carrying it in `sass/_general.scss`. Defaults to `var(--background)`, which is what a browser already painted there on its own (29/07/2026)
- The scaffolded `theme.css` now lists the whole override surface - every custom property SiteBundle, UiBundle and SocialBundle read, 101 of them against 31 before - instead of the shape tokens alone (29/07/2026)
- It ships entirely commented out, at the bundle's own defaults: a fresh site freezes nothing, so a later change to a default reaches it, and the file's active lines are exactly what its design decides (29/07/2026)
- Added `ScaffoldThemeTest`, locking that copy against the compiled `:root` - every declared token offered, every offered value current, nothing left active (29/07/2026)
- Removed `--button-background-secondary-light`, the only token of the `:root` no stylesheet ever read - its `-primary-light` counterpart backs the slider's credits strip and a page-section rule, this one backed nothing (29/07/2026) [BC-Break]
- Recompiled `public/css/emails.css`, which had gone stale on `--footer-margin-top` (29/07/2026)
- Removed the page-template mechanism entirely: `TemplateProviderInterface`, `TemplateRegistry`, `SiteTemplateProvider`, `TemplateApplier`, its `c975l.template_provider` compiler pass, the three shipped `config/templates/*.json` arrangements, `PageCrudController::applyTemplate()` and its "Templates" action group (28/07/2026) [BC-Break]
- Removed the `c975l:site:templates:apply` command, the only real use of the mechanism, and one no site had scripted (28/07/2026) [BC-Break]
- A "template" here was only ever a snapshot of example content copied once into Blocks, with no relation kept afterwards: it read as a maintained source of truth while being none, for a page structure a site composes once in its life. "Duplicate" covers a page meant to look like an existing one (28/07/2026)
- `Page::$replaces` and "Publish as replacement" are untouched, both having stopped depending on `applyTemplate()` before this (28/07/2026)
- The scaffolded `assets/styles/themes/theme.css`, now the only starting point a new site gets for its theme, has its three flat neutrals tinted with the site's own colors: `--footer-border` and `--footer-link-hover-background` are mixed out of `--footer-background`, and `--section-bg-muted` out of `--primary` in place of `--surface-alt`'s grey (28/07/2026)
- Being under `scaffold/assets`, that file is only ever written on a first install (`ScaffoldInstaller` leaves an existing asset alone), so every site already running keeps its own theme untouched (28/07/2026)
- `ThemeVariablesCssListener` no longer mentions "an admin applying a preset", the theme-preset mechanism it referred to having been removed from ConfigBundle (28/07/2026)

- Added `Page::$options`, one JSON payload for the page's benign display options (same reasoning as UiBundle's `Block::$data`): every option added from now on is a code change alone, with no schema migration for the apps running this bundle to replay (28/07/2026)
- Added `Page::getOptions()`/`setOptions()`/`getOption()`/`setOption()`, and its first option: `isTitleDisplayed()`/`setIsTitleDisplayed()`, letting an editor drop the layout's own `<h1>` on a page opened by a block already carrying one (28/07/2026)
- `PageCrudController` offers it right under the slug, and carries the whole payload over when a page is duplicated (28/07/2026)
- Added `PageTest`, covering the option accessors, their defaults and the property path EasyAdmin and the layout read them through (28/07/2026)
- `ContentQualityClient` now returns `h1Count` in place of `hasH1`, the check having no way to see a page carrying several `<h1>` until now (28/07/2026) [BC-Break]
- The `content-quality` check reports a page holding several `<h1>` as a warning, valid HTML but as many top-level subjects announced for one page (28/07/2026)
- `PageHealthCheckAdviceBuilder` advises on that case, and still reads the `hasH1` detail of the rows persisted before (28/07/2026)
- Added `--footer-margin-top`, the gap before the footer band being hardcoded while every other footer property already read a token (28/07/2026)
- The scaffolded `assets/styles/themes/theme.css` restates it at its default value (28/07/2026)
- A published page no longer renders the empty `.container` holding the preview alert, its own bottom margin and padding opening a gap between the navbar and the page's first block (28/07/2026)

## v7.10.1

- Replaced ids by hash in translations (27/07/2026)

## v7.10.0

- Removed the theme preset catalog, `SiteThemePresetProvider`, `config/themes/`, `sass/themes/` and the compiled `public/css/themes/` alike (27/07/2026) [BC-Break]
- Removed the `theme-stylesheet` config key, along with its `label.`/`description.` translations (27/07/2026) [BC-Break]
- `StylesheetProvider` no longer takes a `ConfigServiceInterface`, having no config left to read (27/07/2026) [BC-Break]
- Removed `PageController`'s `?preset=<slug>` preview, along with `label.preview_preset_mode` (27/07/2026) [BC-Break]
- The scaffolded `assets/styles/themes/theme.css` now restates every shape token at its default value (27/07/2026)
- Added `--navbar-width`/`--navbar-margin-x`, the navbar's full-bleed being hardcoded in `.menu` while the footer already read the same pair from tokens (27/07/2026)
- Added `--navbar-btn-background`/`-background-hover`/`-color`, the "primary" nav item's pill being invisible on a navbar painted with `--primary` (27/07/2026)
- Added `--navbar-site-name-color`/`--navbar-site-tagline-color`, both hardcoded to `--primary` until now (27/07/2026)
- Fixed the navbar tagline showing `--text` instead of its own color, `_typography.scss` painting the rich-text editor's `<div>` directly (27/07/2026)
- Added `--section-bg-muted`/`-primary`/`-dark`, the three flats a page section can be painted with (27/07/2026)
- `body` now carries `font-weight: var(--font-body-weight)`, the token being declared since 18/02/2024 but read by no rule (27/07/2026)
- Removed the scaffolded `assets/styles/_fonts.css` starter file, superseded by the font upload screen (27/07/2026) [BC-Break]
- Removed the `site-fonts-face-file` config key, along with `FontService`'s parsing of dev-declared `@font-face` (27/07/2026) [BC-Break]
- `FontService` now only offers the uploaded fonts, and no longer depends on `ConfigServiceInterface`/`kernel.project_dir` (27/07/2026) [BC-Break]
- `ThemeVariablesCssListener` now compiles only the `theme-`-prefixed slugs of the theme group, skipping the others instead of turning them into a variable no stylesheet reads (27/07/2026)
- Fixed a lesser admin silently stripping ROLE_SUPER_ADMIN off a user just by saving their record in `UserCrudController` (27/07/2026)
- `UserCrudController` gained an `AdminContextProvider` constructor argument, to know which user is being edited (27/07/2026) [BC-Break]
- `c975l:site:create` now also grants ROLE_EDITOR to the bootstrap user, no `role_hierarchy` being shipped to imply it from ROLE_ADMIN (27/07/2026)
- Added `UserCrudControllerTest`, covering the role choices and the frozen roles field (27/07/2026)
- Added `UserManagementVoter`, keeping a lesser admin off a super admin's account, edit and delete alike (27/07/2026)
- `UserCrudController` now hands that voter to EasyAdmin as its entity permission, in place of the `site-role-admin` role (27/07/2026)
- Added `ContentQualityAnalyzer`, the content-quality checks split off from `ContentQualityHealthCheckProvider` (27/07/2026)
- Added `DeclaredUrlsHealthCheckProvider`, running those same checks over another bundle's declared sitemap urls (27/07/2026)
- Added `DeclaredUrlsHealthCheckPass`, registering one provider per `SitemapProviderInterface` in the container (27/07/2026)
- Added `DeploymentHealthCheckProvider`, checking the http/https redirect and that an unknown url answers a real 404 (27/07/2026)
- Added `DeploymentClient` (27/07/2026)
- `content-quality` now checks the `<title>`'s presence and its 30-65 character length (27/07/2026)
- `content-quality` now checks the meta description's 50-160 character length, not just its presence (27/07/2026)
- `content-quality` now checks the `og:title`/`og:description`/`og:image` share tags (27/07/2026)
- `content-quality` now checks external links too, a dead one only ever a warning (27/07/2026)
- Link checks now send an identifying `User-Agent`, a bare library default being filtered by most big sites (27/07/2026)
- A link answering 403, 429 or LinkedIn's non-standard 999 is now inconclusive, instead of broken (27/07/2026)
- `PageHealthCheckAdviceBuilder` now covers the `deployment` and `urls-<bundle>` kinds (27/07/2026)
- Added the advice lines for the title, description, share tags and external link checks (27/07/2026)
- Fixed a W3C HTML message split into several parts rendering as "line 12: Array" (27/07/2026)
- The scaffolded `MaintenanceSchedule` now schedules the `deployment` and `urls-<bundle>` kinds (27/07/2026)
- `ContentQualityAnalyzer` now analyses urls in batches, instead of firing every request at once and holding every response (27/07/2026)
- A declared url no longer costs an existence HEAD of its own, its analysis response answering it (27/07/2026)
- `DeclaredUrlsHealthCheckPass` now skips abstract and synthetic definitions, which no container can reference (27/07/2026)
- `ScaffoldInstaller::themeImportReminder()` now also fires on a project wiring its assets from `app.js` alone (27/07/2026)
- Removed `ScaffoldInstaller::ensureThemeImport()`, a private method called by nothing (27/07/2026)
- Added a readme section on routing `RunCommandMessage`, for the dashboard's on-demand health check runs (27/07/2026)
- Fixed `UserManagementVoter` denying an account holding ROLE_SUPER_ADMIN without the `site-role-admin` role (27/07/2026)
- A declared url answering 404 is now an error, instead of being reported as not tested (27/07/2026) [BC-Break]
- A url answering 403 or 5xx is now an error too, instead of being reported as not tested (27/07/2026) [BC-Break]
- A url answering 410 is now a warning, deliberately removed but still declared (27/07/2026)
- Added the `label.health_check_url_gone` translation (27/07/2026)
- Added `UserManagementVoterTest` (27/07/2026)
- `c975l:site:create` no longer asks to confirm the password, which it echoes in clear text anyway (27/07/2026)
- `c975l:site:create` now looks for `src/Entity/User.php` on disk, autoloading the class freezing its pre-scaffold version for the whole process (27/07/2026)
- Added `BuildFileWriterTrait`, the atomic build-file write shared by `ThemeVariablesCssListener` and `FontCssListener` (27/07/2026)
- Split the longest methods of the commands, controllers, importers and services into named private helpers (27/07/2026)
- Added `DeploymentHealthCheckProviderTest`, `DeclaredUrlsHealthCheckProviderTest` and `DeclaredUrlsHealthCheckPassTest` (27/07/2026)
- Added `DeploymentClientTest` (27/07/2026)
- Added `SiteCreateCommandTest` (27/07/2026)
- Added a `W3cValidatorClientTest` case on a HTML message returned as an array (27/07/2026)
- Documented the per-row `UserManagementVoter` guard and the frozen roles field in the readme (27/07/2026)
- Documented wiring `theme.css` from `assets/app.js` in the readme, AssetMapper not merging CSS (27/07/2026)

## v7.9.1

- `isIndexable` is now shown as a switch in the pages list, next to `isPublished` (26/07/2026)

## v7.9.0

- Fixed the `viewport` meta missing its comma separator in `layout.html.twig` and `emails/fullLayout.html.twig` (26/07/2026)
- The email layout's `viewport` no longer locks the zoom (26/07/2026)
- `SitePageHealthCheckProvider` now analyses one page at a time, instead of firing every PageSpeed request at once (26/07/2026)
- The layout now preconnects to Matomo's own origin, without needing it in `site-preconnect` (26/07/2026)
- Added `PageDevProfilePathProvider`, declaring every published page to ConfigBundle's `c975l:dev-profile:run` (26/07/2026)
- Added `PagePublicUrlResolver::resolvePath()`, the local path `resolve()` already built, now reusable without `site-url` (26/07/2026)
- Added a readme section on the dev profile (26/07/2026)
- Documented that Matomo's origin is preconnected without being listed in `site-preconnect` (26/07/2026)
- Fixed `label.latest_update` being translated in the `messages` domain instead of `site` in the twelve legal model templates (26/07/2026)

## v7.8.1

- Merged `translations.en/fr/es.js` into a single `translations.js` (26/07/2026) [BC-Break]
- The navbar logo now carries its intrinsic `width`/`height` and a `?v=` cache-buster (26/07/2026)
- `_menu.scss` now sizes the navbar logo (26/07/2026)
- A `ul` inside a `.text-center` container drops its chevron marker (26/07/2026)
- An `ol` inside a `.text-center` container moves its numbers into the centered text flow (26/07/2026)
- `c975l:site:smoke-test` now skips a site left in maintenance (26/07/2026)
- `c975l:site:smoke-test` now lists the page failures when the home page references no asset (26/07/2026)
- `c975l/ui-bundle` is now required in `^1.10`, for `Media::getIntrinsicWidth()`/`getIntrinsicHeight()` (26/07/2026)
- Added `ConfigsJsonTest`, checking every `configs.json` entry has unique slug, expected keys and en/es/fr translations (26/07/2026)
- Added `TranslationsJsTest`, checking `translations.js` carries every shipped locale with the same keys (26/07/2026)

## v7.8.0

- Added `c975l:site:smoke-test`/`SmokeTestClient`, checking every published page and the home page's css/js assets answer 200 (26/07/2026)
- `c975l:site:smoke-test` takes a `--pages-only` option, skipping the asset pass (26/07/2026)
- `SitePageSitemapProvider` now passes `Page::$priority` as-is on its 0-10 scale, `SitemapWriter` doing the conversion to the protocol's 0.0-1.0 (26/07/2026)
- Added `PageBlockLocator`, tracing an image/link found in a page's rendered HTML back to the block that produced it (26/07/2026)
- Content-quality advice lines now list each offending image/link individually, linking to its own block (26/07/2026)
- `PageHealthCheckAdviceBuilder::buildAdvice()` is now keyed by ConfigBundle's `HealthCheckAdviceBuilder::key()` instead of by kind (26/07/2026) [BC-Break]
- Fixed every page's content-quality row showing the last checked page's advice (26/07/2026)
- `W3cCssHealthCheckProvider` now counts the warnings the validator's CSS3 profile predates apart, as benign (26/07/2026)
- Fixed a W3C CSS warning whose `message` comes back as an array rendering as "Array" (26/07/2026)
- Removed the `::-moz-focus-inner`/`:-moz-focusring` pair from `_normalize.scss`, a no-op costing eight W3C validator warnings (26/07/2026)
- Fixed `.btn-secondary`'s label being repainted by `a.btn`/`a:hover`, dropping its contrast to 3.5:1 (26/07/2026)
- `menu_link` now points at the site root for the "home" page instead of costing a 301 hop on every click (26/07/2026)
- `layout.html.twig`'s `robots` meta tag is now overridable per template, and `page.html.twig` sets it from `Page::isIndexable()` (26/07/2026)
- Added a `fontPreload` block in `layout.html.twig`, emitting the preloads before the stylesheets (26/07/2026)
- Added the `sitemap-fields` Stimulus controller, locking a non-indexable page's sitemap fields (26/07/2026)
- `isIndexable` is now carried by page export/import, and `DefaultPagesImporter` seeds the account-related pages as non-indexable (26/07/2026)
- Fixed `clonePage()` dropping `isIndexable`, putting a duplicated noindex page back in the sitemap (26/07/2026)
- `ContentQualityClient::readLinkCheck()` now returns a `LINK_*` verdict string instead of a `bool`, and `isLinkBroken()` only reports a conclusive 4xx/5xx (26/07/2026) [BC-Break]
- `ContentQualityHealthCheckProvider`'s `brokenLinks` detail now carries each link's own url and location instead of a bare url string (26/07/2026) [BC-Break]
- `font_preloads()` is now cached, dropped by `FontCssListener`/`ThemeVariablesCssListener` - it re-queried every font family on every front-end render (26/07/2026)
- Fixed `PageBlockLocator` matching an image against a block holding a filename that merely shares its prefix (`photo-1` vs `photo-11`) (26/07/2026)
- Fixed the sitemap fields being wiped on every save of a non-indexable page - `changeFrequency`/`priority` are now locked read-only, not disabled (26/07/2026)
- Added `Page::$isIndexable`, excluding a page from `sitemap-site.xml` and adding its `robots` meta tag (26/07/2026) [DB-Migration]
- Sitemap generation moved to ConfigBundle: `c975l:site:sitemaps:create` is replaced by `c975l:sitemaps:create`, collecting every bundle's `SitemapProviderInterface` (26/07/2026) [BC-Break]
- Added `SitePageSitemapProvider`, SiteBundle's own contribution to that sitemap (the database pages) (26/07/2026)
- `PagePublicUrlResolver` now builds `/pages/{slug}` without a trailing slash, the canonical form shared with the sitemap (26/07/2026)
- Moved `sitemap.xml.twig`/`sitemap-index.xml.twig` and the "Regenerate sitemap" dashboard shortcut to ConfigBundle (26/07/2026) [BC-Break]
- Removed the scaffold's `App\Command\SitemapCreateCommand`; the scheduler now runs `c975l:sitemaps:create` directly (26/07/2026)
- Removed `SitemapCreateCommand::getChangeFrequency()`/`getPriority()`, dead since pages moved to the database (26/07/2026)
- Fixed `SiteShortcutController` importing the scaffold's `App\Command\SitemapCreateCommand` from bundle code (26/07/2026)
- Fixed the readme's scheduler example missing the `c975l:` prefix on every command (26/07/2026)
- Documented that file-based pages aren't declared in the sitemap, only database pages are (26/07/2026)
- Added `FontPreloadExtension`/`font_preloads()`, emitting `<link rel="preload">` for the theme's own uploaded fonts (26/07/2026)
- The navbar logo is now `loading="eager" fetchpriority="high"` instead of `loading="lazy"` (26/07/2026)
- Fixed the Matomo controller building a `//matomo.js` URL when `site-matomo-url` ends with a slash (26/07/2026)
- Added `label.cookies_dialog`, giving the cookie banner's `role="dialog"` an accessible name (26/07/2026)
- `vanilla-cookieconsent` is now served from the bundle instead of jsDelivr (26/07/2026)
- Fixed `a.btn` overriding `.btn-secondary`'s and `.btn-link`'s own text color in `emails.css` (26/07/2026)
- Renamed the `cookieConsent`/`collectionItemSort` Stimulus controllers to `cookie-consent`/`collection-item-sort` (26/07/2026)
- Removed the dead `templates/emails/emails.min.css`, emails inline `public/css/emails.min.css` (26/07/2026)

## v7.7.5

- Page's own blocks now render full-width outside `layout.html.twig`'s `.container`, instead of constrained inside it (24/07/2026)
- Fixed the active menu item's background color never applying, invalid `rgba()` usage on `--primary` (24/07/2026)

## v7.7.4

- `BlockDataExporter`/`BlockDataImporter` now carry a PDF's `.webp` thumbnail alongside it in Sync exports/imports, reused as-is via `Media::$importedThumbnailPath` instead of regenerating it with Ghostscript (24/07/2026)

## v7.7.3

- Fixed `BlockDataExporter`/`BlockDataImporter` silently dropping a Media's `name` from Sync exports/imports
- Added `FontBulkImportController`, letting an admin upload several font files at once from the Font list
- Added `FontFilenameParser`, guessing each font's name/weight/style from its filename

## v7.7.2

- Fixed `CollectionItemImportProvider` failing to import items into a newly created collection (24/07/2026)

## v7.7.1

- Added `ImportmapProvider`, declaring `controllers-admin.js`/`controllers.js`'s importmap.php entries for ConfigBundle's `c975l:config:check-importmap` (24/07/2026)

## v7.7

- Fixed the cookie consent banner showing the same text twice, once as title and once as description (24/07/2026)
- Added `PageEditUrlResolver`, so PageSpeed Insights/W3C/content-quality/mixed-content health check rows also link to the page's own edit screen, alongside its public url (24/07/2026)
- The weekly `c975l:health-check:run` scheduler entry now also covers the `ssl-certificate`/`mixed-content`/`seo-files`/`redirect-chains` kinds, previously left out (24/07/2026)
- The dashboard guided tour's sidebar entries now reuse each screen's own `crud/index`/`crud/edit` override explanatory text as their `description`, instead of showing title-only (24/07/2026)
- `PageHealthCheckAdviceBuilder` now logs a warning for a health check kind it has no advice mapped for, instead of staying silent (24/07/2026)
- Extracted `HealthCheckErrorRowTrait`, shared by every `HealthCheckProviderInterface` implementation's "the check itself failed" row (24/07/2026)
- `SitePageHealthCheckProvider` now skips a not-yet-deployed page instead of spending a PageSpeed Insights quota call on it, same `PageExistenceChecker` guard as the W3C/content-quality providers (24/07/2026)
- `W3cHtmlHealthCheckProvider`/`W3cCssHealthCheckProvider`/`ContentQualityHealthCheckProvider` now run every page's request concurrently instead of serially, same `request()`/`read()` pattern as `SitePageHealthCheckProvider`'s own PageSpeed calls (24/07/2026)
- Page not found rows (W3C, content-quality) now use `HealthCheckResult::STATUS_SKIPPED` instead of a warning, and a shorter "Page not found (not tested)" summary (24/07/2026)
- Split `W3cHealthCheckProvider` into `W3cHtmlHealthCheckProvider`/`W3cCssHealthCheckProvider` (kinds `w3c-html`/`w3c-css`), each its own row instead of one combined HTML+CSS row (24/07/2026)
- Extracted `AbstractW3cValidationHealthCheckProvider`, shared by the two W3C providers (24/07/2026)
- `PageHealthCheckAdviceBuilder` advice lines now carry an optional direct link to the external validator's own report for the page (W3C, PageSpeed Insights, securityheaders.com) (24/07/2026)
- The Page edit screen's Health check tab no longer shows the table's search/status/kind filter bar - a single page only ever has a handful of rows (24/07/2026)
- Moved the QR code out from below the whole tabbed form into the "Data" tab, where it belongs (24/07/2026)
- Added `PageQrCodeType`, backing the QR code's new tab-scoped rendering (24/07/2026)
- `W3cHealthCheckProvider` now checks a page exists (single `HEAD` request) before calling the W3C validators (24/07/2026)
- Removed `WaveHealthCheckProvider`/`WaveApiClient` and its monthly cron entry (24/07/2026)
- Extracted `PageExistenceChecker`, shared by `W3cHealthCheckProvider` and `ContentQualityHealthCheckProvider` (24/07/2026)
- `ContentQualityHealthCheckProvider` now checks each page exists before analyzing it, same as `W3cHealthCheckProvider` (24/07/2026)
- `SecurityHeadersHealthCheckProvider` now checks the homepage only (24/07/2026)
- `ContentQualityHealthCheckProvider`'s details now carry `hasDescription`/`hasH1` alongside `imagesWithoutAlt`/`brokenLinks` (24/07/2026)
- Added a "Health check" tab on the Page edit screen, showing that page's own results (reusing ConfigBundle's shared table/gauges) plus short actionable advice per issue found (24/07/2026)
- Added `PageHealthCheckAdviceBuilder`/`PageHealthCheckExtension`/`PageHealthCheckPanelType` backing the new tab (24/07/2026)
- Wrapped the Page edit form's existing fields into a "Data" tab, alongside the new "Health check" one (24/07/2026)
- Documented that `c975l:health-check:run` must run against production's own database, same constraint as `sitemaps:create`/`backup` (24/07/2026)
- Added `ContentQualityHealthCheckProvider`/`ContentQualityClient`, checking each published page for a missing meta description, missing `<h1>`, images without `alt`, and broken internal links (24/07/2026)
- Added the free `content-quality` kind to the weekly Health check scheduler entry (24/07/2026)
- `PagePublicUrlResolver` now builds each page's path through the real router instead of a hand-built string (24/07/2026)
- `PageSpeedInsightsClient`/`SitePageHealthCheckProvider` now run every page's PageSpeed Insights request concurrently instead of serially (24/07/2026)
- Fixed the cookie consent banner's `data-*-value` attributes never matching its `cookieConsent` Stimulus controller (24/07/2026)
- Extracted `PagePublicUrlResolver`, shared by every `HealthCheckProviderInterface` implementation (23/07/2026)
- Added `HealthCheckRunner`/`c975l:health-check:run --kind=` (23/07/2026)
- Added `SecurityHeadersHealthCheckProvider`, checking each published page's HTTP response headers (23/07/2026)
- Added `W3cHealthCheckProvider`, validating each published page's HTML/CSS markup (23/07/2026)
- Added `WaveHealthCheckProvider`, running WebAIM's WAVE accessibility scan against every published page (23/07/2026)
- Added `SiteBlockOwnerResolver` for UiBundle's block-move feature (23/07/2026)
- `PageCrudController`/`MenuCrudController` now expose their "blocks" field to UiBundle's drag-to-a-different-section move (23/07/2026)
- Added `CollectionGroup` entity/`CollectionCrudController` (23/07/2026)
- `CollectionItem`'s free-text `group` is now a required `CollectionGroup` relation - see UPGRADE.md (23/07/2026) [BC-Break] [DB-Migration]
- `CollectionItemCrudController` now requires browsing into a collection instead of a free-text group (23/07/2026)
- Expanded the explanatory text on the Collection/Collection item screens (23/07/2026)
- `CollectionItemImportProvider`/`CollectionItemImportCommand` now auto-create the referenced collection if missing (23/07/2026)
- Extracted `CollectionGroupResolver`, shared by `CollectionItemImportProvider`/`CollectionItemImportCommand` (24/07/2026)
- Added `SitePageHealthCheckProvider`/`PageSpeedInsightsClient`, feeding ConfigBundle's "Health check" dashboard with PageSpeed Insights scores (23/07/2026)
- Added a weekly `c975l:health-check:run` entry to the maintenance scheduler (23/07/2026)
- Fixed `PageSpeedInsightsClient` raising an unclear error when no API key is configured (23/07/2026)
- A missing PageSpeed Insights API key now shows as a warning on the Health check page and as a dashboard alert (23/07/2026)
- Added `CollectionItemExportProvider`/`CollectionItemImportProvider`, plugging Collection items into ConfigBundle's "Sync" content export/import (23/07/2026)
- Fixed `PageExportProvider`/`PageImportProvider` silently dropping a container block's nested slots (23/07/2026)
- Fixed `PageExportProvider`/`PageImportProvider` not carrying a block's entrance `animation` through Sync exports/imports (23/07/2026)
- Added `SiteGraphicExportProvider`/`SiteGraphicImportProvider`, plugging site graphics into ConfigBundle's "Sync" content export/import (23/07/2026)
- Fixed `PageExportProvider`/`PageImportProvider` silently dropping a Page's own `summarySocialNetwork` and `ogImage` from Sync exports/imports (23/07/2026)
- Extracted `BlockDataExporter`/`BlockDataImporter` out of `PageExportProvider`/`PageImportProvider` (23/07/2026)
- Added `RedirectExportProvider`/`RedirectImportProvider`, plugging Redirects into ConfigBundle's "Sync" content export/import (23/07/2026)
- Added `MenuExportProvider`/`MenuImportProvider`, plugging Menus into ConfigBundle's "Sync" content export/import (23/07/2026)
- Added `SiteEssentialActionProvider`, contributing a Pages/Menus/Fonts setup checklist to ConfigBundle's dashboard "essential actions" (23/07/2026)
- Added `PageExportProvider`, plugging Pages into ConfigBundle's "Export sync (everything)" dashboard shortcut (23/07/2026)
- Added `FontExportProvider`, plugging Fonts into ConfigBundle's "Export sync (everything)" dashboard shortcut (23/07/2026)
- Redirect/Site graphics/Menu/Font/Form/Email template menu entries are now tagged `tier: advanced` (23/07/2026)
- SiteShortcutProvider's dashboard shortcuts are now tagged with a `ShortcutProviderInterface::CATEGORY_*` (23/07/2026)
- Site name in the navbar now links to the homepage (23/07/2026)
- Renamed the `twig_content` block's category from `label.category_migration` to `label.category_twig` (23/07/2026)
- Fixed README's `routes.yaml` example pointing at `Controller/` instead of `src/Controller/` (23/07/2026)
- Added `SslCertificateHealthCheckProvider`/`SslCertificateClient`, checking the site's TLS certificate expiry (24/07/2026)
- Added `MixedContentHealthCheckProvider`/`MixedContentClient`, flagging http:// resources loaded from an https:// page (24/07/2026)
- Added `SeoFilesHealthCheckProvider`/`SeoFilesClient`, checking robots.txt/sitemap-site.xml are reachable and sane (24/07/2026)
- Added `RedirectChainHealthCheckProvider`, detecting redirect chains/loops from the `Redirect` entity (24/07/2026)
- `PageHealthCheckAdviceBuilder` now advises on an expiring TLS certificate and on mixed-content resources (24/07/2026)

## v7.6.8

- Expanded the explanatory text on the Page/Redirect/Menu/Site graphics/User/Font/Collection item index and edit screens (22/07/2026)
- Removed the detail/view page on Redirect, Menu, Site graphics, Font and Collection item (22/07/2026)
- Added a Cancel action on every create/edit screen (22/07/2026)
- Added `Font` entity/`FontCrudController`, letting an admin upload their own TTF/WOFF/WOFF2 font files (22/07/2026)
- Added `FontCssListener`, compiling uploaded fonts into `public/bundles/build/site-fonts-uploaded.css` (22/07/2026)
- `FontService` now also offers admin-uploaded font names alongside the dev-declared ones (22/07/2026)
- Added `FontService`, exposing `@font-face` font-family names to ConfigBundle's `font`-kind config fields (21/07/2026)
- Added `site-fonts-face-file` config key, pointing to the CSS file declaring `@font-face` fonts (21/07/2026)
- `theme-font-family-title`/`-body`/`-accent` are no longer `restricted` - any `ROLE_ADMIN` can edit them (21/07/2026)
- `ThemeVariablesCssListener` now appends a generic font fallback (`sans-serif`/`monospace`) to a bare custom font name (21/07/2026)
- Added a scaffolded `assets/styles/_fonts.css` starter file with an example `@font-face` declaration (21/07/2026)
- Page/Font "Export selection" now works in production, no longer dev-only (22/07/2026)
- Lowered the Page/Font "Export selection" permission from `site-role-editor` to `site-role-admin` (22/07/2026)

## v7.6.7

- Added `ProcedureProvider`, contributing admin help procedures to ConfigBundle's dashboard AI assistant
- Added `--navbar-background-scrolled`/`--navbar-text-scrolled` CSS variables and `.menu.is-scrolled` styling
- Fixed menu toggle icon color to follow `--navbar-text` instead of `--primary`

## v7.6.6

- `ThemeVariablesCssListener` now throws on write failure instead of failing silently (20/07/2026)

## v7.6.5

- Corrected scaffold files (20/07/2026)
- Cleaned ContactForm references (20/07/2026)

## v7.6.4

- Modified scaffold SitemapCreateCommand as specific files not needed anymore (20/07/2026)

## v7.6.3

- Harmonized SQL exports (20/07/2026)

## v7.6.2

- Added `MenuLinkType`'s `primary` checkbox for filled-button menu links (20/07/2026)
- Fixed navbar `.menu-link:hover`/`:focus` not changing text color (20/07/2026)
- `BackupCommand` dropped the `--full` option - see UPGRADE.md (20/07/2026) [BC-Break]
- Fixed `BackupCommand::dumpTable()`'s FK constraint order and `_archives` table handling (20/07/2026)
- `BackupCommand`'s complete file backup now also re-runs periodically (20/07/2026)
- Added `SessionNonceGenerator` for a stable per-session CSP nonce (20/07/2026)
- Added `EmailLayoutProvider` for branded email preview/send (20/07/2026)
- Added info paragraphs to the User CRUD index (20/07/2026)
- Restored `GalleryShowcaseProvider` (20/07/2026)
- `EmailVerifier`/`UserRegistrar` now send the registration confirmation email through `EmailService` - see UPGRADE.md (20/07/2026) [BC-Break]
- Fixed `ExportTablesCommand` truncating `site_config` on export (20/07/2026)

## v7.6.1

- Corrected dependency (20/07/2026)

## v7.6

- Added "Email templates" admin menu entry (19/07/2026)
- Fixed `SiteMediaUsageProvider`/`SiteBlockEditUrlProvider` duplicating the same block-focus URL building, now shared via `BlockFocusUrlTrait` (19/07/2026)
- Fixed `security/login.html.twig` still showing the sign-in form below the "already logged in" notice instead of hiding it (19/07/2026)
- Removed `app_logout` from scaffold's `LinkableRouteProvider` linkable routes (19/07/2026)
- Fixed `ArticleBlockCacheInvalidationListener`/`MenuCacheInvalidationListener` duplicating the same Doctrine listener skeleton, now shared via `AbstractBlockCacheInvalidationListener` (19/07/2026)
- Fixed `MenuExtension`'s "page:ID(#fragment)" target-parsing duplicated across 4 methods, now shared via `parseTarget()` (19/07/2026)
- Fixed `CollectionItemSourceProvider::detail()` re-deriving fields already computed by `toCollectionItemModel()` (19/07/2026)
- Fixed `EmailVerifier`'s `getId()` duck-typing check repeated 3x, now a shared `getUserId()` helper (19/07/2026)
- "register"'s CGU checkbox links to the site's real terms-of-use page again, via UiBundle's new `FormField::$url` - see UPGRADE.md (19/07/2026)
- "register"/"reset_password_request" Forms now show an "already logged in" notice instead of the form to an authenticated visitor, via UiBundle's `RequiresAnonymousInterface` (19/07/2026)
- Moved `scaffold/templates/registration/confirmation_email.html.twig`/`reset_password/email.html.twig` to bundle-owned `templates/emails/confirmation_email.html.twig`/`reset_password_email.html.twig`, same as `contact_notification.html.twig` - see UPGRADE.md (19/07/2026) [BC-Break]
- Fixed `security/login.html.twig`'s "forgot password"/"create account" links pointing at the removed `app_register`/`app_forgot_password_request` routes - see UPGRADE.md (19/07/2026) [BC-Break]
- Unified register/reset-password-request onto the generic "form" Block mechanism - see UPGRADE.md (19/07/2026) [BC-Break]
- Removed `user-registration-enabled` config, replaced by `Form::$enabled` - see UPGRADE.md (19/07/2026) [BC-Break]
- Renamed `findOneByBlockKind()`/`site_page_for_block()` to `findOneByFormBlockName()`/`site_page_for_form_block()` - see UPGRADE.md (19/07/2026) [BC-Break]
- Rate limiting on register/reset-password-request now uses the shared `limiter.ui_form` - see UPGRADE.md (19/07/2026) [BC-Break]
- Renamed `CollectionEntry` to `CollectionItem` - see UPGRADE.md (19/07/2026) [BC-Break] [DB-Migration]
- `CollectionItemCrudController` index is now a two-step group-then-items screen (19/07/2026)
- Fixed `DefaultPagesImporter` never backfilling the "contact" Form/EmailTemplate on existing sites (19/07/2026)
- `c975l:site:create` now also runs `c975l:ui:form-field-template:import-defaults` (19/07/2026)
- Removed `register.html.twig`/`reset_password/request.html.twig` templates - see UPGRADE.md (19/07/2026) [BC-Break]
- Added `PageRepository::findOneByBlockKind()`/`site_page_for_block()` Twig function (19/07/2026)
- Fixed "register" Block showing blank instead of a "registration is not open" notice (19/07/2026)
- Added `SiteBlockEditUrlProvider` for UiBundle's front-end "Edit this block" button (19/07/2026)
- Added `EmailVerifier`/`UserRegistrar`/`PasswordResetter` bundle services, moved out of scaffold - see UPGRADE.md (18/07/2026) [BC-Break]
- Added `register`/`reset_password_request` Block kinds - see UPGRADE.md (19/07/2026)
- Added "Forms" admin menu entry (19/07/2026)
- `DefaultPagesImporter` now also seeds "register"/"forgot password"/"contact" pages - see UPGRADE.md (19/07/2026)
- Menu rendering now eager-joins and caches blocks instead of querying per link/menu (19/07/2026)
- Page's "Summary for social networks" field now supports UiBundle's AI rephrase button (19/07/2026)
- Moved `FormBotProtection` to UiBundle, shared with its `form` Block - see UPGRADE.md (19/07/2026) [BC-Break]
- Fixed "Publish as replacement" never showing on the page edit screen (19/07/2026)
- Fixed title-change confirmation modal showing an empty message (Stimulus controller id casing) (19/07/2026)
- Added optional `label` field to `menu_link` (19/07/2026)
- Fixed in-page anchor links losing their `#...` on click (Turbo Drive re-visit) (19/07/2026)
- Removed `RegistrationFormType`/`ResetPasswordRequestFormType`, now built from UiBundle `Form` rows - see UPGRADE.md (19/07/2026) [BC-Break]
- Moved `DnsEmail`/`DnsEmailValidator` to UiBundle - see UPGRADE.md (19/07/2026) [BC-Break]
- Added `password`/`password_repeated`/`url`/`tel`/`number`/`date` field types to `FormField` (19/07/2026)
- Fixed required checkbox fields silently accepting an unchecked box (19/07/2026)
- Fixed `FormFieldNamer` silently renaming a restricted field's stable key on label edit (19/07/2026)
- Fixed `label.accept_tou` English translation using the wrong placeholder (19/07/2026)
- Removed `menu_link`'s `asCopyright` checkbox, replaced by `site-menu-link-copyright-auto` config - see UPGRADE.md (19/07/2026) [BC-Break]
- Fixed footer copyright notice being inconsistent with/without a "Copyright" menu link (19/07/2026)
- `DefaultPagesImporter` now also seeds `contact_notification`/`account_validation`/`password_reset` EmailTemplate rows (19/07/2026)
- Emails now compose via `email_template_body()` and share one layout, editable from admin - see UPGRADE.md (19/07/2026) [BC-Break]

## v7.5.3

- Renamed `site-navbar-fixed` (`bool`) config key to `site-navbar-position` (`text`, free CSS position value: relative/sticky/fixed/static...) (17/07/2026) [BC-Break]
- Navbar now bleeds full-viewport-width like the footer already did (17/07/2026)
- `menu_link` targets can now point at a page's own in-page anchor (`page:ID#anchor-blockId`), listed right under that page's entry in `MenuLinkType`'s picker (17/07/2026)
- `menu_link` targets can now be an unpublished page too, flagged "(draft)" in the picker instead of being filtered out (17/07/2026)
- "Publish as replacement" is now a per-page action group listing every other page as a target, no longer limited to a template-created draft's own pre-filled target; removed from the index/detail row actions, edit screen only (17/07/2026) [BC-Break]
- `CollectionEntry` now has its own `slug`, unique within its `group`, auto-filled/de-duplicated by `CollectionEntryCrudController` (17/07/2026) [DB-Migration]
- `CollectionEntrySourceProvider` now implements `detail`, so the `collection` block can link an item straight to its own detail page (17/07/2026)
- `PageController::home()` now sets the `page` request attribute so a `collection` block on the home page can still resolve its items' detail links (17/07/2026)
- Removed `GalleryShowcaseProvider`: SiteBundle no longer contributes a `articles_slider`/`menu_link` showcase to UiBundle's block gallery (17/07/2026) [BC-Break]
- Fixed `c975l:site:collection-entry:import` never setting the entry's new required `slug`, crashing on flush (17/07/2026)
- Fixed drag-and-drop reorder corrupting a group's order once it spans more than one index page, by keeping `CollectionEntry`'s index on a single page (17/07/2026)
- Fixed an anchored `menu_link` rendering a blank label instead of falling back to the page's own title when its target block's title/anchor were both since cleared (17/07/2026)

## v7.5.2

- Footer's full-bleed width/margin and border are now `--footer-width`/`--footer-margin-x`/`--footer-border` (defaults unchanged) instead of hardcoded, so a plain/minimal footer template can realign it with the rest of the page instead of overriding raw CSS (17/07/2026)
- Added `site_copyright()` Twig function, replacing the "© firstYear - currentYear" logic duplicated in `layout.html.twig`/`emails/fullLayout.html.twig` - keeps both languages' own punctuation before the site name (French/Spanish's space before ":", English's none) instead of picking one (17/07/2026)
- A `menu_link` block can now be flagged "asCopyright" (`MenuLinkType`) to show the live computed copyright as its label instead of its target page's title - lets a footer's "Copyright" page link double as the copyright notice instead of showing both side by side (17/07/2026)
- Added `--footer-link-hover-background` (defaults to today's `rgba(0, 0, 0, .1)`) - `.menu-link:hover`'s background wasn't scoped to the navbar dropdown it was meant for and was leaking into the footer; a plain/minimal footer template now sets it to `transparent` instead (17/07/2026)
- `site_copyright()` now also collapses to a single year in emails when `site-first-online-date` is unset or matches the current year, instead of always showing a redundant range there (17/07/2026)
- `site-navbar-position` now only accepts `relative`/`sticky`/`fixed`/`static`/`absolute`, any other value is ignored instead of being inlined verbatim into the navbar's `style` attribute (17/07/2026)

## v7.5.1

- Renamed "page templates" to "templates", decoupled from theme presets (see UPGRADE) [BC-Break]
- Theme presets no longer reference a template; `?preset=` preview only shows the shape now [BC-Break]
- Renamed `agency-home-warm` template to `agency-home`, `portfolio-blueprint` to `portfolio-showcase` [BC-Break]
- Added a generic `default` template
- Scaffold now also ships an editable `assets/styles/themes/theme.css`
- `c975l:scaffold:install`/`c975l:site:create` remind you to wire its `@import` into `app.css` yourself
- Removed the `warm`/`blueprint` theme presets (see UPGRADE) [BC-Break]
- Renamed `sass/page-templates/` to `sass/themes/` [BC-Break]

## v7.5

- Index-page inline row actions now icon-only with hover-title label (16/07/2026)
- Theme presets simplified to shape only, colors/fonts removed; `warm-artisan` renamed to `warm` (16/07/2026) [BC-Break]
- Added `blueprint` theme preset and a per-preset preview action (16/07/2026)
- Added `portfolio-blueprint` page template, demoed by the `blueprint` preset's preview (16/07/2026)
- Applying a page template now creates a draft copy instead of editing the live page (16/07/2026) [BC-Break]
- Added `PageTemplateProviderInterface`/`PageTemplateRegistry` for bundle-contributed page templates (16/07/2026) [BC-Break]
- Fixed `articles_slider` cache invalidation; added `CollectionEntry` and its import command (16/07/2026)
- Added per-item detail pages for a `collection` block, rendered by a real `Page` referenced via its new `detailPage` field (16/07/2026)
- Replaced `agency-home-warm`'s real 975L copy with generic placeholder content (16/07/2026)
- Replaced cookie consent with `vanilla-cookieconsent` v3.1.0, gating `video_iframe` and rewriting the cookies legal copy (16/07/2026) [BC-Break]

## v7.4

- Added admin-editable theme (colors/fonts/light-dark mode) compiled to CSS custom properties by `ThemeVariablesCssListener`, inlined into emails via the new `theme_variables_css()` Twig function - replaces the old per-app `_user-variables.css`/`_user-typography.css` email override stubs, any app-level override of those two files stops applying (15/07/2026) [BC-Break]
- Added theme presets (`config/themes/*.json`, `SiteThemePresetProvider`), applicable from the dashboard and previewable per-page via `?preset=<slug>` before committing (15/07/2026)
- Added page templates (`config/page-templates/*.json`, `SitePageTemplateProvider`, `PageTemplateApplier`), applicable from a page's edit screen or via the new `c975l:site:pages:apply-template` command (15/07/2026)
- A theme preset's `?preset=` preview can now also demo its associated page template's block arrangement, not just its colors/fonts/shape (15/07/2026)
- Added page-template "shape" stylesheets (`sass/page-templates/*`, radii/shadows/nav/footer), activated via the `theme-stylesheet` config (15/07/2026)
- Added `|linkify` Twig filter, turning bare URLs in raw text into safe links (15/07/2026)
- Added registration/reset-password anti-spam protections: DNS-validated email, honeypot + minimum submit delay, optional GDPR consent checkbox, rate limiting (15/07/2026)
- Extracted the honeypot field and submission-timing check shared by the registration/reset-password scaffold into `FormBotProtection`, instead of duplicating that logic in each scaffolded Form/Controller (15/07/2026)
- `DnsEmail`'s DNS/MX lookup is now cached per domain (`cache.app`, 6h TTL) instead of hitting DNS on every validation, including every EasyAdmin edit of an existing user (15/07/2026)
- The optional GDPR consent checkbox now actually rejects an unchecked submission server-side (`Assert\IsTrue`) - `required => true` alone was HTML5-only and enforced nothing (15/07/2026) [BC-Break]
- `c975l:site:create` now also wires Symfony's `login_throttling` onto the "main" firewall (15/07/2026)
- Removed `apple-touch-icon.png`/`favicon.ico` from `BackupCommand`'s standard excludes, now Media rows rather than static files (15/07/2026)

## v7.3.6

- Modified view for Messenger messages in error (14/07/2026)
- Added test to trigger deprecations (14/07/2026)

## v7.3.5.1

- Corrected overflow-x for footer (14/07/2026)

## v7.3.5

- Suppressed DependencyInjection as not needed (14/07/2026)

## v7.3.4

- Added scaffold files  (14/07/2026)
- Corrected emails/fullLayout.html.twig (14/07/2026)
- Corrected scaffold/RegistrationController (14/07/2026)

## v7.3.3

- Added LinkableRouteProvider to scaffold to be able to be used in MenuLink Block (14/07/2026)

## v7.3.2

- Added `--button-background-success`/`-danger` styles (13/07/2026)
- Added gallery showcases for "articles_slider" and "menu_link" blocks (13/07/2026)
- Corrected Backup Command (13/07/2026)
- Added help text to the Menu CRUD index (13/07/2026)
- Added editable email texts and legal mentions Config entries (13/07/2026)
- Added `email-header` Menu location, mirroring `email-footer` (13/07/2026)
- Added Command to purge old messenger_messages and alert on failures (13/07/2026)
- Added `.btn-link` style (13/07/2026)
- Corrected undefined `--primary`/`--secondary` CSS variables (13/07/2026)

## v7.3.1

- Moved tests to the right place (13/07/2026)
- Added dependency to symfonycasts/reset-password-bundle (13/07/2026)

## v7.3

- Added duplication of page (12/07/2026)
- Suppressed Redirection to when a page is definitely suppressed (12/07/2026)
- Added `email-footer` Menu location so the email footer can be defined by the client from the backoffice, independently from the site footer (12/07/2026)
- Corrected `emails.scss` to compile `:root` variables directly instead of duplicating them by hand in `templates/emails/_variables.scss` (12/07/2026)
- Added tests (12/07/2026)
- Corrected scaffold/MaintenanceSchedule.php (12/07/2026)
- `Menu` (navbar/footer/email-footer) now owns a single sortable `blocks` collection, so menu links (a "menu_link" Block kind) and other blocks can be freely reordered together (12/07/2026) [BC-Break] [DB-Migration]
- Corrected logo in navbar (13/07/2026)

## v7.2.7

- Corrected footer margin to avoid horizontal scroll (11/07/2026)
- Added navbar display without menu (11/07/2026)

## v7.2.6.1

- Corrected xlf files (11/07/2026)

## v7.2.6

- Re-added block share in layout.html.twig a used by Twig templates to not display (11/07/2026)
- Added configs value used for display informations (11/07/2026)

## v7.2.5

- Updated What's new (11/07/2026)

## 7.2.4

- Modified exception when register is disabled (11/07/2026)

## v7.2.3

- Added ArticlesSlider ratio possibility (11/07/2026)

## v7.2.2

- Merged selector for MenuItem (11/07/2026)

## v7.2.1

- Modified role access (11/07/2026)
- Added placeholder for SociaShare (11/07/2026)
- Modified restriction on configs (11/07/2026)

## v7.2

- Added a Command to interactively create the new site (05/07/2026)
- Added Controllers redirections for wrong methods calls (09/07/2026)
- Modified Matomo component (09/07/2026)
- Added css class `legal` + sections in legal models (09/07/2026)
- Corrected Matomo component (09/07/2026)
- Renamed Page description to summarySocialNetwork (10/07/2026) [DB-Migration]
- Modified Dashboard sortcuts (10/07/2026)
- Re-ordered xlf files (10/07/2026)
- Modified ArticleSlider to use article.hook (10/07/2026)
- Added click on image to go to article for ArticlesSlider (10/07/2026)

## v7.1.7

- set `user-registration-enabled` to true, otherwise we can't create the first user (05/07/2026)
- Added scaffold folder for overriding src, templates, to be used in site, if needed (08/07/2026)
- Added translations to Twig Error templates (08/07/2026)
- Added possibility to upload user defined error images (08/07/2026)
- Added isPublished on default imported pages (08/07/2026)

## v7.1.6

- Corrected css for footer on mobile (05/07/2026)
- Added a config value to allow fixed navbar (05/07/2026)

## v7.1.5

- Added a Command to bulk export data from tables site_* + Shortcut on admin dashboard(05/07/2026)

## v7.1.4

- Moved favicon/apple-touch-icon/og-image/logo from plain config paths to site_media managed from a new "Site graphics" CRUD (05/07/2026)
- Added `ogImage` to Page, letting each page override the site's default og-image (05/07/2026)
- Added dashboard alerts (via ConfigBundle's new `AlertProviderInterface`) for site graphics not yet uploaded (05/07/2026)
- Added `SiteMediaUsageProvider` so UiBundle's Media library can show where a site graphic/page og-image/block media is used (05/07/2026)
- Made template pages/page.html.twig extend `@c975LSite/layout.html.twig` (05/07/2026)
- Added Menu/MenuItem entitys to manage the site's main menu (05/07/2026) [BC-Break]
- Taken Menu/Navbar/Footer components + sass from c975L/UiBundle and wired them (with Matomo/CookieConsent) directly into layout.html.twig's navigation/footer blocks, removing the `logoPrintOnly` block (05/07/2026) [BC-Break]
- Added tagline in layout (05/07/2026)
- Added config value to allow display of site name (05/07/2026)
- Added `site-preconnect` config to preconnect to external origins used by HostedBy/MadeBy/Matomo (05/07/2026)
- Added a "Regenerate sitemap" dashboard shortcut via ConfigBundle's new `ShortcutProviderInterface`, reusing `SitemapCreateCommand` (05/07/2026)

## v7.1.3

- Added the display of page description on page, before it was hidden (04/07/2026)
- Added a What's new file that will appear on main dashboard + menu (04/07/2026)
- Added a QrCode by page on the crud edit page (04/07/2026)
- Protected routes that were not in crud controllers (04/07/2026)

## v7.1.2

- Added config values (04/07/2026)
- Removed twig blocks in legal model as cannot be used anymore (04/07/2026)
- Translated (IA) legal model in english and spanish (04/07/2026)
- Added global info on CrudController (04/07/2026)

## v7.1.1

- Added Export dropdown (SQL/CSV/JSON) to Page, Redirect, and User CRUD controllers, using ConfigBundle's `TableExporter` (04/07/2026)
- Added severity on configs (04/07/2026)
- Added desciptions for Blocks (04/07/2026)
- Corrected Config Relation (04/07/2026)

## v7.1

- Suppressed home fallback on physical template (04/07/2026)
- Added component ArticleSlider (04/07/2026)
- Added config to allow/unallow user's registration (04/07/2026)
- Added UserCrudController (04/07/2026)
- Added `user-roles-available` config to manage selectable roles from the backoffice (04/07/2026)
- Added preview for pages not yet published (04/07/2026)

## v7.0

- Deleted Twig2MdCommand (28/06/2026)
- Removed page position as not needed (28/06/2026)
- Changed name of sitemap from sitemap-pages to sitemap-site (28/06/2026)
- Suppressed the discovery of physical templates, everything is in database [BC-Break] (28/06/2026)
- Added  `isDeleted` fields to `Page` entity (28/06/2026)
- Page deletion is now a soft-delete: row kept in DB, content cleared, URL returns 410 Gone (28/06/2026)
- Added Redirect sytem for global urls (28/06/2026)
- Transformed EasyAdmin action to import default pages to a Command (01/07/2026)
- Moved sass related to components to c975L/UiBundle (01/07/2026)
- Added possibility to delete/undelete pages (01/07/2026)

## v6.28.2

- Added ManyToMany relation page-blocks (27/06/2026)
- Added StyleSheetProvider to load stylesheets automatically (27/06/2026)
- Removed animations.css (27/06/2026)

## 6.28.1

- Put icons in their own folder (27/06/2026)
- Added a controller.js to register stimulus controllers (27/06/2026)
- Moved related controllers + sass to c975L/UiBundle (27/06/2026)
- Updated Readme (27/06/2026)

## v6.28

- Added priority and changeFrequency fields for pages (24/06/2026)
- Moved components to c975L/UiBundle (simply replace "twig:c975LSite" by "twig:c975LUi") (25/06/2026) [BC-Break]
- Changed the way src folders are exposed (26/06/2026)
- Suppressed Articles as they are a Block in c975L/UiBundle (26/06/2026)
- Renamed Services (26/06/2026)
- Suppressed bash scripts and replaced backup by a command (26/06/2026)
- Added Schedule Component require (26/06/2026)
- Added c975l:prefix for Command (26/06/2026)

## v6.27.4

- Added composer require for c975l/ui-bundle (24/06/2026)

## v6.27.3

- Renamed method in MenuProvider (22/06/2026)
- Corrected default configs (22/06/2026)
- Added translated messages (22/06/2026)

## v6.27.2

- Corrected SitemapCreateCommand

## v6.27.1

- Removed use of Fixtures to load default values and made use of ConfigBundle Command (22/06/2026)

## v6.27

- Added time for maintenance access (15/06/2026)
- Added join for article medias (18/06/2026)
- Corrected Sitemap command to include pages in database (18/06/2026)
- Moved Listener logic to CrudControllers (18/06/2026)
- Moved MaintenanceListener to c975L/ConfigBundle (22/06/2026)

## v6.26.2

- Added missing services.yaml file (12/06/2026)

## v6.26.1

- Added maintenance mode (11/06/2026)
- Corrected script for slider auto size (11/06/2026)

## v6.26

- Renamed/deleted services (06/06/2026)
- Added Pages/Articles management (06/06/2026) [Needs db migration]
- Added automatic slider (06/06/2026)
- Added WAI compatibility for slider (06/06/2026)

## v6.25.4

- Fixed toggle password visibility with Turbo compatibility (25/05/2026)

## v6.25.3.1

- Corrected javascript copyrights (16/05/2026)

## v6.25.3

- Added img-below + img-above css classes (04/05/2026)

## v6.25.2.1

- Replaced single quotes by double quotes (02/04/2026)

## v6.25.2

- Added toggle password (02/04/2026)
- Added password validation (02/04/2026)
- Added javascript translations (02/04/2026)
- Added touUrl config (02/04/2026)

## v6.25.1

- Suppressed W3C css validator errors (01/04/2026)

## v6.25

- Removed h1 from navbar as not recommended (31/03/2026)
- Added Twig Extension Nl2br to avoid use of `<br />` (31/03/2026)
- Removed button element as descendant of a element (31/03/2026)
- Removed width="auto" for Video components (25/03/2026)
- Corrected html in models (31/03/2026)

## v6.24.1

- Modified burger menu (25/03/2026)
- Added if to RichSnippet component (25/03/2026)
- Removed width="auto" for Images components (25/03/2026)
- Added Section component (25/03/2026)

## v6.24

- Added component RichSnippet (24/03/2026)

## v6.23.1

- Corrected errors (16/03/2026)

## v6.23

- Added Menu and MenuItem (16/03/2026)
- Added classLabel for some components (16/03/2026)
- Moved default components value inside the code (16/03/2026)

## v6.22.6.1

- Added line-clamp as a css variable to be able to modify it easily (05/02/2026)
- Added nl2br filter to Readmore component (05/02/2026)

## v6.22.6

- Added component Text:Readmore (05/02/2026)

## v6.22.5

- Modified .slider-rights-reserved to appear at top of the image (03/02/2026)

## v6.22.4

- Added and modified styles for Contact form Honeypot (14/01/2026)

## v6.22.3

- Added striptags for meta description (14/01/2026)
- Corrected Cookie consent data-controller name (14/01/2026)

## v6.22.2

- Removed Security autowire (14/01/2026)

## v6.22.1

- Removed Voter as not used anymore (14/01/2026)

## v6.22

- Made use of primary-light for slider credits (18/12/2025)
- Inversion of credits/reserved rights for Slider (18/12/2025)
- Changed bottom values for Slider credits (18/12/2025)

## v6.21

- Removed use of dashoard and  tools as not really used (03/11/2025)
- Removed display of config as not used (03/11/2025)
- Transferred the display of Pages from PageEdit (03/11/2025)

## v6.20

- Replaced Symfony\Component\Routing\Annotation\Route by Symfony\Component\Routing\Attribute\Route (09/10/2025)

## v6.19.9

- Added_media.scss to emails.scss (09/10/2025)
- Modified .texxt style (09/10/2025)

## v6.19.8

- Added translations (19/09/2025)

## v6.19.7

- Added width/height styles (17/09/2025)
- Added sizes for img (17/09/2025)

## v6.19.6

- Added lang attribute for Card (12/09/2025)

## v6.19.5

- Added raw to button to allw html content (08/09/2025)

## v6.19.4

- Added label possibility on image components (01/09/2025)

## 6.19.3

- Added a locale variable to be able to change it at the main level template (21/08/2025)

## v6.19.2

- Added raw filter to label to allow html (01/08/2025)

## v6.19.1

- Added possibility to have rights reserved display for each slide in Slider component (29/06/2025)
- Added pre-load for slider images (29/06/2025)

## v6.19

- Updated legal notice model (06/06/2025)

## v6.18.7.2

- Modified styles (06/06/2025)

## v6.18.7.1

- Corrected navbar img for screen > 768px (06/06/2025)

## v6.18.7

- Modified styles (06/06/2025)

## v6.18.6

- Corrected.bold (27/05/2025)
- Added .primary .secondary (27/05/2025)

## v6.18.5

- Added easiest way to override images in error pages (25/05/2025)

## v6.18.4

- Codacy corrections (23/05/2025)

## v6.18.3

- Added styles (26/04/2025)
- Added emails.scss (26/04/2025)
- Added user variables/typography for emails (26/04/2025)

## v6.18.2

- Added missing require twig/cssinliner-extra (25/04/2025)

## v6.18.1

- Codacy corrections (25/04/2025)

## v6.18

- Added level for title Card (10/04/2025)
- Added img-white class (10/04/2025)
- Added email templates froms c975LEmailBundle abandonned (25/04/2025)

## v6.17.11

- Added progress bar (09/04/2025)

## v6.17.10

- Added styles (04/04/2025)

## v6.17.9.3

- Added max-width to slider (03/04/2025)

## v6.17.9.2

- Removed slider aspect-ratio (03/04/2025)

## v6.17.9.1

- Corrected xlf file (03/04/2025)

## v6.17.9

- Added translations (03/04/2025)

## v6.17.8.1

- Corrected css error (02/04/2025)

## v6.17.8

- Added .btn-grey (02/04/2025)

## v6.17.7.1

- Corrected background color for .btn-small (31/03/2025)

## v6.17.7

- Added .btn-large (27/03/2025)

## v6.17.6.1

- Codacy corrections (26/03/2025)

## v6.17.6

- Corrected Slider (26/03/2025)

## v6.17.5.1

- Removed background color for btn-small class (22/03/2025)

## v6.17.5

- Added missing translations from fusion of c975L/ServicesBundle (22/03/2025)

## v6.17.4

- Corrected template call of inc_content (09/03/2025)

## v6.17.3

- Removed s from Service (09/03/2025)

## v6.17.2

- Corrected autowire (09/03/2025)

## v6.17.1

- Corrected namespace (09/03/2025)

## v6.17

- Removed use of `c975L/ServicesBundle` and include its service inside this bundle (09/03/2025)
- Removed use of `c975L/IncludeLibraryBundle` (09/03/2025)

## v6.16.6

- Made use of absolute_url for Images Components (22/02/2025)

## v6.16.5

- Modified button secondary colors for better contrast (27/01/2025)

## v6.16.4.1

- Corrected button to avoid leading _ when inline button (26/01/2025)

## v6.16.4

- Corrected button (26/01/2025)

## v6.16.3

- Added css for blockquote (26/01/2025)

## v6.16.2

- Added condition length > 0 for Slider (12/01/2025)

## v6.16.1

- Modified styles for slider (12/01/2025)

## v6.16

- Modified Slider to authorize credits per image (12/01/2025)

## v6.15.2

- Added parameter c975LCommon.url (10/01/2025)

## v6.15.1

- Added style for c975L/ContactFormBundle honeypot (26/11/2024)

## v6.15

- Added an Audio component (27/10/2024)

## v6.14.4

- Added Video:Iframe component (24/10/2024)
- Added attributes loop and muted to Video component (24/10/2024)

## v6.14.3.1

- Corrected example of Video component (24/10/2024)

## v6.14.3

- Modified Video component (22/10/2024)

## v6.14.2

- Version due to conflict (16/10/2024)

## v6.14.1

- Added default value for aria-label in componenet Image:Link (16/10/2024)

## v6.14

- Removed text-center from Card component (15/10/2024)

## v6.13.1

- Added a default value for aria-label in Componenet Image:Link (16/10/2024)

## v6.13

- Added span to Image component to be able to select label (15/10/2024)
- Added Video componenet (15/10/2024)

## v6.12.10

- Corrected components to make them re-usable (15/10/2024)

## v6.12.9

- Corrected slider that was not initialized on first load (10/10/2024)

## v6.12.8

- Added aria-label="{{ label }}" to Image:Link (07/10/2024)

## v6.12.7

- Corrected Button component (30/09/2024)

## v6.12.6

- Suppressed first slash of fileif present (30/09/2024)

## v6.12.5

- Modified requirement for AssetController file (30/09/2024)

## v6.12.4

- Corrections from Codacy (29/09/2024)

## v6.12.3

- Converted Matomo and CookieConsent to components (29/09/2024)

## v6.12.2

- Corrections from Codacy (29/09/2024)

## v6.12.1

- Converted some fragments to Componenets (29/09/2024)
- Added width and height on images as optional (29/09/2024)

## v6.12

- Added {{ importmap('app') }} for asset-mapper (29/09/2024)
- Moved block javascript to head (29/09/2024)
- Converted javascripts to Stimulus controllers (29/09/2024) [BC-Break]
- Added Component Stimulus:Controller (29/09/2024)
- Added confetti animation (29/09/2024)

## v6.11

- Removed lock from button and added icon (26/09/2024) [BC-Break]
- Corrected slider style (26/09/2024)
- Modified Slider to display arrows and dots only for more than one item (26/09/2024)
- Added --button-secondary-color (26/09/2024)
- Added _tables.scss (26/09/2024)

## v6.10.1

- Corrected example of use for components (26/09/2024)

## v6.10

- Removed abbreviation for components (26/09/2024) [BC-Break]
- Added class to Card component (26/09/2024)
- Added animations (26/09/2024)
- Used SASS for animations (26/09/2024)
- Put in place the use of prefers-reduced-motion (26/09/2024)
- Added dataAnimation for Card component (26/09/2024)

## v6.9.6.1

- Forgot the block... (24/09/2024)

## v6.9.6

- Added a {% block preconnect %} in `layout.html.twig` (24/09/2024)

## v6.9.5.2

- Added missing loading="lazy" (24/09/2024)

## v6.9.5.1

- Corrected use example of Card component (24/09/2024)

## v6.9.5

- Added styles (18/09/2024)
- Added possibility of inline buttons (18/09/2024)
- Modified examples of component to indicate optional between [] (18/09/2024)

## v6.9.4

- Suppressed functions-old.js (17/09/2024)
- Corrections from Codacy (17/09/2024)

## v6.9.3

- Re-factorisation of javascript functions (17/09/2024)

## v6.9.2

- Corrections identified by Codacy (16/09/2024)

## v6.9.1

- Added examples of use for Twig components intemplates (15/09/2024)
- Modified some components (15/09/2024)
- Added Slider component (15/09/2024)
- Added Image:Link componenet (16/09/2024)

## v6.9

- Added ->setMaxAge(3600) to controllers (15/09/2024)

## v6.8

- Added AssetController (15/09/2024)
- Added DownloadController (15/09/2024)
- Modified README (15/09/2024)
- Added require `symfony/ux-twig-component": "*"` (15/09/2024)
- Added styles (15/09/2024)
- Added Twig components (15/09/2024)

## v6.7.1

- Added style (13/09/2024)

## v6.7

- Suppressed spaceless filter as it's deprecated (12/09/2024)

## v6.6.13

- Suppressed title attributes that were not accessibility compliant (12/09/2024)

## v6.6.12

- Added style (01/09/2024)

## v6.6.11

- Added aria label for Top/Bottom buttons (21/08/2024)

## v6.6.10

- Added margin for footer elements (21/08/2024)

## v6.6.9

- Added  loading="lazy" (16/08/2024)

## v6.6.8

- Added styles (07/06/2024)

## v6.6.7

- Added styles (01/04/2024)

## v6.6.6

- Updated Command file (31/03/2024)

## v6.6.5.2

- Added class box-shadow (12/03/2024)

## v6.6.5.1

- Changed class img-rounded (11/03/2024)

## v6.6.5

- Added classes (09/03/2024)

## v6.6.4

- Added card styles (19/02/2024)

## v6.6.3

- Changed lead css (19/02/2024)

## v6.6.2

- Re-ordered css (19/02/2024)

## v6.6.1

- Codacy corrections (18/02/2024)

## v6.6

- Added card styles (18/02/2024)
- Moved css to mobile first (18/02/2024)
- Moved css to sass (18/02/2024)

## v6.5.3

- Corrected backTop and pullDown butons (16/02/2024)

## v6.5.2

- Removed use of errorImages array (15/02/2024)

## v6.5.1

- Added missing svg (15/02/2024)

## v6.5

- Changed error images and templates (15/02/2024)

## v6.4.4

- Added img/container styles (13/02/2024)

## v6.4.3

- Added images sizes in frgaments (12/02/2024)
- Added error images by default (12/02/2024)
- Added cookieconsent message by default (12/02/2024)

## v6.4.2

- Codacy corrections (11/02/2024)

## v6.4.1

- Added styles for forms (11/02/2024)
- Removed flash messages dismiss (11/02/2024)

## v6.4

- Removed use of jQuery (10/02/2024)

## v6.3.3

- Changed flash messages (30/01/2024)

## v6.3.2

- Changed input focus color to be less "agressive" (29/01/2024)

## v6.3.1

- Removed movement (due to border) on input focus and changed its color (29/01/2024)

## v6.3

- Added some animations (26/01/2024)

## v6.2.1

- Suppressed little things (25/01/2024)

## v6.2

- Suppressed trailing slashes (25/01/2024)

## v6.1.1

- Added possibility to have only site as page title in case title is set to '' (25/01/2024)

## v6.1

- Supressed load of libraries by default (24/01/2024)

## v6.0.1

- Cosemtic changes (22/01/2024)

## v6.0

- Changed to new recomended bundle SF 7 structure (16/01/2024)

Upgrading from v5.x? **Check UPGRADE.md**

## v5.0.1

- Changed to AbstractBundle (04/12/2023)

## v5.0

- Changed routes to attribute (04/12/2023)

Upgrading from v4.x? **Check UPGRADE.md**

## v4.0.2

- Added TreeBuilder return type (29/05/2023)

## v4.0.1

- Version not tagged (29/05/2023)

## v4.0

- Changed compatibility to PHP 8(25/07/2022)

Upgrading from v3.x? **Check UPGRADE.md**

## v3.2

- Added return type for Voter (24/07/2022)
- Changed composer versions constraints (24/07/2022)

## v3.1

- Added semantic balises (03/06/2022)

## v3.0.5

- Added meta data (15/04/2022)

## v3.0.4

- Modified fragments (02/03/2022)

## v3.0.3

- Added fragment hostedBy (02/03/2022)

## v3.0.2

- Corrected Command return for SF 4 (14/10/2021)

## v3.0.1

- Added return for console Command (08/10/2021)

## v3.0

- Changed `localizeddate` to `format_datetime` (20/09/2021)

Upgrading from v2.x? **Check UPGRADE.md**

## v2.x

## v2.5

- Removed versions constraints in composer (03/09/2021)

## v2.4.2

- Updated Matomo script (22/07/2020)

## v2.4.1

- Cosmetic changes due to Codacy review (05/03/2020)

## v2.4

- Added A4 print sizes (sorry for letter format users) (19/02/2020)

## v2.3

- Removed use of symplify/easy-coding-standard as abandonned (19/02/2020)

## v2.2.4

- Suppressed transform on form field hover as quite annoying (19/02/2020)

## v2.2.3

- Removed composer.lock from Git (19/02/2020)

## v2.2.2.1

- Added attributs title (19/01/2020)

## v2.2.2

- Resized images to decrease downloaded size (28/11/2019)

## v2.2.1

- Added animations for inputs (18/11/2019)

## v2.2

- Made use of apply spaceless (05/08/2019)

## v2.1.1.1

- Forgotten to save layout.html.twig ;-) (03/06/2019)

## v2.1.1

- Removed forgotten call for bootstrap js (03/06/2019)

## v2.1

- Suppressed inclusion of bootstrap 3 by default in `layout.html.twig` (03/06/2019)

## v2.0.4.1

- Changed Github's author reference url (08/04/2019)

## v2.0.4

- Corrected README.md (19/03/2019)
- Made use of Twig filter spaceless instead of spaceless tag (22/03/2019)

## v2.0.3

- Removed deprecations for @Method (13/02/2019)
- Implemented AstractController instead of Controller (13/02/2019)
- Modified Dependencyinjection rootNode to be not empty (13/02/2019)

## v2.0.2

- Modified required versions in `composer.json` (25/12/2018)

## v2.0.1

- Corrected `UPGRADE.md` for `php bin/console config:create` (03/12/2018)
- Added rector to composer dev part (23/12/2018)
- Modified required versions in composer (23/12/2018)

## v2.0

- Created branch 1.x (02/09/2018)
- Updated composer.json (01/09/2018)
- Removed common data from layout that will be set via c975L/ConfigBundle (02/09/2018)
- Updated `README.md` (02/09/2018)
- Added `bundle.yaml` (02/09/2018)
- Made use of c975L/ConfigBundle (02/09/2018)
- Added `UPGRADE.md` (02/09/2018)
- Added Controller + Voter for Routes `site_config` + `dashboard_config` (02/09/2018)
- Cleaned Configuration class (02/09/2018)

Upgrading from v1.x? **Check UPGRADE.md**

## v1.x

## v1.6.7.3

- Added meta "og:site_name" (19/08/2018)
- Added link to BuyMeCoffee (22/08/2018)
- Added link to apidoc (22/08/2018)
- Added documentation (22/08/2018)

## v1.6.7.2

- Removed chrome value for "X-UA-Compatible" (03/07/2018)
- Added href value for alternate language when only one (03/07/2018)
- Suppressed 'type="text/javascript"' as unneeded (03/07/2018)

## v1.6.7.1

- Removed viewport values that prevent users from resizing documents (10/06/2018)

## v1.6.7

- Removed old IE versions warnings (27/05/2018)
- Corrected meta copyright (27/05/2018)
- Re-ordered css form largest to smallest screen size and removed `!important` (06/06/2018)
- Added language declaration in openinng html (10/06/2018)
- Corrected base balise (10/06/2018)

## v1.6.6

- Updated privacy-policy linked to GDPR (25/05/2018)

## v1.6.5.5

- Removed required in composer.json (22/05/2018)

## v1.6.5.4

- Corrected some styles (15/05/2018)
- Added styles for "toolbar" (15/05/2018)

## v1.6.5.3

- Corrrected input outline (13/05/2018)

## v1.6.5.2

- Corrected `services.yml` (13/05/2018)

## v1.6.5.1

- Corrected missing file for auto-discovery of services (12/05/2018)

## v1.6.5

- Added "line" style in place of "box" style for input fields (12/05/2018)

## v1.6.4.1

- Removed in `README.md` blocks to disable for error pages as if they are removed we lose some functionalities (04/05/2018)

## v1.6.4

- Set on one line matomo code (28/04/2018)
- Added condition for ogImage != null for display (02/05/2018)

## v1.6.3

- Corrected text for err410 (14/04/2018)
- Suppressed contact link in error templates as c975L/ContactFormBundle may not be installed (14/04/2018)

## v1.6.2

- Added javascript function `nl2br()` to remove carriage returns (04/04/2018)
- Added ogImage variable to separate from logo (05/04/2018)]

## v1.6.1

- Corrected copyright date display to set only one year if firstOnlineDate == current year (03/04/2018)

## v1.6

- Changed the format of `languagesAlt` to be re-used for `navbarLanguagesDropdownMenu.html.twig` [BC-Break] (23/03/2018)
- Added fragment `navbarLanguagesDropdownMenu.html.twig` (23/03/2018)

## v1.5.4.1

- Added condition `display == pdf` for block `logoPrintOnly` in `layout.html.twig` (21/03/2018)
- Added removing of displaying url in print format in `styles.css` (22/03/2018)

## v1.5.4

- Added condition `display == html` to load jQuery in `layout.html.twig` (21/03/2018)

## v1.5.3.1

- Suppressed second call of jQuery (19/03/2018)

## v1.5.3

- Corrected block `acceptation` in Terms of use (18/03/2018)
- Added empty block `payingServices` to Terms of use to allow override (18/03/2018)
- Added empty block `services` to Terms of sales to allow override (18/03/2018)
- Added full layout example to `README.md` (18/03/2018)

## v1.5.2

- Added `hreflang` meta for multiples languages (15/03/2018)
- Added full example of layout in `README.md` (15/03/2018)
- Added css styles (15/03/2018)
- Added DependencyInjection to discover services (15/03/2018)

## v1.5.1

- Moved jQuery call into its proper block at the top of body, in order that it's loaded before any other jQuery function call (13/03/2018)

## v1.5

- Added `models:twig2md` Command to convert templates to Markdown to make their reading easier on Github (13/03/2018)
- Added Markdown format for pre-defined models (13/03/2018)

## v1.4.3

- Changed scroll value for pullDown (12/03/2018)

## v1.4.2

- Corrected error410 page (12/03/2018)

## v1.4.1

- Re-added the possibility to call default language at country level, as it's useful for multilingual sites (12/03/2018)

## v1.4

- Suppressed "div.container" in error pages (12/03/2018)
- Added country level folder for models (12/03/2018)

## v1.3.1

- Added color named styles (09/03/2018)
- Added default value for copyright (09/03/2018)
- Added model for Privacy policy (09/03/2018)
- Added a test to display the more accurate latest update between the models files and the date provided by the site (09/03/2018)
- Corrected pullDown javascript function (09/03/2018)

## v1.3

- Added print styles for bootsrapt alerts (08/03/2018)
- Changed size for print logo (08/03/2018)
- Updated `README.md` (08/03/2018)
- Corrected translations for error pages (08/03/2018)
- Added models for Terms of use, Terms of sales, etc. (08/03/2018)

## v1.2.2

- Corrected indentation in `layout.html.twig` (07/03/2018)
- Changed `README.md` to use `inc_content()` (07/03/2018)

## v1.2.1

- Corrected `layout.html.twig` for `if display` to check if it's not pdf instead of checking 'html' as display can take other values (07/03/2018)

## v1.2

- Moved pullDown bookmark after footer (05/03/2018)
- Added block `navigationBottom` (06/03/2018)
- Added block `container` (06/03/2018)
- Added conditions to test if display is for html or pdf (Used by c975L/PageEdit) (06/03/2018)
- Added meta `hreflang` (06/03/2018)
- Added css styles (06/03/2018)

## v1.1

- Added core system files (04/03/2018)

## v1.0

- Creation of bundle (04/03/2018)
