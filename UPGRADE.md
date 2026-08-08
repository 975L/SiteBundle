# UPGRADE

## Unreleased

**`MenuExtension` no longer takes a `TranslatorInterface`.** A contributed menu target is now labelled by
ConfigBundle's `LinkableRouteRegistry::label()`, which leaves the extension with nothing to translate of
its own. **Nothing to run** — the service is autowired — unless your app instantiates `MenuExtension` by
hand or decorates it: drop the fifth constructor argument.

This bundle now reads that `label()` method, so it needs the core-bundle release shipping it.

## v8.1.2

**The bare `nav` element is no longer styled.** `sass/_navbar.scss` held `nav { text-align: center; height: 74px }` plus a link and an image rule — meant for the centered-logo fallback a site with no navbar menu gets, but reaching the menu navbar too and holding it at 74px whatever its header asked for, which is what pinned its logo and burger against the top edge. Those four rules now hang off a `.nav-simple` class, which `Navbar.html.twig` puts on that fallback. **Nothing to run**, unless your app renders a `<nav>` of its own and was counting on those rules: add `nav-simple` to its class list, or restate what it needs in `app.css`. The same holds for the sibling c975L bundles rendering a bare `<nav>` — GalleryBundle's breadcrumb is one, and it gains from the removal, its own `.gallery-breadcrumb` rules already laying down what it needs.

## v8.0.0

**Twelve more shared pieces left this bundle, and nothing they did depended on having pages.** A site running Config + Ui plus a shop, a catalogue or a gallery had no theme compiled, no favicon to upload, no cookie banner, no redirects and half the health checks missing. All of it moved to the bundle owning the domain; the slugs, route names, table names and Twig function names are unchanged, so **nothing to run**:

| Removed from this bundle | Now |
|---|---|
| `Listener\ThemeVariablesCssListener`, `Twig\ThemeVariablesExtension` | `c975L\UiBundle\*` |
| the ten `theme-*` configs | declared by `c975l/ui-bundle` |
| `Controller\Management\SiteGraphicCrudController` | `c975L\UiBundle\Controller\Management\SiteGraphicCrudController` |
| `Management\SiteGraphic{Alert,Export,Import}Provider` | `c975L\UiBundle\Management\*` |
| `Form\OgImageType` | `c975L\UiBundle\Form\OgImageType` |
| `templates/components/General/CookieConsent.html.twig` | `<twig:c975LUi:Cookie:Consent />` |
| `site-enable-cookie-consent`, `url-cookies-policy` | declared by `c975l/ui-bundle` |
| `Management\SvgFontsHealthCheckProvider` | `c975L\UiBundle\Management\SvgFontsHealthCheckProvider` |
| `Entity\Redirect`, `Repository\RedirectRepository`, `EventSubscriber\RedirectSubscriber` | `c975L\ConfigBundle\*` |
| `Controller\Management\RedirectCrudController`, `Management\Redirect{Export,Import,Chain*}Provider` | `c975L\ConfigBundle\*` |
| `Service\Security\SessionNonceGenerator` | `c975L\ConfigBundle\Security\SessionNonceGenerator` |
| `Twig\CopyrightExtension` | `c975L\ConfigBundle\Twig\CopyrightExtension` |
| `site-author`, `site-first-online-date` | declared by `c975l/config-bundle` |
| `Management\{Ssl*,SecurityHeaders*,SeoFiles*}HealthCheckProvider` and their clients | `c975L\ConfigBundle\*` |
| `Management\ContentQualityAnalyzer`, `Service\ContentQualityClient` | `c975L\ConfigBundle\*` |
| `Management\DeclaredUrlsHealthCheckProvider` and its compiler pass | `c975L\ConfigBundle\*` |
| `Service\PageExistenceChecker` | `c975L\ConfigBundle\Service\UrlStatusChecker` |
| `Service\PagePublicUrlResolver::resolveSiteRoot()` | `c975L\ConfigBundle\Service\SiteUrlResolver::siteRoot()` |
| `Controller\AssetController` and its `/asset/{file}` route | removed, nothing called it and the web server already serves `public/` |

`site_copyright()`, `site_media()`, `theme_variables_css()` and the `site_redirect` table keep their names, so a template, a query or a migration using any of them is untouched. Two things to know:

- **The `content-quality` check keeps every one of its rows**, block links included: the analyzer is generic now and gets them back through `Management\PageContentOffenceLocator`, which this bundle registers. A bundle whose urls have no such mapping still gets the check, just without the links.
- **`security-headers` no longer resolves the home Page**, it reads the site root (`SiteUrlResolver::siteRoot()`). Same url on the dashboard as before, and it now runs on a site with no pages at all. Its row label is `null` rather than the home page's title.
- **Twelve `label.*` keys moved domain**: the site-graphic and cookie-banner ones to `ui`, the redirect and site-wide health-check ones to `config`, `label.summary_social_network` to `config` (read from two bundles now). If your app overrode any of them in its own `translations/`, move the override to the matching domain.

**Nine more shared pieces left this bundle.** None of them concerned the notion of a site, and several were hand-duplicated by bundles requiring Config + Ui and not this one. See the UPGRADE of `c975l/ui-bundle` and `c975l/config-bundle` for the full tables; from here:

| Removed | Now |
|---|---|
| `Form\VichImageOptions` | `c975L\UiBundle\Form\VichImageOptions` |
| `Controller\Management\Trait\UniqueSlugTrait` | `c975L\UiBundle\Service\UniqueSlug` |
| `Controller\Management\Trait\BlockMoveRowAttrTrait` | `c975L\UiBundle\Service\BlockMoveRowAttrBuilder` |
| `Management\BlockFocusUrlTrait` | `c975L\UiBundle\Service\BlockFocusUrl` |
| `Listener\AbstractBlockCacheInvalidationListener` | `c975L\UiBundle\Listener\*` |
| `Management\BlockDataExporter` / `BlockDataImporter` | `c975L\UiBundle\Management\*` |
| `Controller\DownloadController` | `c975L\UiBundle\Controller\DownloadController` |
| `Management\Trait\HealthCheckErrorRowTrait` | `c975L\ConfigBundle\Management\HealthCheckErrorRow` |
| `Twig\CanonicalUrlExtension` | `c975L\ConfigBundle\Twig\CanonicalUrlExtension` |

`canonical_url()` and the `download_file` route keep their names, so a template calling either is untouched. `PageCrudController` and `MenuCrudController` swapped their `CsrfTokenManagerInterface` argument for a `BlockMoveRowAttrBuilder` one — **only relevant if your app instantiates them itself**, which nothing normally does.

**`url-terms-of-use` is declared by ConfigBundle now**, this bundle's identical copy being dropped along with ShopBundle's and PaymentBundle's — `PaymentBundle` reads it and requires none of the three. Same slug, same group, **nothing to run**.

**Fifteen config keys left the `email` and `general` groups.** The six addresses `EmailService` resolves (`email-from`/`email-to`/`email-reply-to` and their `-name` counterparts) plus `site-name`, `site-contact-email`, `site-director`, `site-made-by-logo` and `site-made-by-url` are declared by ConfigBundle now, and `site-form-delay`/`site-form-gdpr` by UiBundle — each of them was read by code in those bundles, so a site not installing this one had no way to fill them. `email-from` was even declared twice, here and there, identically. **Nothing to run**: the slugs are unchanged and an existing `site_config` row is matched as it is; only the bundle that declares it changes. The five `email-text-*` keys stay here, being the copy of this bundle's own branded email layout.

**The account layer left this bundle for ConfigBundle.** Every satellite bundle requires Config + Ui and none requires this one, yet all of them relate their entities to `Contract\UserInterface` — so the back-office, the registration flow and the account scaffold now live where the contract already did. See `c975l/config-bundle`'s own UPGRADE for the full class-by-class table and the re-scaffold to run; from here, what disappears is:

