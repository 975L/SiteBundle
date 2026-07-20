# Changelog

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

## 6.28.1

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

## v6.27.2

- Corrected SitemapCreateCommand

## v6.27.1

- Removed use of Fixtures to load default values and made use of ConfigBundle Command (22/06/2026)

## v6.27

- Added time for maintenance access (15/06/2026)
- Added join for article medias (18/06/2026)
- Corrected Sitemap command to include pages in database (18/06/2026)
- Moved Listener logic to CrudControllers (18/06/2026)
- Removed twitter meta data (20/06/2026)
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
- Added Twig Extension Nl2br to avoid use of <br /> (31/03/2026)
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

## v6.22.6

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

## v6.19.2

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

- Added Twitter cards (07/03/2018)
- Corrected indentation in `layout.html.twig` (07/03/2018)
- Changed `README.md` to use `inc_content()` (07/03/2018)

## v1.2.1

- Corrected `layout.html.twig` for ` if display` to check if it's not pdf instead of checking 'html' as display can take other values (07/03/2018)

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