| Removed from this bundle | Now |
|---|---|
| `Controller\Management\UserCrudController` | `c975L\ConfigBundle\Controller\Management\UserCrudController` |
| `Security\Voter\UserManagementVoter` | `c975L\ConfigBundle\Security\Voter\UserManagementVoter` |
| `Service\UserRegistrar` / `EmailVerifier` / `PasswordResetter` | `c975L\ConfigBundle\Service\*` |
| `templates/management/user_crud_{index,edit}.html.twig` | `@c975LConfig/management/*` |
| `templates/emails/{confirmation,reset_password}_email.html.twig` | removed, composed from their EmailTemplate instead |
| the whole account half of `scaffold/` | `c975l/config-bundle`'s scaffold |
| the `user-roles-available` config and the `label.users`/`label.roles`/`label.info_user*` keys | `c975l/config-bundle` |

**Update `c975l/config-bundle` at the same time**, and re-scaffold: `php bin/console c975l:scaffold:install`. Nothing to run on the database — `site_user` is the app's own table and never moved.

**The legal models moved to UiBundle**, for the same reason: a site running a shop, a book catalogue or a gallery without page management still owes its visitors a privacy policy and its buyers terms of sales, and nothing in a legal document is about pages. The 18 templates, `LegalModelCatalog`, `LegalModelRenderer`, `LegalModelPlaceholders`, `LegalModelCustomizer`, `LegalModelExtension`, the four form types, `LegalModelController` and `LegalModelDriftHealthCheckProvider` are `c975L\UiBundle\*` now, the models being reached at `@c975LUi/models/…` and their labels living in the `ui` translation domain.

**Nothing to run**: blocks keep their `legal_model` kind and their whole `data`, customization delta included. What changes:

| Was | Now |
|---|---|
| `@c975LSite/models/{country}/{model}.{locale}.html.twig` | `@c975LUi/models/…` |
| `templates/bundles/c975LSiteBundle/models/…` (app override) | `templates/bundles/c975LUiBundle/models/…` |
| `management_site_legal_models` / `…_customize` | `management_ui_legal_models` / `…_customize` |
| the `site` translation domain, for every `label.legal_*` key | the `ui` domain |
| `PageRepository::findWithLegalModelBlocks()` | removed - UiBundle walks the blocks themselves |

Two things stay here, both being about pages: `c975l:site:pages:import-defaults` still creates one page per model, and `site_legal_pages()` still lists them. `SiteBlockLocationProvider` is new, and is what tells UiBundle's screens which page a document sits on — implement `BlockLocationProviderInterface` yourself if your own entity carries `legal_model` blocks.

**Six more config keys left the `legal` group.** `site-owner`, `site-producer`, `site-hosting-provider`, `site-dpo`, `site-director-location` and `site-contact-phone` are declared by ConfigBundle now, next to `site-name`, `site-director` and `site-contact-email` — the models reading all nine had no way to fill them on a site without this bundle. Same slugs, same group, **nothing to run**.

**`site-other-copyright` and `site-other-cookies` are declared by UiBundle now**, the copyright and cookies models being the only readers. Same slugs, same group, **nothing to run**.

If your app overrode a model template, move it under `templates/bundles/c975LUiBundle/`; if it rendered one with a plain `{% include %}`, change the namespace. A site that only ever used the block and the back-office screen has nothing to do.

**Fonts moved to UiBundle**, which already owned the `FontProviderInterface` they answer and the blocks that pick them: `Entity\Font`, `FontRepository`, `FontCrudController`, `FontBulkImportController`, `FontService`, `FontFilenameParser`, `FontCssListener`, `FontPreloadExtension`, `Font{Export,Import}Provider` and the four `font_*` templates are `c975L\UiBundle\*` now. The `site_font` table, the `theme-font-family-*` configs and the compiled `bundles/build/site-fonts-uploaded.css` are unchanged — only the namespaces are, so **nothing to run**. The "Fonts" menu entry is contributed by UiBundle's own `MenuProvider`, as are "Media library", "Forms" and "Email templates", which this bundle used to declare on UiBundle's behalf.

**Five generic Twig helpers moved to UiBundle**: `nl2br`, `linkify`, `route_exists`, `template_exists` and `asset_exists`. None had anything to do with the notion of a site, and `asset_exists` was already being called from BookBundle templates — which don't require this bundle, so those calls only worked by accident. Same names, same behaviour, available to every bundle now.

**The failed-Messenger screen, the table export, the scaffold installer and the `deployment` health check moved to ConfigBundle**, with three renames: `c975l:site:messenger-cleanup` → `c975l:config:messenger-cleanup`, `c975l:site:export-tables` → `c975l:config:export-tables`, and the `management_site_messenger_failed*` routes → `management_config_messenger_failed*`. `c975l:scaffold:install` keeps its name. Both commands run on the Symfony scheduler through `MaintenanceTaskProviderInterface`, so **there is no crontab to edit**. The "Export tables" and "Enable/disable registration" shortcuts are contributed by ConfigBundle now; this bundle keeps "Create a page" alone.

**Seven Composer requirements are gone**: `symfonycasts/reset-password-bundle`, `symfonycasts/verify-email-bundle`, `symfony/rate-limiter`, `symfony/messenger`, `symfony/process`, `symfony/mailer`, `symfony/scheduler` and `dragonmantank/cron-expression` — all of them followed the code that used them. They are required by `c975l/config-bundle` instead, which this bundle requires, so an application composing both keeps every one of them installed.

**`SiteMaintenanceTaskProvider` is removed.** Its only task was the messenger cleanup, now declared by ConfigBundle's own provider. Nothing referenced the class.

**Added `Service\SiteFormPageUrlProvider`**, implementing UiBundle's new `FormPageUrlProviderInterface`: it answers `form_url('register')` with the real `Page` carrying that `form` Block, so the scaffolded login page keeps linking the admin-editable per-locale slug even though the page itself is shipped by ConfigBundle now. `site_page_for_form_block()` still exists and is unchanged, but `form_url()` is what a template should call from now on.

**Unpublishing a page now unreferences it too.** `isIndexable` follows `isPublished` down on every save (`Page::unreferenceWhenUnpublished()`, a `PreFlush` callback), whatever unpublished the page — the edit form, the trash, *Publish as replacement*, a duplication or an import. **Publishing a page again no longer references it back**: the switch stays unchecked until an admin checks it, which is the deliberate call it should be. On the Pages index, where both switches save through ajax, unpublishing a row unchecks and disables its own referencing toggle right away (`publication-switch`), so it never offers to "uncheck" something the database already holds as false. Nothing to run: no schema change, and an already-unpublished page is corrected on its next save — the sitemap has always excluded unpublished pages anyway (`PageRepository::findAllOrdered()`), so what changes is the page's own `robots` meta tag and what the edit screen honestly shows.

**The scaffolded `App\Scheduler\MaintenanceSchedule` takes a `MaintenanceScheduleBuilder` now, and lists no command of its own.** Each bundle declares the commands it needs run through ConfigBundle's `MaintenanceTaskProviderInterface`, so the class is the same file on every site and a bundle installed later schedules its tasks without an edit here (see the readme). Its scaffolded test moved with it - **re-scaffold the two together**, the test being written against that new two-argument constructor:

```bash
php bin/console c975l:scaffold:install --dry-run --path=src/Scheduler --path=tests/Scheduler
php bin/console c975l:scaffold:install --path=src/Scheduler --path=tests/Scheduler
```

Your own copies are moved aside into `existingFiles/*.old`, so a command you had added by hand is read back from there and re-added after the call to `addTasks()` - and a command a bundle declares that this site shouldn't run at all is named in its second argument rather than dropping the bundle. Requires `c975l/config-bundle` >= v5.16.

**The bundle now requires PHP 8.4 and Symfony 8.** It used to declare `"php": ">=8.1"` and `"symfony/*": "*"`, an unbound constraint that let Composer resolve Symfony against whatever PHP the application ran on - so an application on PHP 8.2 silently got Symfony 7 with a bundle only ever tested against Symfony 8. The requirements now say what is actually built and tested: `"php": ">=8.4"` and `"symfony/*": "^8.0"`. If your application is still on Symfony 7, stay on the previous release until you migrate - `composer update` will simply refuse to move rather than break anything.

**Your `App\Entity\User` must now implement `c975L\ConfigBundle\Contract\UserInterface`**, `Page::$user` and `CollectionItem::$user` being typed against it instead of `App\Entity\User`. See `c975l/config-bundle`'s own UPGRADE for the one-line change and why nothing else moves - no migration, no configuration, the column and the join stay identical. The scaffold's `User` already implements it, so a site created from `c975l:site:create` after this release has nothing to do; an older one adds the `implements` itself.

**`c975l/social-bundle` is a suggestion of this bundle now, no longer a requirement.** The one thing SiteBundle still took from it was the share buttons band `layout.html.twig` renders between `<main>` and the footer — and it took it by calling `share_buttons_default()`, a Twig function. Twig resolves a function call at **compile time**, so the `{% if config('social-enable-share-buttons') %}` around it protected nothing: dropping the requirement as such would have answered 500 on every front page of a site not installing SocialBundle. The layout includes `@c975LSocial/shareButtons/default.html.twig` instead, which SocialBundle now ships with the band's markup and its config guard — an include is resolved at runtime, and `ignore_missing` swallows the missing namespace. **Update SocialBundle at the same time** if your site uses the share buttons: a SocialBundle older than v1.3.0 has no such template, and the band silently stops rendering. **If your site registers `c975L\SocialBundle\c975LSocialBundle` in `config/bundles.php`**, add `composer require c975l/social-bundle` before updating, otherwise the next `composer update` removes the package and the app fails to boot on the missing class. A site that never registered it simply stops installing a bundle it never enabled.

**A menu is created from the index now, and there is no "new menu" form.** A menu's only own field is its location, one of four, each usable once — so the Menus index shows one create button per location not created yet, each creating the row and opening its edit screen in a single click (`MenuCrudController::create()`, a CSRF-protected POST). `Action::NEW` is disabled and `templates/management/menu_crud_new.html.twig` is gone: **if your app overrode that template**, drop the override, and override `@c975LSite/management/menu_crud_index.html.twig` instead if you need to say something on that screen. Existing menus are untouched, and a location that already has its row simply opens it.

**A navbar only offers `menu_link` now**, through UiBundle's exclusive `menu_navbar` context — a navigation bar being a plain list of links, anything else belonging in the page itself. A navbar already holding another block kind keeps rendering it; it just can't be picked again from that screen. The other three locations keep the full picker.

**`Menu` gained a nullable `style` column** (`site_menu.style`, `VARCHAR(20) DEFAULT NULL`), the layout picked from the footer menu's own edit screen ("Display style": inline, block, or the site theme's own choice). Run `php bin/console doctrine:migrations:diff && php bin/console doctrine:migrations:migrate` — **before deploying the code**, since every page read 500s on "Unknown column style" until the column exists. Existing menus come out with no style at all, which means "whatever the theme decided": **a site that laid its footer out through `--footer-items-direction`/`--footer-items-justify` in `themes/site.css` renders exactly as before**, and only starts following the backoffice once an admin picks a style there.

**`user-roles-available` no longer ships `ROLE_SUPER_ADMIN`** — it's the owner's role, granted once by `c975l:site:create`, and `UserCrudController` decides it itself rather than reading it from the config (stripped from whatever the config holds, then put back for an acting user who already holds it). Nothing to do: a site whose row still lists it sees the same choices as before.

**`Page` gained a nullable `options` column** (`site_page.options`, `JSON DEFAULT NULL`), one payload holding the benign per-page display options rather than a column each — so a future option is a code change alone, with no migration for every app running this bundle to replay. Run `php bin/console doctrine:migrations:diff && php bin/console doctrine:migrations:migrate` — **before deploying the code**, since every page read 500s on "Unknown column options" until the column exists. Existing pages come out with no option set at all, each then reading as its own default, so nothing changes on an upgraded site.

The first option it holds is `titleDisplayed`, exposed as the "Display title" switch on the page edit screen. It defaults to `true`, which is what the layout always did; turn it off on a page opened by a block already carrying its own `<h1>` (a "hero" or a `banner_title` left on its h1 level), where the layout's title made a second top-level heading. `content-quality` reports a page left with none, the same way it reports one with several.

**The form layer moved to UiBundle.** `sass/_forms.scss` and the password behaviours of the `basic` controller (show/hide toggle, format check, confirmation match) now live in `c975l/ui-bundle`, along with `assets/js/handlers.js`, `assets/js/translations.js` and `public/icons/eye.svg`/`eye-slash.svg`. Eight c975L bundles require UiBundle and none requires this one, so a form rendered by ShopBundle or BookBundle had no styling to count on — and UiBundle is what renders the forms in the first place (its `Form`/`FormField` builder, `components/Form/Form.html.twig`, the block and captcha form themes).

Requires `c975l/ui-bundle` at the matching version. Nothing to do if you use this bundle's `layout.html.twig`; **if your app overrides it**, add the controller to the body:

```diff
-<body id="top" data-controller="basic">
+<body id="top" data-controller="basic password">
```

The two checks used to target `#registration_form_plainPassword` / `#registration_form_confirmPassword`, ids belonging to the consuming app rather than to any bundle. They are driven by `data-password-pattern` / `data-password-confirm` / `data-password-message` now — see UiBundle's README. Those two ids keep working until UiBundle 2.0, so an app already using them has nothing to change yet.

**`color-scheme` is now declared**, `light` on `:root` and `dark` in the dark-mode block. Left undeclared, the browser rendered its own parts — autofill, scrollbars, date pickers, select dropdowns — with the light palette whatever the site's theme was: a dark site kept showing white autofilled inputs, and a field left on `background: transparent` got a white surface on desktop (where autofill fires) and the dark page on mobile (where it does not). A site that had worked around this in its own `app.css` should drop that workaround.

**Dark mode now restates every form token**, which it never did — `--form-input-color` stayed `#555` on the `#121212` page, the hover glow `#4d4d4d` was invisible against it, and a field had no surface of its own. Five new tokens come with it: `--input-background`, `--input-valid-border-color` / `--input-invalid-border-color` and their `-shadow-` pair, `--input-icon-filter` and `--form-width`. All are listed, commented out at their defaults, in the scaffolded `assets/styles/themes/theme.css`. A site with its own `theme.css` keeps that file untouched (it is only ever written on first install) and can copy the new lines in from the scaffold.

**The email base moved to UiBundle, and this bundle overrides nothing anymore.** `sass/emails.scss` is down to `@use '_footer.scss'` — its branded footer, the one thing that is genuinely this site's. Everything an email is built out of (the `:root` defaults, typography, tables, lists, images, buttons, alerts) now comes from UiBundle's own `sass/emails.scss`, which `emails/fullLayout.html.twig` sources first:

```diff
 <style>
+    {{ source('@c975LUiCss/emails.min.css') }}
     {{ source('@c975LSiteCss/emails.min.css') }}
     {{ theme_variables_css() }}
 </style>
```

Same reasoning as the form layer: the EmailTemplate builder is UiBundle's, and six bundles send mail while only one of them can count on this bundle being installed. `sass/_email-overrides.scss` is gone with it — the tighter email margins it restated (`section`, `h1`-`h6`) are folded into UiBundle's email typography, so nothing has to override anything. **If your app overrides `emails/fullLayout.html.twig`, add the first `source()` line.**

Rendering was compared before/after on the real block structure: eleven measures identical (heading sizes, colors and margins, paragraph color, muted text, button fill and label, table header rule, cell padding, inline list, responsive image, alignment). The two that moved are a fix — `.alert`/`.alert-danger` were used by email templates in six bundles and styled by none of them, so an alert rendered with no background at all and now gets its tint.

**Five more stylesheets moved to UiBundle**: `sass/_badges.scss`, `_blockquotes.scss`, `_alignments.scss`, `_colors.scss` and `_iframe.scss` — generic components and utility classes, next to UiBundle's own `.btn` / `.card` / `.alert` / `.width-*`. Nothing to do if you load both stylesheets, which every site does. They are no longer pulled by `emails.scss`, checked against every email template shipped by the six bundles that send one: none uses a `.badge`, `.flex-*` or `.primary`-style utility class (the only match was `class="btn btn-primary"`, which comes from `_buttons.scss`).

**Alerts are readable in dark mode, and `--alert-danger-color` changed.** `.alert` used to set `color: #000` on itself only, so anything nested inside it — a `<p>`, a bolded word — was painted `--text` by `_typography.scss`'s own `* { color: var(--text) }`: near-white on a pale tint, measured at 1.02:1. The rule now covers the descendants, the four tints are mixed into the page background in dark mode, and the text follows a new `--alert-color` token. Because the danger color now carries a whole message rather than one bare line, it is darkened from `#b64450` to `#9c3a44` — the old value read at 4.14:1 on its own tint, under WCAG AA. Restate it in your `theme.css` to keep the previous shade.

**`sass/_dimensions.scss` is emails-only now.** Its `.width-100` to `.width-300` duplicated UiBundle's `sass/_sizes.scss`, with different rules on the same class names — UiBundle's also set `width: 90vw; height: auto; margin: 0 auto`, so which of the two won came down to the order the two stylesheet providers happened to be registered in (both tag at `priority: 100`). The front-end now gets UiBundle's set alone, and `.height-100` to `.height-300` moved there with it. `emails.scss` keeps the file, inlining its own CSS without ever loading UiBundle's stylesheet.

**Three tokens stop being flat neutrals and follow the site's own colors.** They were already tinted in the scaffolded `theme.css`; the defaults in `sass/_variables.scss` now match, so a site that never wrote a `theme.css` of its own sees the change:

| Token | Before | Now |
| --- | --- | --- |
| `--section-bg-muted` | `var(--surface-alt)`, i.e. `--background` mixed with `--text` - a grey | `color-mix(in srgb, var(--background) 94%, var(--primary))` - a pale shade of the site |
| `--footer-border` | `solid grey 2px`, ignoring the palette | `solid color-mix(in srgb, var(--footer-background) 80%, black) 2px` |
| `--footer-link-hover-background` | `rgba(0, 0, 0, 0.1)`, a black wash | `color-mix(in srgb, var(--footer-background) 88%, var(--footer-text))` |

The last one is the reason for the change: a black wash only ever darkens, so it is invisible on a dark footer band - which the default footer becomes as soon as the admin picks a dark primary color. Mixing toward `--footer-text` contrasts in whichever direction that footer needs.

To keep the previous look, restate the old value in the site's `assets/styles/themes/theme.css` - the three lines in the table's "Before" column. A footer left `transparent` gets `rgba(0, 0, 0, 0.2)` out of the new border mix, i.e. a discreet dark rule rather than a grey one.

`--surface-alt` itself is unchanged, so anything else reading it (a portfolio thumbnail's empty frame) stays grey.

**`--button-background-secondary-light` is gone.** It was the only one of the `:root`'s 84 tokens no stylesheet ever read - declared out of symmetry with `--button-background-primary-light`, which does back something (the slider's credits strip and a page-section rule). A site that set it was setting nothing; a site reading it from its own `app.css` gets an invalid declaration now and should inline `color-mix(in srgb, var(--secondary) 50%, white)` or declare the token itself in its `theme.css`.

**New `--frame-background` token**, the canvas showing on both sides of the page once the viewport is wider than `--body-max-width`. `html` now carries it in `sass/_general.scss`. It defaults to `var(--background)`, which reproduces exactly what a browser already painted there on its own - `html` having had no background, `body`'s propagated to the canvas. Nothing moves; a design framing its page inside its measure now gives this token another color instead of writing an `html { background-color: ... }` rule in its `app.css`.

**The theme preset catalog is removed** - one site, one theme. Gone: `SiteThemePresetProvider`, `config/themes/*.json`, `sass/themes/*` and the compiled `public/css/themes/*.min.css`, the `theme-stylesheet` config key, and `PageController`'s `?preset=<slug>` preview (that route now treats the query param as any other unknown one). `StylesheetProvider` no longer reads any config, so its constructor takes no `ConfigServiceInterface` - only relevant if you instantiate or extend it yourself.

Nothing breaks visually: the only stylesheet ever shipped, `default`, restated `sass/_variables.scss`'s own base tokens, so a site that had `theme-stylesheet` set to it rendered exactly as one that had it empty. The `site_config` row itself stays in database (`c975l:config:load-all` only inserts and updates, it never prunes) where it is simply no longer read - to be rid of it: `DELETE FROM site_config WHERE slug = 'theme-stylesheet';`, or delete it from the config screen (ROLE_SUPER_ADMIN, it is `restricted`). A site that had set it to a stylesheet of its own has to move those tokens into its `assets/styles/themes/theme.css`.

**Two new navbar tokens, `--navbar-site-name-color` and `--navbar-site-tagline-color`**, replacing the hardcoded `--primary` on `.menu-site-name`/`.menu-site-tagline`. Their defaults reproduce what every site actually renders today (the name in `--primary`, the tagline in `--text` - the rich-text editor wraps the tagline in a `<div>` that `_typography.scss`'s own `* { color: var(--text) }` was painting, whatever `_menu.scss` asked for). Nothing to do; a site whose navbar is a colored flat can now point both at `var(--navbar-text)` instead of overriding the rules.

**Three tokens for the "primary" nav item's pill, `--navbar-btn-background`, `--navbar-btn-background-hover` and `--navbar-btn-color`**, replacing the hardcoded `.btn-primary` colors on `.menu-item--primary`. Defaults are those same colors, so nothing moves. Same reasoning as `--navbar-site-name-color` above: a navbar painted with `--navbar-background: var(--primary)` makes a `--primary` pill invisible, and such a site now inverts it (white pill, `--primary` label) from its `theme.css`.

**Two more navbar tokens, `--navbar-width` and `--navbar-margin-x`**, the pair `.menu` used to hardcode to break out of `--body-max-width` while the footer already read the same one from `--footer-width`/`--footer-margin-x`. Defaults are the previous values (`100vw` and `calc(50% - 50vw)`), so nothing moves. A site framing its page inside `--body-max-width` sets both to `auto`/`0` in its `theme.css` and can drop whatever `.menu { margin: 0; width: auto }` override it had in `app.css`.

**A lesser admin can no longer act on a super admin's account at all.** Freezing the roles field (see the note below) closed the demotion path alone: a plain ROLE_ADMIN could still change a super admin's email and have a password reset sent to their own mailbox, or simply delete the account. `UserCrudController` now hands EasyAdmin the new `UserManagementVoter` as its entity permission instead of the `site-role-admin` role, which is evaluated per row: a super admin's row keeps its place on the Users list but loses its actions, and its edit/delete pages answer 403. Nothing to configure; only an app overriding `configureCrud()` has to keep `->setEntityPermission(UserManagementVoter::MANAGE)` to keep the guard. ROLE_SUPER_ADMIN is granted *before* the `site-role-admin` check, not after: no `role_hierarchy` is shipped, so it doesn't imply that role on its own, and an account holding only the highest role would otherwise be refused every row of the very screen that could grant it the one it's missing.

**A url the health check can't read is no longer reported as "not tested" across the board.** `ContentQualityAnalyzer` used to call any answer >= 400 "page not found" and persist a grey `STATUS_SKIPPED` row with no details - which hid, behind a status meaning "nothing to see here", the very failures the new `urls-<bundle>` kinds exist to surface. Each status now says what it means:

- **404** stays "not deployed here" only for an entry backed by a `Page` (it exists in database either way, which is what the existence HEAD establishes). A url a bundle declares in its own sitemap (see `DeclaredUrlsHealthCheckProvider`) answering 404 is now an **error**: it is advertising a resource that isn't there.
- **410** gets its own **warning** row, "deliberately removed but still declared" - the site answered on purpose (a soft-deleted `Page` returns exactly that, see `PageController`'s `GoneHttpException`), so nothing is broken; what's left to fix is whatever still declares the url. New `label.health_check_url_gone` translation in the `site` domain - re-copy it if you override that domain wholesale.
- **403, 5xx, and a response that never completed** (timeout, DNS, refused connection) are **errors**, as they always should have been - none of them says anything about the url existing.

Nothing to migrate: rows persisted before this upgrade keep the status they were written with, and are replaced by the next `c975l:health-check:run` (the weekly scheduler entry does it on its own). To get the tab clean immediately, drop the stale rows - it's a history table, nothing else reads it: `DELETE FROM site_health_check_result WHERE kind = 'content-quality' OR kind LIKE 'urls-%';`

**The dashboard's "Run health check now" button is now asynchronous** (`c975l/config-bundle`), which needs one more line of Messenger routing on top of the scheduler setup in the readme - see ConfigBundle's own UPGRADE. Without it the button still works, it just blocks the request like it used to.

**`UserCrudController` takes an extra `AdminContextProvider` constructor argument** (autowired, nothing to configure unless you instantiate or extend it yourself). It needs to know which user is being edited: ROLE_SUPER_ADMIN was already kept out of the role choices for a lesser admin - so out of the submitted form's allowed values too - but Symfony's `ChoiceType` *silently drops* a value missing from the choices when it renders the field, where it rejects it on submit. A plain ROLE_ADMIN opening a super admin's record and hitting Save therefore posted a set without ROLE_SUPER_ADMIN and demoted them, without either of them seeing it. The roles field is now displayed disabled in that case (nothing submitted back, stored value kept), with the role left in the choices so the select shows what the user really has.

**`c975l:site:create` now grants `['ROLE_EDITOR', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN']`** to the bootstrap user instead of `['ROLE_ADMIN', 'ROLE_SUPER_ADMIN']`. No `role_hierarchy` is shipped, so ROLE_ADMIN never implied ROLE_EDITOR: the site's owner was failing every action gated by the `site-role-editor` config. Nothing to do on an existing site unless its owner account still lacks it - add ROLE_EDITOR from the Users screen.

**The dev-declared `@font-face` path is removed**, superseded by the font upload screen (`FontCrudController`, see the readme) which already does the same job end to end - it generates the `@font-face` rules, injects them into `site.css` and preloads them, where `assets/styles/_fonts.css` only fed the `theme-font-family-*` selects and still needed its own `@import` in `app.css` to load anything. Gone with it: the `scaffold/assets/styles/_fonts.css` starter file, the `site-fonts-face-file` config key and `FontService`'s CSS parsing (it now only offers the uploaded fonts, and no longer takes `ConfigServiceInterface`/`kernel.project_dir` - update any direct instantiation of your own).

- A site that really declared its fonts in `assets/styles/_fonts.css`: upload each cut from the dashboard's **Fonts** screen (one row per weight/style, or the "Bulk import" action for a whole family at once), then delete the file and its `@import` from `app.css`. Until then the font still loads - the file is the app's own, nothing deletes it - but its name is no longer offered in the `theme-font-family-*` selects.
- `c975l:config:load-all` only inserts and updates, it never prunes: the `site-fonts-face-file` row stays in `site_config` on an existing site, where it shows up in the theme config list with its label untranslated. Nothing else reads it, and `ThemeVariablesCssListener` now skips any theme-group slug without the `theme-` prefix, so it can't leak into `site-theme.css` as a stray custom property either - but to be rid of it: delete it from the config screen (ROLE_SUPER_ADMIN, it's `restricted`), or `DELETE FROM site_config WHERE slug = 'site-fonts-face-file';`.

**`assets/js/translations.en.js`/`translations.fr.js`/`translations.es.js` are merged into a single `assets/js/translations.js`**, keyed by locale, so the front-end costs one request and one modulepreload instead of three. `handlers.js` is up to date; only code of your own importing one of the three per-locale modules (`/bundles/c975lsite/js/translations.fr.js`) has to import `translations.js` instead and read `translations.fr` out of it.

The navbar logo now reads its intrinsic dimensions through `Media::getIntrinsicWidth()`/`getIntrinsicHeight()`, so `c975l/ui-bundle` is now required in `^1.10`. Run `php bin/console c975l:ui:media-dimensions` once to fill in the logo rows uploaded before UiBundle started recording dimensions - until then the logo falls back to its displayed height alone, exactly as before.

`ContentQualityHealthCheckProvider`'s `brokenLinks` detail changed shape - each entry is now an object carrying the url plus where it was found, instead of a bare url string - so that the Health check tab can list the individual offenders behind a "N broken links" row. Only the new shape is read: results persisted before this upgrade keep showing their count with no advice line under it. They're replaced by the next `c975l:health-check:run` (the weekly scheduler entry does it on its own); to get the tab clean immediately, drop the stale content-quality rows: `DELETE FROM site_health_check_result WHERE kind = 'content-quality';` - it's a history table, nothing else reads it.

`ContentQualityClient::readLinkCheck()` returns one of the `LINK_OK`/`LINK_BROKEN`/`LINK_UNKNOWN` verdict strings instead of a `bool`, and `isLinkBroken()` now means "conclusively broken" instead of "not answering 2xx" - an unreachable host (DNS failure, timeout) or a server refusing the HEAD *method* (405/501) is `LINK_UNKNOWN` and gets a GET retry before anything is reported broken, which is what stopped healthy pages from being flagged. Any code of your own calling `readLinkCheck()` in a boolean context now sees every healthy link as truthy, so test it against the constants instead. In the ecosystem only `ContentQualityHealthCheckProvider` calls it, and it's up to date.

**`Page` gained a non-nullable `isIndexable` column** (`site_page.is_indexable`, `BOOLEAN NOT NULL DEFAULT 1`), driving both the page's presence in `sitemap-site.xml` and its `robots` meta tag. Run `php bin/console doctrine:migrations:diff && php bin/console doctrine:migrations:migrate` - **before deploying the code**, since every page read 500s on "Unknown column is_indexable" until the column exists. Existing pages come out indexable, exactly as before; only `DefaultPagesImporter`'s no-SEO-value pages ("creer-un-compte", "mot-de-passe-oublie") are seeded with `false`, and only on a fresh import - a site already past that step keeps its own values, opt a page out from the Page edit screen if wanted.

**Sitemap generation moved to ConfigBundle.** `c975l:site:sitemaps:create` is gone, replaced by `c975l:sitemaps:create` (ConfigBundle's `SitemapWriter`), which writes one `public/sitemap-<name>.xml` per bundle implementing ConfigBundle's new `SitemapProviderInterface` (`getSitemapName()`/`getUrls()`, tagged automatically like `MenuProviderInterface`) **and** the `public/sitemap-index.xml` declaring them all. Any combination of bundles then gets its sitemaps and its index, SiteBundle installed or not, and the app has nothing to list by hand. Requires a `c975l/config-bundle` shipping `SitemapWriter`.

- **Point every scheduler/cron entry at `c975l:sitemaps:create`** (`App\Scheduler\MaintenanceSchedule`, see the bundle's `scaffold/`).
- **The scaffold's `App\Command\SitemapCreateCommand` is removed.** Delete `src/Command/SitemapCreateCommand.php` and its test from your app - running each bundle's sitemap command and writing the index is exactly what the new command does.
- SiteBundle's own `Command\SitemapCreateCommand` is removed too; its url-building lives in `Management\SitePageSitemapProvider::getUrls()`, its file writing in ConfigBundle's `SitemapWriter`. `sitemap-site.xml` itself is unchanged, so nothing to redeclare on Google's side.
- The two sitemap Twig templates moved: `@c975LSite/sitemap.xml.twig`/`@c975LSite/sitemap-index.xml.twig` are now `@c975LConfig/sitemaps/sitemap.xml.twig`/`@c975LConfig/sitemaps/sitemap-index.xml.twig`. Move any override accordingly.
- The "Regenerate sitemap" dashboard shortcut is now ConfigBundle's "Create sitemaps" (`ConfigShortcutController::SITEMAPS_CREATE_ROUTE`), still ROLE_SUPER_ADMIN. `SiteShortcutController::SITEMAP_CREATE_ROUTE` and the `label.sitemaps_create`/`flash.sitemaps_created` `site` translations are removed - update any hardcoded link to that route.

**`EmailVerifier::sendEmailConfirmation()`/`UserRegistrar::register()` no longer take a `TemplatedEmail`.** They now take `string $subject, string $template, string $to` and send through UiBundle's `EmailService` (like `ResetPasswordRequestFormAction` already did), returning `bool` instead of `void` - so the registration confirmation email also honors the ROLE_SUPER_ADMIN "email-debug" preview instead of always really sending. Re-copy `App\Service\RegisterFormAction` from the bundle's `scaffold/`, or update your own copy's `$this->userRegistrar->register(...)` call to pass `$subject, $template, $to` strings instead of a built `TemplatedEmail`.

**`BackupCommand`'s `--full` option is removed.** MySQL is now always dumped table by table (the previous weekly whole-database dump is gone - restore by replaying each table's `.sql` file, each already wrapped in `SET FOREIGN_KEY_CHECKS=0/1` so table order doesn't matter). File backup: complete on the very first run (or if `var/BackupDateTimeFile` is missing) **and now also every `site-backup-full-interval-months` calendar months after that (default 1)**, modified-since-last-run only in between - previously it never went complete again after the first run. Update any cron/scheduler entry still passing `--full` (e.g. `App\Scheduler\MaintenanceSchedule`) to drop it.

**"register"'s CGU checkbox links to the real terms-of-use page again**, via UiBundle's new `FormField::$url` (see its own UPGRADE.md - requires `doctrine:migrations:diff`/`migrate` for `site_form_field.url`). `DefaultPagesImporter` seeds it on a fresh install; on an existing site, re-run `c975l:site:pages:import-defaults` to backfill the "cgu" field's `url` on the already-seeded "register" Form (same idempotent backfill already documented below for `action`) - only touched while still `null`, so an admin who already edited it manually is never overwritten.

**`CollectionEntry` is renamed to `CollectionItem`**, matching the "item" vocabulary already used everywhere around it (UiBundle's own `CollectionItem` DTO, the `collectionItem` Twig global...): `c975L\SiteBundle\Entity\CollectionEntry` → `Entity\CollectionItem`, `Repository\CollectionEntryRepository` → `Repository\CollectionItemRepository`, `Controller\Management\CollectionEntryCrudController` → `CollectionItemCrudController`, `Service\CollectionEntrySourceProvider` → `CollectionItemSourceProvider`, `Command\CollectionEntryImportCommand` → `CollectionItemImportCommand` (command name `c975l:site:collection-entry:import` → `c975l:site:collection-item:import`). If your app injects or extends any of these classes directly, update accordingly.

- Run `php bin/console doctrine:migrations:diff && php bin/console doctrine:migrations:migrate` to rename the `site_collection_entry` table to `site_collection_item` (the generated migration should be a plain `RENAME TABLE`/`ALTER TABLE` - review it before running in production) and its `UNIQ_COLLECTION_ENTRY_GROUP_SLUG` constraint to `UNIQ_COLLECTION_ITEM_GROUP_SLUG`
- The Vich uploader mapping is renamed `collection_entry` → `collection_item` - nothing to reconfigure (registered by the bundle itself via `prependExtension()`), but any already-uploaded file path was already independent of the mapping name, so existing images are unaffected
- The admin menu entry/dashboard route changes from `collection_entry` to `collection_item` - update any hardcoded link to it (e.g. a custom dashboard menu override)

**`CollectionItem`'s free-text `group` column is replaced by a required `collectionGroup` relation to the new `CollectionGroup` entity.** Run `php bin/console doctrine:migrations:diff && php bin/console doctrine:migrations:migrate` - **existing `group` values are not migrated automatically**: the generated migration adds `collection_group_id` as `NOT NULL` with no source column to backfill from, so review it and either create one `CollectionGroup` per distinct existing `group` value and backfill `collection_group_id` yourself before running it in production, or drop/recreate `site_collection_item` on a site with no items worth keeping.

`CollectionItemCrudController`'s index is no longer a single flat table listing every group's items together - collections are now managed via a separate `CollectionCrudController` ("Collections" in the dashboard menu), whose "Items" action opens `CollectionItemCrudController`'s familiar EasyAdmin grid filtered to that one collection (`?collectionGroup=<id>`, not the free-text `?group=<name>` of before). Nothing to migrate: existing items/groups work as-is, this only changes the admin UX. If you link directly to the CRUD's index (bypassing the dashboard menu), update it to pass `?collectionGroup=<id>` instead of `?group=<name>`.

`DefaultPagesImporter` gained an `EmailTemplateRepository` constructor argument (autowired, nothing to configure) and now also seeds `contact_notification`/`account_validation`/`password_reset` `c975L\UiBundle\Entity\EmailTemplate` rows (UiBundle's new EmailBuilder) the first time a page is imported, or the first time `ensureRegisterFormExists()`/`ensureResetPasswordRequestFormExists()` self-heals. Requires `c975l/ui-bundle` with the `Entity\EmailTemplate`/`Entity\EmailBlock` classes (not yet in a tagged release at the time of writing).

Registration/reset-password confirmation emails, and the "contact" `Form`'s notification email, now all render their content via the new `email_template_body('account_validation'|'password_reset'|'contact_notification', {...})` Twig function instead of hand-written markup, and all three now plainly `{% extends "@c975LSite/emails/layout.html.twig" %}` (the empty shell already meant to be extended/overridden, see its own docblock) instead of `fullLayout.html.twig` directly - every email now shares the exact same layout through one ordinary `extends`, no bundle-template-override involved. All three are now bundle-owned templates, not scaffold: `templates/emails/contact_notification.html.twig`/`confirmation_email.html.twig`/`reset_password_email.html.twig` - nothing to re-copy for any of them going forward. `App\Service\RegisterFormAction`/`ResetPasswordRequestFormAction` (scaffold) reference them as `@c975LSite/emails/confirmation_email.html.twig`/`@c975LSite/emails/reset_password_email.html.twig`. To adopt on an existing site: delete your own `scaffold/templates/registration/confirmation_email.html.twig`/`reset_password/email.html.twig` copies (and the now-empty `registration/` folder) and re-copy `App\Service\RegisterFormAction`/`ResetPasswordRequestFormAction` from the bundle's `scaffold/` to pick up the new `htmlTemplate()` path - until then, your existing copies keep working as-is (nothing forces the switch), but their email body then stays static, not editable from the admin's new "Email templates" screen. `EmailTemplateCrudController`'s admin preview still renders standalone (no site layout, no branding) - it's meant to check the compiled block markup, not reproduce the final look; do a real test send (`email-debug` config) to see that.

`App\Security\EmailVerifier` is gone from the scaffold, replaced by the bundle's own `c975L\SiteBundle\Service\EmailVerifier`. Two new bundle services, `UserRegistrar`/`PasswordResetter`, take over the "hash password, persist, send confirmation email" / "hash new password, persist" steps previously inlined in `RegistrationController::register()`/`ResetPasswordController::reset()`.

If your app already has its own copy of these scaffold files (from before this change), nothing breaks - your copy keeps working as-is. To adopt the change on an existing site:

- Delete `src/Security/EmailVerifier.php` and its test - `php bin/console c975l:scaffold:install` now does it for you, from `c975l/core-bundle` v1.2 on (it deletes a withdrawn scaffold file whose content is still one of the versions the bundle delivered, and reports it instead of touching it if you customized it). Run it with `--dry-run` first to see the list.
- Re-copy `RegistrationController.php`/`ResetPasswordController.php` from the bundle's `scaffold/` (or apply the same change by hand: inject `c975L\SiteBundle\Service\UserRegistrar`/`EmailVerifier`/`PasswordResetter`, replace the inline hash/persist/flush/`sendEmailConfirmation()` calls with the corresponding service call - see the bundle's own scaffold files for the exact diff).
- Honeypot, rate-limiting, CSRF, routes, `ChangePasswordFormType` are unchanged, nothing to update there. `RegistrationFormType`/`ResetPasswordRequestFormType` are gone - see further down, they're replaced by a DB-driven form.

**A site scaffolded before 12/07/2026 carries `templates/bundles/c975LSiteBundle/emails/footer.html.twig`**, the scaffold's own copy of the email footer - and that path is the override of the bundle's template, so it keeps winning over it and the restyled footer never shows. Run `php bin/console c975l:scaffold:install` to have it removed (with `--dry-run` first): the file is deleted if it still holds the version delivered, and only reported as obsolete if you customized it. Same for the other scaffold files withdrawn since, all declared in the bundle's `scaffold/removed.json`.

`DefaultPagesImporter` gained `FormRepository`/`EmailTemplateRepository` constructor arguments (autowired, nothing to configure) and now seeds 3 more pages per locale (register/forgot-password/contact) the first time `c975l:site:pages:import-defaults`/`c975l:site:create` runs on a site - existing sites already past that step are unaffected (re-running the command only adds what's missing, by slug). Each of these 3 pages carries a generic `form` Block pointing at its matching `Form` by name ("register"/"reset_password_request"/"contact") - `DefaultPagesImporter` seeds each `Form` (+ matching `EmailTemplate`) itself if not already present, whether the page is newly created or already existed (so a site upgrading past this point gets backfilled just by re-running the import command, even though its pages already exist).

**register/reset-password-request now go through the same generic mechanism as "contact"** (`c975L\UiBundle\Controller\FormController`/`FormActionRegistry`, the "form" Block picked by name in the admin) instead of a dedicated Block kind and controller actions building `c975L\UiBundle\Form\FormSubmissionType` themselves. `RegistrationFormType`/`ResetPasswordRequestFormType` were already removed in a previous entry above; this removes what replaced them:

- **Removed**: the `register`/`reset_password_request` Block kinds (`config/services.yaml`'s `site.block.register`/`site.block.reset_password_request`, `ScaffoldBlockController::registerFragment()`/`resetPasswordRequestFragment()`, `templates/components/ScaffoldBlock/RegisterBlock.html.twig`/`ResetPasswordRequestBlock.html.twig`/`RegisterDisabled.html.twig`); scaffold's `RegistrationController::register()`/`fragment()`, `ResetPasswordController::request()`/`requestFragment()`/`checkEmail()` and the `app_check_email` route/`reset_password/check_email.html.twig` "check your email" page (replaced by the generic `label.form_submitted` flash, same as any other Form); `registration/_form.html.twig`/`reset_password/_request_form.html.twig` (replaced by UiBundle's own `Form.html.twig`).
- **Added**: `App\Service\RegisterFormAction`/`ResetPasswordRequestFormAction` (scaffold, `c975L\UiBundle\Contract\FormActionInterface` keys `register`/`reset_password_request`, auto-registered - no DI wiring needed, same mechanism as `SendEmailFormAction`) - they call the same `UserRegistrar`/`EmailVerifier`/`ResetPasswordHelperInterface` the old controller actions did, just from an action instead of a controller. A duplicate registration email now always looks like a success (no account created, no email sent) instead of a field-level "already taken" error - the generic action contract has no way to report a field-specific error, and this matches reset-password's own long-standing "never reveal" stance, so no user enumeration either way.
- **Changed**: `DefaultPagesImporter`'s seeded `register`/`reset_password_request` `Form` rows now carry `action: 'register'`/`'reset_password_request'` (previously `null`) - their `ensureRegisterFormExists()`/`ensureResetPasswordRequestFormExists()` self-heal methods are gone (no more per-request controller calling them); backfilling for an existing site happens through `import()` itself now, unconditionally, whether the page is new or not - see the `DefaultPagesImporter` entry above.
- **Changed**: rate limiting is now the shared `limiter.ui_form` (see `RateLimiterGuard`), not a dedicated `limiter.registration`/`limiter.reset_password` - remove those two entries from `config/packages/rate_limiter.yaml` if you had them, add `ui_form` instead (see the bundle README's "Registration anti-spam protections" section).
- **Changed**: `user-registration-enabled` ConfigBundle key is gone, replaced by `Form::$enabled` on the "register" `Form` (a checkbox on `FormCrudController`, or from the dashboard's existing "enable/disable registration" shortcut, now backed by the `Form` instead) - see UiBundle's own UPGRADE.md. Remove the `user-registration-enabled` entry from your `config/configs.json`/`config/site-create-questions.json` if you keep a local copy.
- **Changed**: `PageRepository::findOneByBlockKind(string $kind)`/`site_page_for_block(kind)` Twig function are renamed `findOneByFormBlockName(string $formName)`/`site_page_for_form_block(name)` - register/reset_password_request no longer have their own Block kind to look up by, only a `Form` name (same as "contact"). Update any custom template using the old function name.
- **Data migration required if your site already had a `register`/`reset_password_request` Block** (i.e. you'd already adopted the previous "dedicated Block kind" entry above): those Block *rows* still carry the old `kind` value, which is no longer a registered Block kind - `import()`'s backfill only fixes the `Form`'s `action`, it never rewrites existing Block content. Run once per such Block:

  ```sql
  UPDATE site_block SET kind = 'form', data = '{"name":"register"}' WHERE kind = 'register';
  UPDATE site_block SET kind = 'form', data = '{"name":"reset_password_request"}' WHERE kind = 'reset_password_request';
  ```

  Without this, the page 500s with `Unknown block: register`/`reset_password_request` (`c975L\UiBundle\Registry\BlockRegistry`). A site adopting register/reset-password-request for the first time (never had the old Block kind) doesn't need this - `DefaultPagesImporter` seeds the `form` Block directly.

To adopt on an existing site: delete the removed files above, re-copy `RegistrationController.php`/`ResetPasswordController.php` and add `App\Service\RegisterFormAction`/`ResetPasswordRequestFormAction` from the bundle's `scaffold/`, run `doctrine:migrations:diff`/`doctrine:migrations:migrate` for `Form::$enabled` (see UiBundle's UPGRADE.md), run the `UPDATE site_block` statements above if applicable, then re-run `c975l:site:pages:import-defaults` once to backfill the `register`/`reset_password_request` `Form`s' new `action`.

`c975L\UiBundle\Form\FormSubmissionType` gained new field types (`password`, `password_repeated`, `url`, `tel`, `number`, `date`, pickable from any Form's admin screen) and now attaches `Assert\Email` + the new `DnsEmail` constraint to every `email`-typed field automatically (previously neither ran server-side on a generic Form's email field, HTML5 `type="email"` only) - if you have a generic Form with an email field pointed at a domain that can't be reasonably expected to resolve (internal testing domains etc.), submissions against it will now be rejected. A required checkbox field now correctly requires it to be checked (`IsTrue`) instead of silently accepting an unchecked box (`NotBlank` doesn't consider `false` blank) - if you relied on that bug, update accordingly.

`c975L\SiteBundle\Service\FormBotProtection` is gone, moved to `c975L\UiBundle\Service\FormBotProtection` (now shared with UiBundle's own `form` Block instead of duplicated). If your app imports it directly (e.g. a custom copy of the scaffold controllers), update the `use` statement - nothing else about its API changed. Requires `c975l/ui-bundle` with the `Service\FormBotProtection` class (not yet in a tagged release at the time of writing).

**`registration/register.html.twig`/`reset_password/request.html.twig` are removed.** `/register`/`/reset-password` were the only way to reach these forms in English while the actual Page-based ones already have translated slugs (`creer-un-compte`/`mot-de-passe-oublie`, `crear-una-cuenta`/`contrasena-olvidada`...) - now that both forms are also reachable as a Block on a real Page (see above), the bare routes have no reason left to render a full page. `RegistrationController::register()`/`ResetPasswordController::request()` now behave like `c975L\UiBundle\Controller\FormController::submit()` does for its own generic Form: on success, redirect to the `Referer` (same-origin checked) instead of a fixed route, falling back to `page_home`/`app_check_email`; on an invalid submission (or a direct GET), render the bare `_form.html.twig`/`_request_form.html.twig` partial directly (no layout - it now shows its own flashes, since it can no longer rely on `layout.html.twig`'s generic flash block always being there). To adopt on an existing site:

- Delete `templates/registration/register.html.twig`/`reset_password/request.html.twig`.
- Re-copy `RegistrationController.php`/`ResetPasswordController.php` and `registration/_form.html.twig`/`reset_password/_request_form.html.twig`/`reset_password/check_email.html.twig` from the bundle's `scaffold/`, or apply the same change by hand (inject `c975L\SiteBundle\Repository\PageRepository` into both controllers; the honeypot/rate-limit/success redirects now call a small `redirectAfterSuccess()`/`redirectToReferer()` helper instead of `redirectToRoute('app_register'|'app_forgot_password_request')` - see the bundle's own scaffold files for the exact diff).
- New `PageRepository::findOneByBlockKind(string $kind): ?Page` and `site_page_for_block(kind)` Twig function (see `PageExtension`) - resolves the published Page carrying a given Block kind, used by `check_email.html.twig`'s "try again" link to point at the real Page instead of the bare route (falls back to the bare route if no Page carries that Block - e.g. the admin removed it).
- `/login` itself is unaffected - Symfony Security needs a real, fixed login route/page, `SecurityController.php` doesn't change. Its template's "forgot password"/"create account" links now resolve the real register/reset-password Page via `site_page_for_form_block('register'|'reset_password_request')` instead of the removed `app_register`/`app_forgot_password_request` routes, falling back to the generic `ui_form_submit` route if the admin removed that Page - re-copy `security/login.html.twig` from the bundle's `scaffold/` to pick this up, or apply the same change by hand.

**`MenuLinkType`'s `asCopyright` checkbox is removed**, replaced by a single `site-menu-link-copyright-auto` ConfigBundle setting (default `true`). A `menu_link` now shows the live computed copyright as its label automatically whenever it targets the site's own "Copyright" page (the one `DefaultPagesImporter` seeds under the `france/copyright` model - `copyright`/`copyright-notice`/`aviso-de-copyright` depending on locale), instead of needing that checkbox ticked. If your app keeps its own local copy of `config/configs.json` (rather than using the bundle's as-is), add the new `site-menu-link-copyright-auto` entry yourself - not synced automatically. Check any footer/navbar `menu_link` that previously relied on `asCopyright`: if its target page's slug was renamed away from the imported default, or if `site-menu-link-copyright-auto` is turned off, it now falls back to showing that page's own title instead of the computed copyright. `menu_link` also gained an optional `label` field (overrides the auto-derived title/copyright/section label) - nothing to do, existing blocks are unaffected since it's empty by default.

**The new "Health check" feature** (`c975l:health-check:run`, its 9 `HealthCheckProviderInterface` implementations, the Page edit screen's new "Health check" tab) **requires `c975l/config-bundle` with `Management\HealthCheckProviderInterface`/`HealthCheckAdviceProviderInterface`/`HealthCheckRunner`, `Entity\HealthCheckResult` and `Repository\HealthCheckResultRepository`** (not yet in a tagged release at the time of writing) - the container fails to compile without them, so don't upgrade past this point until `c975l/config-bundle` ships it.

**Dragging an already-saved block into a different section** (`PageCrudController`/`MenuCrudController`'s "blocks" field, `SiteBlockOwnerResolver`) **requires `c975l/ui-bundle` with `Contract\BlockOwnerResolverInterface`** (not yet in a tagged release at the time of writing) - the container fails to compile without it too.

## > v7.x

- Page templates were renamed from "page templates" to plain "templates", now entirely decoupled from theme presets: `config/page-templates/*.json` → `config/templates/*.json` (`agency-home-warm` → `agency-home`, `portfolio-blueprint` → `portfolio-showcase`, plus a new generic `default` template), `PageTemplateProviderInterface`/`SitePageTemplateProvider`/`PageTemplateRegistry`/`PageTemplateApplier` → `TemplateProviderInterface`/`SiteTemplateProvider`/`TemplateRegistry`/`TemplateApplier`, the `c975l.page_template_provider` tag → `c975l.template_provider`, and the `c975l:site:pages:apply-template` command → `c975l:site:templates:apply`. If you contribute your own templates or inject any of these classes, update accordingly. A theme preset's `config/themes/*.json` no longer accepts a `pageTemplate` key - remove it if you had one, it's no longer read
- Run `php bin/console doctrine:migrations:diff && php bin/console doctrine:migrations:migrate` to add the new `replaces`/`archivedSlug` columns on `site_page` (see "Applied template as a copy" / "publishAsReplacement" in the ChangeLog)
- If any site already set `theme-stylesheet` to `warm-artisan`, reset it to `warm` (the preset and its shape stylesheet were renamed) - e.g. via the theme admin screen, or directly in the `site_config` table
- The `warm` and `blueprint` theme presets were removed (`default` is the only one shipped now): `config/themes/{warm,blueprint}.json` and their `sass/page-templates/{warm,blueprint}.scss` shape stylesheets are gone. If any site set `theme-stylesheet` to `warm` or `blueprint`, reset it to `default` (or empty, same effect) - the compiled CSS it pointed to no longer exists, so the site would otherwise silently fall back to the `default` shape anyway (same `--radius-*`/navbar/footer tokens as `_variables.scss`'s own base values) without the config reflecting it. `sass/page-templates/` itself was renamed to `sass/themes/` - update any local override that referenced the old path
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
