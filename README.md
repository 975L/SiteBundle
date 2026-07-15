# SiteBundle

Symfony bundle that provides a complete foundation for building websites — layout, pages, SEO, admin, sitemap, legal templates, and more.

[![GitHub](https://img.shields.io/github/license/975L/SiteBundle)](https://github.com/975L/SiteBundle/blob/master/LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/c975l/site-bundle)](https://packagist.org/packages/c975l/site-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/c975l/site-bundle)](https://packagist.org/packages/c975l/site-bundle)

---

## Features

- **Base layout** with SEO-optimized meta tags (OpenGraph, robots, canonical, favicon, Apple touch icon)
- **Page display** from Twig templates (file-based) or from the database via the `Page` entity
- **Page redirects** and 410 Gone handling
- **Admin CRUD** for database pages via EasyAdmin
- **Admin CRUD** for users via EasyAdmin, with role management
- **Admin CRUD** for the site's navbar/footer menus and the email header/footer via EasyAdmin
- **Admin CRUD** for the site's graphics (favicon, Apple touch icon, logo, default Open Graph image) via EasyAdmin
- **Sitemap generation** from both filesystem templates and database pages, with a "Regenerate sitemap" dashboard shortcut
- **Error page templates** for 401, 403, 404, 410, and 500
- **Legal model templates** for France (French): cookies, copyright, legal notice, privacy policy, terms of sales, terms of use
- **Matomo analytics** integration
- **CookieConsent** integration
- **Alternate language** hreflang meta tags
- **Open Graph** image support
- **Email templates** with CSS inlining
- **Asset serving** controller (inline display, access-protected)
- **File download** controller (forced download, access-protected)
- **Twig extensions**: `route_exists`, `template_exists`, `asset_exists`, `nl2br`
- **File lists**: `extensions.txt` and `bots.txt`

---

## Requirements

- PHP >= 8.1
- [c975L/ConfigBundle](https://github.com/975L/ConfigBundle)
- [c975L/UiBundle](https://github.com/975L/UiBundle)
- Doctrine ORM
- EasyAdmin
- symfony/ux-twig-component
- twig/cssinliner-extra

---

## Installation

### Download

```bash
composer require c975l/site-bundle
```

### Load configuration values

This bundle uses [c975L/ConfigBundle](https://github.com/975L/ConfigBundle) to manage its settings. Load the default configuration keys into the database:

```bash
php bin/console c975l:config:load-all
```

Then open the ConfigBundle dashboard to set values for the keys

### Enable routes

Add the bundle routes to `config/routes.yaml`:

```yaml
c975_l_site:
    resource: "@c975LSiteBundle/Controller/"
    type: attribute
    prefix: /
    # For multilingual websites:
    # prefix: /{_locale}
    # defaults:
    #     _locale: '%locale%'
    # requirements:
    #     _locale: en|fr|es
```

### Install assets

```bash
php bin/console assets:install --symlink
```

### Register Stimulus controllers

This bundle ships Stimulus controllers (basic, matomo, cookieConsent). They are exposed via AssetMapper under the `@c975l/site-bundle` namespace.

**Add one entry to `importmap.php`** (one-time, at installation):

```php
'@c975l/site-bundle/controllers.js' => [
    'path' => './vendor/c975l/site-bundle/assets/controllers.js',
],
```

**Add two lines to `assets/bootstrap.js`** (or `assets/stimulus_bootstrap.js`):

```js
import { startStimulusApp } from '@symfony/stimulus-bundle';
import { register as registerc975lSite } from '@c975l/site-bundle/controllers.js';

const app = startStimulusApp();
registerc975lSite(app);
```

After that, all controllers are loaded with hashed filenames (cache busting). Adding or removing controllers in a future bundle update requires no change in your app.

---

## Usage

### Creating your layout

Create `templates/layout.html.twig` in your project and extend the bundle's layout:

```twig
{% extends '@c975LSite/layout.html.twig' %}
```

### Page-specific variables

Declare these variables in each page template to populate meta tags and the page title:

```twig
{% set title = 'My Page Title' %}
{% set summarySocialNetwork = 'A short summary for social networks of this page.' %}
```

### Template blocks

The layout exposes the following Twig blocks for you to override or extend:

| Block | Description |
| --- | --- |
| `head` | Entire `<head>` element |
| `meta` | Meta tags (charset, viewport, robots, og:*, etc.) |
| `stylesheets` | CSS links |
| `preconnect` | `<link rel="preconnect">` hints |
| `body` | Entire `<body>` element |
| `header` | Site header |
| `navigation` | Main navigation |
| `main` | Main content wrapper |
| `title` | Page `<h1>` title |
| `flashes` | Flash messages |
| `container` | Container div wrapping `content` |
| `content` | Page-specific content |
| `share` | Sharing widgets |
| `navigationBottom` | Bottom navigation |
| `footer` | Site footer |
| `javascripts` | JavaScript includes |

**Override a block:**

```twig
{% block share %}
    {{ parent() }}
    {# your additional content #}
{% endblock %}
```

**Disable a block:**

```twig
{% block share %}{% endblock %}
```

### Display mode

Use the `display` variable to conditionally include templates (defaults to `html`):

```twig
{% if display == 'pdf' %}
    {% include 'header-pdf.html.twig' %}
{% else %}
    {% include 'header.html.twig' %}
{% endif %}
```

---

## Pages

### File-based pages

Place Twig templates in `templates/pages/`. They are served at `/pages/{slug}` via the `page_display` route.

To **hint the sitemap generator**, add metadata in a Twig comment at the top of the file:

```twig
{# changeFrequency="monthly" priority="8" #}
```

### Redirects and deleted pages

| Location | Effect |
| --- | --- |
| `templates/pages/redirected/{slug}.html.twig` | Redirects to the slug written inside the file |
| `templates/pages/deleted/{slug}.html.twig` | Throws a 410 Gone exception |

### Database pages

Use the `Page` entity to manage pages through the database. Each page supports:

- Title, slug (unique), summarySocialNetwork
- Published status and display position
- Sitemap fields: change frequency and priority (0–10)
- Blocks (content blocks from [c975L/UiBundle](https://github.com/975L/UiBundle))
- Creation / modification timestamps and author reference

Database pages are rendered with the bundle's `@c975LSite/pages/page.html.twig` template, which displays the page title, summarySocialNetwork, and its associated blocks.

`PageController::home()`/`display()` don't set an HTTP `Cache-Control: max-age` on the response (dropped in favor of UiBundle's per-block server-side cache, see its README's "Block render cache" section: infinite TTL, invalidated on save, shared by every visitor - rather than a per-browser cache with no way to invalidate it early after an edit).

### Blocks defined by this bundle

On top of the generic block system provided by [c975L/UiBundle](https://github.com/975L/UiBundle), SiteBundle registers the following blocks (see `config/services.yaml`):

| Kind | Category | Description |
| --- | --- | --- |
| `legal_model` | `label.category_legal` | Renders one of the built-in legal page models (cookies policy, copyright, legal notice, privacy policy, terms of sales, terms of use), localized under `templates/models/{country}/{model}.html.twig`. Optionally displays a "latest update" date. |
| `twig_content` | `label.category_migration` | Renders raw Twig content stored in the block's data via `template_from_string()`. Intended as a migration/escape hatch for content that doesn't fit another block type. |
| `articles_slider` | `label.category_navigation` | Picks another database page and renders its `article` blocks (that have at least one media) as a clickable slider, using the `<twig:c975LUi:Slider:Slider>` component from UiBundle. |
| `menu_link` | `label.category_navigation` | A link to a published `Page` or a bundle-contributed route (see [Linking to a bundle's own route](#linking-to-a-bundles-own-route)); this is how `Menu` rows (navbar/footer/email-header/email-footer, see [Menus](#menus)) build their navigation, sortable alongside any other block. Restricted to the `menu` context (UiBundle's `contexts` tag attribute, requires `c975l/ui-bundle` with context-aware `BlockRegistry::groupedByCategory()`), so it isn't offered when picking blocks for a `Page`. Not cacheable: its "active" state depends on the current request path. |

Each block is registered as a `ui.block`-tagged service, with a dedicated form (`c975L\SiteBundle\Form\Block\*Type`) and template (`templates/blocks/*.html.twig`). The `articles_slider` block relies on the `site_page(id)` Twig function (`PageExtension`) to eager-load the target page along with its blocks and medias. Like any `ui.block`, `menu_link` is `pickable: true` and has no context restriction, so it also shows up in a `Page`'s own block picker - harmless (it just renders a link) but not the intended use.

### Admin management

Pages are managed in the EasyAdmin dashboard via `PageCrudController`. The menu entry is registered automatically through `MenuProvider`. Access is controlled by the `site-role-editor` key in ConfigBundle.

### Page templates

`config/page-templates/*.json` ships reusable, ordered arrangements of blocks (kind + example data) an admin can bulk-add to a `Page` — a starting point to edit from, not a live/synced layout. Listed via `SitePageTemplateProvider`, applied via `PageCrudController`'s per-template "Apply template" action (appends the template's blocks after whatever the page already has, doesn't replace anything) or the `c975l:site:pages:apply-template` command (see [Commands](#commands)) for scripted use across several pages/sites at once. Both share the same `PageTemplateApplier`.

A page template stays independent from a [theme preset](#themes)'s colors/fonts/shape — a preset can optionally reference one (its `pageTemplate` key) purely to demo its full look in `?preset=` preview, but applying either one never touches the other.

---

## Menus

The site-wide navbar, footer, email header and email footer are managed entirely from the database — no app-side template override needed. Each is a `Menu` (`location`: `navbar`, `footer`, `email-header` or `email-footer`, one row per location, same singleton pattern as the site-wide graphics managed via `SiteGraphicCrudController`).

Every location owns a single ordered `blocks` collection (same generic UiBundle `Block` system as `Page`, see [Blocks defined by this bundle](#blocks-defined-by-this-bundle)) — menu links and any other registered block kind (e.g. SocialBundle's `social_links_display`) are freely sortable together, no separate "items" collection to keep in sync. A menu link is itself a block, of kind `menu_link` (form: `MenuLinkType`), targeting either:

- an existing **published** `Page` (linked by its id, so renaming the page's slug never breaks the link) or
- a route contributed by another bundle (see [Linking to a bundle's own route](#linking-to-a-bundles-own-route) below)

`menu_link` resolves to a relative URL (`UrlGeneratorInterface::generate()`), fine for `navbar`/`footer` but not usable as-is inside an email — `email-header`/`email-footer` are meant for content that doesn't need one (e.g. social icons, legal blurbs), not for reusing the site's own links.

Managed via `MenuCrudController` (drag-and-drop reordering, same mechanism as [Blocks](#blocks-defined-by-this-bundle)). Access is controlled by the `site-role-editor` key in ConfigBundle.

`navbar` and `footer` are rendered by built-in components already wired into the bundle's layout (`navigation`/`footer` blocks) — nothing to add in your app:

```twig
<twig:c975LSite:General:Navbar/>
<twig:c975LSite:General:Footer copyright="{{ copyright }}"/>
```

`email-header` and `email-footer` are rendered the same way inside `@c975LSite/emails/header.html.twig` and `@c975LSite/emails/footer.html.twig` respectively (see [Email templates](#email-templates)), independently from the site's own `navbar`/`footer` — each location is edited separately, so the client can keep different content for emails than for the site.

A block disappears from the rendered menu automatically (no dangling link) if its `menu_link` targets a page that's later unpublished/deleted, or a route whose contributing bundle is removed.

### Navbar: logo, site name, tagline

`Navbar` reads `site_media('logo')`, `config('site-name')` and `config('site-tagline')` — nothing to pass in. `site-name` stays mandatory (used across meta tags, page titles, etc.), but showing it in the navbar specifically is optional via the `site-navbar-show-name` ConfigBundle key (`bool`, default `true`).

The navbar can be kept fixed at the top of the viewport while scrolling via the `site-navbar-fixed` ConfigBundle key (`bool`, default `false`). When enabled, `.menu-fixed` is added on the `<nav>` and a `navbar-fixed` class is added on `<body>` to compensate the space it frees from the normal flow.

`site-tagline` is authored as rich text in the backoffice (Trix wraps the value in its own `<div>`), so it's rendered with `|raw` — style `.menu-site-tagline` in your own SCSS if you need to adjust it.

### Linking to a bundle's own route

A `menu_link` block isn't limited to database pages. Any bundle can expose one of its own front-end routes (e.g. ContactFormBundle's `/contact`) as a selectable target by implementing ConfigBundle's `LinkableRouteProviderInterface` — see [ConfigBundle's README](https://github.com/975L/ConfigBundle#contributing-linkable-routes-for-sitebundle-menus) for how to write the provider. This is how ContactFormBundle exposes its contact page; the same approach will apply to ShopBundle and BookBundle.
A `menu_link` block isn't limited to database pages. Any bundle can expose one of its own front-end routes (e.g. ContactFormBundle's `/contact`) as a selectable target by implementing ConfigBundle's `LinkableRouteProviderInterface` — see [ConfigBundle's README](https://github.com/975L/ConfigBundle#contributing-linkable-routes-for-sitebundle-menus) for how to write the provider. This is how ContactFormBundle exposes its contact page; the same approach will apply to ShopBundle and BookBundle.

Since login/register/logout/forgot-password aren't SiteBundle routes but scaffolded straight into `App\Controller` (see [Users](#users) below), the scaffold also ships `App\Management\LinkableRouteProvider`, so `app_login`, `app_logout`, `app_register` and `app_forgot_password_request` show up in the `menu_link` picker out of the box — re-run the scaffold install (or copy the file by hand) on sites that predate it. `app_verify_email`, `app_check_email` and `app_reset_password` are deliberately left out: they only make sense reached through a signed link, not as a standalone menu target.

### Social links

Social icons in the footer (site or email) are no longer a dedicated component/config toggle — they're a regular block, dropped into the footer's own `blocks` collection like any other. See [c975L/SocialBundle](https://github.com/975L/SocialBundle)'s README for the `social_links`/`social_links_display` block kinds it registers.

---

## Users

`App\Entity\User` is managed in the EasyAdmin dashboard via `UserCrudController`. The menu entry is registered automatically through `MenuProvider`. Access is controlled by the `site-role-admin` key in ConfigBundle, same as pages.

The controller relies on EasyAdmin's auto-discovery of the app's own `User` fields (which vary per app), except for:

- The hashed password field, excluded so it's never displayed or overwritten from the backoffice
- The `roles` field, added explicitly as a multiple-choice field, since JSON columns are never auto-discovered by EasyAdmin
- The `creation` / `modification` fields, made readonly since they're set automatically
- The `isVerified` field, made readonly since it must only be set by `EmailVerifier` upon email confirmation, never edited by hand from the backoffice

`ROLE_USER` is always excluded from the choices (every user already has it by default, see `User::getRoles()`). The other selectable roles come from the `user-roles-available` ConfigBundle key (`json` kind, e.g. `["ROLE_ADMIN","ROLE_EDITOR"]`) — add roles for your app there, no code change needed.

The detail page is disabled (not useful on top of the index and edit pages).

### ROLE_SUPER_ADMIN and restricted configs

Requires `c975l/config-bundle` >= v5.4.

Some configs are shared server-level secrets rather than per-site application settings — for
example `site-backup-db-user`/`site-backup-db-password`, used by `c975l:site:backup` (see
[Commands](#commands)): a single privileged MySQL user reused to back up the database, not
something a client's own site admin should ever be able to read or overwrite. ConfigBundle flags
these with `"restricted": true` in
`configs.json`; any config so flagged is hidden entirely (index, detail, edit, and export) from
every user except one holding `ROLE_SUPER_ADMIN`, regardless of `site-role-admin`.

`c975l:site:create` grants `ROLE_SUPER_ADMIN` (together with `ROLE_ADMIN`) to the bootstrap user
automatically, since whoever runs it owns the site. When you (the producer) deploy a client's site,
run `site:create` yourself to become its super-admin, then create the client's own users with plain
`ROLE_ADMIN` via the User CRUD — they get full access to pages, menus, general configs, etc., but
the `backup` config group stays out of their reach. A standalone install where you're the only user
is never affected, since your own bootstrap account already holds both roles.

To make your own bundle's configs restricted the same way, just add `"restricted": true` next to
`"sensitive"` in its `configs.json` entry. `site-role-admin` and `user-roles-available` are
restricted too: they gate the whole admin and decide which roles exist, so a plain `ROLE_ADMIN`
must never be able to touch them.

That last point matters for the role picker itself: `UserCrudController` strips `ROLE_SUPER_ADMIN`
from the choices offered on the `roles` field unless the acting user already holds it — server-side,
not just visually, so Symfony's `ChoiceType` rejects a crafted submission trying to sneak it in
anyway. Without this, listing `ROLE_SUPER_ADMIN` in `user-roles-available` would let any
`ROLE_ADMIN` grant it to themselves through the User CRUD and bypass every restricted config in one
step.

### Disabling registration

The `user-registration-enabled` ConfigBundle key (`bool`, default `false`) lets you turn the app's registration route on or off without a deployment. This bundle doesn't provide a `RegistrationController` (it's generated by `symfony/make` in your app), so wire the check yourself at the top of its `register` action:

```php
// Access denied if registration is disabled in the configuration
if (false === $this->configService->get('user-registration-enabled')) {
    throw $this->createAccessDeniedException();
}
```

### Registration anti-spam protections

The scaffolded `RegistrationFormType`/`RegistrationController` and `ResetPasswordRequestFormType`/`ResetPasswordController` reject bots at several layers, so a public form doesn't turn into a way to farm confirmation emails towards throwaway domains:

- **`Assert\Email` + `App\Validator\Constraints\DnsEmail`** on `User::$email` — format check, then a live MX/A DNS lookup (via `egulias/email-validator`, already a transitive dependency of `symfony/mailer`, nothing to install) rejecting domains that can't receive mail at all (e.g. `something@dominatingkeywords.com`). Runs on every entity validation, including the User CRUD in the backoffice.
- **Honeypot + minimum submit delay** — an invisible `website` field (hidden inline, no CSS dependency) on both forms, and a minimum delay between displaying the form and submitting it, tracked in session. Either one failing silently redirects (home for registration, the usual "check your email" page for the reset request) without creating an account or sending any email, giving no signal back to the bot. The delay is the shared `site-form-delay` ConfigBundle key (seconds, default `3`) - also used by ContactFormBundle's own anti-bot check, so it's one setting for every public form instead of one per bundle.
- **GDPR consent checkbox** - shown on both forms (unmapped `gdpr` field, reusing ContactFormBundle's `text.gdpr` translation) when the shared `site-form-gdpr` ConfigBundle key (bool, default `true`) is enabled.
- **Rate limiting by IP** — requires a `registration` and a `reset_password` limiter, not wired automatically since scaffold only copies `src/`/`templates/`/`tests/`/`translations/`, never `config/` (the `symfony/rate-limiter` package itself is already a dependency of this bundle, nothing to install there):

```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        registration:
            policy: sliding_window
            limit: 5
            interval: '10 minutes'
        reset_password:
            policy: sliding_window
            limit: 5
            interval: '10 minutes'
```

Without this config, both controllers fail to boot (`limiter.registration`/`limiter.reset_password` service not found) - add it right after installing the scaffold.

### Login throttling

`c975l:site:create` also inserts `login_throttling: { max_attempts: 5 }` onto the `main` firewall in `config/packages/security.yaml` (same step as `user_checker` above), using Symfony's built-in rate limiter for `/login` - no custom code involved. If your site predates this or uses a differently-named firewall, add it yourself:

```yaml
security:
    firewalls:
        main:
            login_throttling:
                max_attempts: 5
```

### Account activation (`isEnabled`)

`App\Entity\User::isEnabled` gates login independently from `isVerified`. `EmailVerifier::handleEmailConfirmation` (scaffolded) sets both `isVerified` and `isEnabled` to `true` once the user confirms their email — `c975l:site:create` does the same for the bootstrap admin account, since there's no email to confirm.

The scaffold ships `App\Security\UserChecker`, which refuses login with Symfony's built-in `DisabledException` ("Account is disabled.", already translated in `security.*.xlf` for en/fr/es, and rendered for free by the scaffolded `login.html.twig`) as soon as `isEnabled` is `false` — before the password is even checked. `c975l:site:create` registers it on the `main` firewall automatically (step 1, right after the scaffold install), by inserting `user_checker: App\Security\UserChecker` into `config/packages/security.yaml` if it isn't already there. If your site predates this or uses a differently-named firewall, add it yourself:

```yaml
security:
    firewalls:
        main:
            user_checker: App\Security\UserChecker
```

This lets you disable a user from the backoffice (`isEnabled` isn't readonly, unlike `isVerified`) to lock them out without deleting their account — a verified user with `isEnabled = false` still can't log in.

---

## SEO

### Sitemap generation

Run the following command to generate `public/sitemap-pages.xml`:

```bash
php bin/console c975l:site:sitemaps:create
```

The command aggregates URLs from:
1. Twig files in `templates/pages/` (reads `changeFrequency` and `priority` from comments)
2. Published database pages (uses their `changeFrequency` and `priority` fields)

A sitemap index template is also available at `@c975LSite/sitemap-index.xml.twig`.

The same command can also be triggered from the dashboard, via a "Regenerate sitemap" shortcut contributed through ConfigBundle's `ShortcutProviderInterface`.

### Alternate languages (hreflang)

Define `languagesAlt` to add `<link rel="alternate" hreflang="...">` tags and enable a language switcher navbar component:

```twig
{% set languagesAlt = {
    en: { title: 'English' },
    fr: { title: 'Français' },
    es: { title: 'Español' }
} %}
```

URLs are built as `https://example.com/{locale}/pages/{slug}`.

### Open Graph image

Resolved in this order: an `ogImage` variable set by the template/page takes priority, then a database `Page`'s own `ogImage` (settable from `PageCrudController`), then the site-wide default og-image managed via [Site graphics](#site-graphics), then the site's logo.

To override it manually for a file-based page:

```twig
{% set ogImage = absolute_url(asset('images/my-og-image.jpg')) %}
```

---

## Site graphics

The site's favicon, Apple touch icon, logo and default Open Graph image are each a `c975L\UiBundle\Entity\Media` row carrying a `role` (`Media::ROLE_FAVICON`, `ROLE_APPLE_TOUCH_ICON`, `ROLE_LOGO`, `ROLE_OG_IMAGE`) — not plain ConfigBundle text paths. Managed via `SiteGraphicCrudController` (one row per role, uploaded file always saved at a fixed well-known path, e.g. `/favicon.ico`, whatever gets re-uploaded). Dashboard alerts (via ConfigBundle's `AlertProviderInterface`) flag any role not yet uploaded, and UiBundle's Media library shows where each one is used (via `SiteMediaUsageProvider`) — as a site graphic, a page's og-image, or a media attached to a page's block.

Access is controlled by the `site-role-editor` key in ConfigBundle, same as pages.

---

## Themes

The site's colors, fonts and light/dark mode are admin-editable ConfigBundle keys (`group: theme`, see `config/configs-css.json`): `theme-color-primary`, `theme-color-secondary`, `theme-color-primary-dark-mode`, `theme-color-secondary-dark-mode`, `theme-color-background`, `theme-color-text`, `theme-font-family-title`, `theme-font-family-body`, `theme-font-family-accent`, `theme-mode` (`auto`/`light`/`dark`) and `theme-stylesheet` (see below). Managed via ConfigBundle's `ThemeCrudController`, its own dashboard view so it doesn't get mixed up with the general config list.

Every change is compiled by `ThemeVariablesCssListener` (a Doctrine listener, also a `CacheWarmerInterface` so a fresh `public/bundles/build/site-theme.css` exists after a deploy even without an admin re-saving anything) into `--c975l-*` CSS custom properties, loaded right after `styles.min.css` so they win the cascade over the built-in defaults. The same compiled file is inlined into emails via the `theme_variables_css()` Twig function — no more per-app `_user-variables.css`/`_user-typography.css` override stubs to keep in sync, the backoffice is now the single source of truth for both the site and its emails. Because the real site links UiBundle's concatenated `bundles/build/site.css` rather than `site-theme.css` directly, the listener also calls UiBundle's `StylesheetCacheWarmer::compileAll()` after every regeneration, so a theme change (or applying a preset) is reflected immediately instead of waiting for the next `cache:warmup`.

`theme-mode: dark` (or `auto` following the visitor's OS preference via `prefers-color-scheme`) swaps in a dark palette (see `sass/_theme-dark.scss`); `theme-color-primary-dark-mode`/`-secondary-dark-mode` optionally override just the accent colors for dark mode, falling back to the light-mode ones otherwise.

### Presets

`config/themes/*.json` ships ready-made combinations of the keys above (colors, fonts, and optionally a page-template "shape" stylesheet, see below) as one-click presets, listed via `SiteThemePresetProvider` (implements ConfigBundle's `ThemePresetProviderInterface`). An admin applies one from the Theme dashboard's "Presets" action group — this bulk-overwrites the `theme-*` configs in a single flush, it never touches page content.

Before committing to one, preview it on any page: `/pages/{page}/preview?preset=<slug>` renders that page with the preset's colors/fonts/shape applied for this request only (nothing written to `site_config`). If the preset also declares a `pageTemplate` (a `config/page-templates/*.json` slug, see [Page templates](#page-templates)), the preview additionally swaps in that template's blocks — transient, never persisted — so the preview shows the preset's full intended look rather than just a reskin of the page's actual content.

### Page-template stylesheets ("shape")

A page template (see below) can ship its own `sass/page-templates/<slug>.scss`, overriding non-color "shape" tokens — border radii, shadows, navbar/footer layout — defined in `sass/_variables.scss`. Setting `theme-stylesheet` (done automatically when applying a preset that declares one) loads `bundles/c975lsite/css/page-templates/<slug>.min.css` after `site-theme.css`, so it can override those tokens on top of the admin's colors/fonts.

---

## General components

All components below read their data from ConfigBundle. No props are needed — just include the tag and set the corresponding keys via the ConfigBundle dashboard.

### Matomo

Set `site-matomo-url` and `site-matomo-id` in ConfigBundle, then place the component wherever you want the tracking snippet (typically just before `</body>`):

```twig
<twig:c975LSite:General:Matomo/>
```

The component renders nothing if either config value is missing.

### CookieConsent

Set `url-cookies-policy` in ConfigBundle (optional — links the banner to your cookies page), then place the component in your layout:

```twig
<twig:c975LSite:General:CookieConsent/>
```

The `message`, `dismiss`, and `link` texts are loaded from the `site` translation domain.

### HostedBy / MadeBy

Set `site-hosted-by-url` + `site-hosted-by-logo` and/or `site-made-by-url` + `site-made-by-logo` in ConfigBundle, then include the components (typically in the footer):

```twig
<twig:c975LSite:General:HostedBy/>
<twig:c975LSite:General:MadeBy/>
```

Each component renders nothing if either its URL or logo config value is missing.

### Preconnect

Set `site-preconnect` in ConfigBundle to a JSON array of external origins to preconnect to, i.e. `["https://975l.com"]`. Useful when `HostedBy`/`MadeBy` logos or Matomo are served from a third-party domain. Empty by default, so it has no effect unless configured.

---

## Error templates

Pre-built error templates are available for: `error`, `error401`, `error403`, `error404`, `error410`, and `error500`.

Follow the Symfony guide on [customizing error pages](https://symfony.com/doc/current/controller/error_pages.html), then include the bundle templates in your own error files:

```twig
{% extends 'layout.html.twig' %}

{% block content %}
    {% include '@c975LSite/Exception/error404.html.twig' %}
{% endblock %}

{% block share %}{% endblock %}
```

---

## Legal models

Pre-built legal templates are available for **France** in **French** (`fr`). Available models:

| Model | Path |
| --- | --- |
| Cookies policy | `@c975LSite/models/france/fr/cookies.html.twig` |
| Copyright | `@c975LSite/models/france/fr/copyright.html.twig` |
| Legal notice | `@c975LSite/models/france/fr/legal-notice.html.twig` |
| Privacy policy | `@c975LSite/models/france/fr/privacy-policy.html.twig` |
| Terms of sales | `@c975LSite/models/france/fr/terms-of-sales.html.twig` |
| Terms of use | `@c975LSite/models/france/fr/terms-of-use.html.twig` |

Each model is also available in Markdown format (`.md`).

**Feel free to contribute translations or add templates for other countries.**

### Include the whole model

```twig
{% extends 'layout.html.twig' %}

{% trans_default_domain 'site' %}
{% set title = 'label.terms_of_sales'|trans %}

{% block content %}
    {% set latestUpdate = '2024-01-01' %}
    {% include '@c975LSite/models/france/fr/terms-of-sales.html.twig' %}
{% endblock %}
```

### Select specific blocks (embed)

```twig
{% extends 'layout.html.twig' %}

{% trans_default_domain 'site' %}
{% set title = 'label.terms_of_sales'|trans %}

{% block content %}
    {% set latestUpdate = '2024-01-01' %}
    {% embed '@c975LSite/models/france/fr/terms-of-sales.html.twig' %}
        {# Disable a block #}
        {% block acceptation %}{% endblock %}

        {# Or extend a block #}
        {% block acceptation %}
            {{ parent() }}
            Additional content here.
        {% endblock %}
    {% endembed %}
{% endblock %}
```

---

## Asset and Download controllers

### AssetController

Serves a file inline (e.g., images, PDFs). Useful for serving files only to authenticated users.

```twig
{{ path('asset_file', { file: 'path/to/your_file.pdf' }) }}
```

To restrict access, add an entry to `config/packages/security.yaml`:

```yaml
access_control:
    - { path: ^/asset/protected/, roles: ROLE_USER }
```

### DownloadController

Forces a file download.

```twig
{{ path('download_file', { file: 'path/to/your_file.csv' }) }}
```

File names may contain letters (including accented), digits, `-`, `_`, `/`, and up to two extensions. Spaces are not allowed.

---

## Twig extensions

| Function / Filter | Description |
| --- | --- |
| `route_exists('route_name')` | Returns `true` if the named route exists |
| `template_exists('template.html.twig')` | Returns `true` if the template file exists |
| `asset_exists('path/to/file')` | Returns `true` if the asset exists in `public/` or `assets/` |
| `\|nl2br` | Applies PHP's `nl2br()` with HTML output safe |
| `\|linkify` | Turns bare `http(s)://` URLs in a raw string into `<a target="_blank" rel="noopener noreferrer">` links, HTML-escaping the rest |
| `theme_variables_css()` | Returns the CSS compiled by `ThemeVariablesCssListener` from the admin-editable theme configs (see [Themes](#themes)), for inlining where a `<link>` isn't possible (e.g. emails) |

---

## Email templates

Pre-built email templates are available at `@c975LSite/emails/`:

| Template | Description |
| --- | --- |
| `layout.html.twig` | Base email layout |
| `fullLayout.html.twig` | Full email layout |
| `header.html.twig` | Email header — renders the `email-header` Menu's blocks (see [Menus](#menus)), edited from the backoffice, independently from the site navbar |
| `footer.html.twig` | Email footer — renders the `email-footer` Menu's blocks (see [Menus](#menus)), edited from the backoffice, independently from the site footer |

CSS is inlined automatically via `twig/cssinliner-extra`. The minified stylesheet (`emails.min.css`, compiled from `sass/emails.scss`, including its `:root` variables) is embedded, followed by the admin-editable [theme](#themes) colors/fonts (`theme_variables_css()`) so they win the cascade.

`fullLayout.html.twig`'s own copy (no-spam notice, "hello", closing/thanks, "sent by", legal mentions) isn't hardcoded translations — it's authored as rich text (`kind: html`) directly in ConfigBundle, under the `email` group: `email-text-no-spam`, `email-text-hello`, `email-text-closing`, `email-text-sent-by`, `email-text-legal`. Each block only renders if its config value is non-empty (`email-text-legal` is empty by default). `email-text-closing` and `email-text-sent-by` support a `%site%` placeholder, replaced with `site-name`. This lets the client rewrite their own email copy, including in `email-text-legal` (share capital, registration number...), from the backoffice without touching translations or templates.

---

## CSS animations

Link the animations stylesheet to use scroll-triggered CSS animations:

```twig
<link rel="stylesheet" href="{{ asset('bundles/c975lsite/css/animations.min.css') }}">
```

---

## Commands

| Command | Description |
| --- | --- |
| `php bin/console c975l:site:create` | Interactive wizard that bootstraps a new site (scaffold, admin user, config, default pages); runs once per repo |
| `php bin/console c975l:scaffold:install` | Re-runnable: (re)installs every installed c975L bundle's scaffold files into the project |
| `php bin/console c975l:site:sitemaps:create` | Generates `public/sitemap-pages.xml` from filesystem and database pages |
| `php bin/console c975l:site:backup` | Backs up the database and `public/` files |
| `php bin/console c975l:site:pages:import-defaults` | Creates default pages (home, legal notice, privacy policy, CGU, CGV, cookies) if they do not already exist |
| `php bin/console c975l:site:pages:apply-template <template> <page>` | Creates or updates a page from a [page template](#page-templates) (`--title`, `--replace`, `--publish` options) |
| `php bin/console c975l:site:messenger-cleanup` | Purges old failed Messenger messages and alerts admins of new important ones |

### Create a new site

`c975l:site:create` is the single entry point used to bootstrap a brand new site. Run it once
`make:user`, `make:registration-form` and `make:reset-password` have been run (it needs
`App\Entity\User` to already exist):

```bash
php bin/console c975l:site:create
```

**One-shot only**: on success it writes a `.c975l-site-created` marker at the project root and
commits — sorry, expects *you* to commit it — with the rest of the repo (it is not gitignored on
purpose, so the guard survives `git clone`/deploy, not just a re-run on the same machine). Any
further run refuses immediately with an error as long as that file exists. This exists because
step 1 (scaffold) unconditionally overwrites any matching `src/`/`templates/` file — including
ones you've since customized — and re-running isn't a supported way to add things to an existing
site. To add config values, pages or menu items later, run the underlying commands directly
(`c975l:config:load-all`, `c975l:site:pages:import-defaults`, ...) instead of this wizard. If you
genuinely need to re-run the whole wizard (e.g. resetting a throwaway dev environment), delete
`.c975l-site-created` first.

It walks through, in order:

1. **Scaffold install** — copies `scaffold/src` and `scaffold/templates` from every installed
   c975L bundle (`vendor/c975l/*/scaffold`) into the project. Unlike a plain overwrite, any file
   it would replace is first moved to `existingFiles/<same path>.old` at the project root, so
   nothing generated by `make:*` is silently lost. Add `existingFiles/` to your own workflow if
   you don't want it committed (the command adds it to `.gitignore` automatically).
2. **Default config** — runs `c975l:config:load-all` internally.
3. **Vault key** — generates and writes `C975L_VAULT_KEY` to `.env.local` if it isn't defined yet.
4. **Admin account** — asks for an email and password (typed in clear text, not masked, so you can
   see what you're entering — it is never echoed back afterwards, including in the final summary),
   creates the user with `ROLE_ADMIN`, `isVerified = true`, `isEnabled = true`.
5. **Config values** — asks for the values listed in `config/site-create-questions.json` (see
   below), validated against each config's `kind`.
6. **Default pages** — same as `c975l:site:pages:import-defaults` below, but page by page:
   confirms the import and the initial `isPublished` state for each page not already in database.
7. **Footer menu** — offers to add, one by one (yes by default), every bundle-contributed route
   registered via `LinkableRouteProviderInterface` (e.g. ContactFormBundle's contact page, only
   proposed if that bundle is installed), then the legal pages just imported, in a fixed order
   (mentions légales, règles de confidentialité, CGU, CGV, cookies, copyright). Re-running the
   command never creates duplicate items.

#### `config/site-create-questions.json`

A flat, ordered JSON array of config slugs to ask about — edit it freely to add, remove or
reorder questions:

```json
["site-name", "site-url", "site-author", "..."]
```

A slug can belong to any installed c975L bundle (looked up by slug in the database, regardless
of which bundle's `configs.json` defined it); slugs that aren't found (bundle not installed, or
`config:load-all` hasn't run yet) are skipped with a warning.

### Installing a bundle's scaffold on an existing site

`c975l:scaffold:install` is the standalone, re-runnable equivalent of step 1 of `c975l:site:create`
(the one gated by `.c975l-site-created`). Use it whenever you `composer require` a c975L bundle
into a site that's already past the one-shot wizard, to pull in that bundle's
`scaffold/{src,templates,tests,translations}` files:

```bash
php bin/console c975l:scaffold:install
```

Same backup behavior as the wizard: any target file it would overwrite is moved to
`existingFiles/<same path>.old` first (never silently erased), and a target already identical to
the scaffold source is left untouched — so running it again on an unmodified project is a no-op.

#### Translating the questions

Each question's text is the config's `description`, translated through the shared `site_config`
translation domain. Since Symfony merges translation files sharing the same domain and locale
across every bundle's own `translations/` directory, any c975L bundle can contribute to it
independently — SiteBundle doesn't need to know about the others. To make a bundle's own configs
show up as readable questions instead of raw text:

1. In that bundle's `config/configs.json`, replace the `description` value with a translation key,
   i.e. `"description": "label.my_slug"`.
2. Add the corresponding entries to `translations/site_config.en.xlf`, `.fr.xlf` and `.es.xlf` in
   that same bundle.

This is backward-compatible: `trans()` on a plain-text `description` that isn't a known
translation key simply returns it unchanged, so bundles that haven't migrated yet still display
correctly, just untranslated.

### Import default pages

Run once after setting up a new site to pre-populate the database with common pages:

```bash
php bin/console c975l:site:pages:import-defaults
```

One page is created per locale — `%kernel.default_locale%` plus every locale listed in `framework.enabled_locales` (falling back to just the default locale if that list is empty):

| Slug (fr) | Slug (en) | Slug (es) | Title (fr) | Block |
| --- | --- | --- | --- | --- |
| `home` | `home` | `home` | Accueil | — |
| `mentions-legales` | `legal-notice` | `aviso-legal` | Mentions légales | `legal_model` → `france/legal-notice` |
| `regles-de-confidentialite` | `privacy-policy` | `politica-de-privacidad` | Règles de confidentialité | `legal_model` → `france/privacy-policy` |
| `conditions-generales-d-utilisation` | `terms-of-use` | `condiciones-de-uso` | Conditions générales d'utilisation | `legal_model` → `france/terms-of-use` |
| `conditions-generales-de-vente` | `terms-of-sales` | `condiciones-de-venta` | Conditions générales de vente | `legal_model` → `france/terms-of-sales` (only if c975L/ShopBundle is installed) |
| `cookies` | `cookies-usage` | `uso-de-cookies` | Utilisation des cookies | `legal_model` → `france/cookies` |
| `copyright` | `copyright-notice` | `aviso-de-copyright` | Copyright | `legal_model` → `france/copyright` |

`home` is always the same slug across locales — `PageController` looks it up literally, so only one homepage can ever exist. All pages are created as **unpublished** — review and publish them individually from the admin. Pages whose slug already exists are silently skipped, so re-running the command after adding a new `enabled_locales` entry only creates the missing locale's pages.

### Messenger cleanup

Purges failed `messenger_messages` rows (`queue_name = 'failed'`) older than `site-messenger-cleanup-retention-days` days (default 30). Each failure is classified minor (spam/blacklist-related, matched against the exception message) or important; new important failures since the last alert trigger a single digest email to `site-messenger-cleanup-mailto` (both configs `restricted`, the mailto also `sensitive`, same pattern as the backup mailto — see [ROLE_SUPER_ADMIN and restricted configs](#role_super_admin-and-restricted-configs)), never more than once per new batch.

A dashboard alert (ConfigBundle's `AlertProviderInterface`) also surfaces important failures — full detail (recipient, subject, error) to `ROLE_SUPER_ADMIN`, a plain "already reported" message to `ROLE_ADMIN` — linking to a management page listing them, with a "Purge now" button (`ROLE_SUPER_ADMIN` only) that runs the same cleanup immediately.

---

## Scheduler

The bundle provides `site:sitemaps:create`, `site:backup` and `site:messenger-cleanup` as schedulable commands. The schedule itself is defined in your app so each project controls its own timing.

### 1. Create the schedule class

```php
// src/Scheduler/SiteSchedule.php
namespace App\Scheduler;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('site')]
class MaintenanceSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {}

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->stateful($this->cache)
            // Sitemap: daily at 00:05
            ->add(RecurringMessage::cron('5 0 * * *', new RunCommandMessage('site:sitemaps:create')))
            // Partial backup: every 6 hours (DB regular tables + modified files only)
            ->add(RecurringMessage::cron('7 */6 * * *', new RunCommandMessage('site:backup')))
            // Full backup + report: every Monday at 03:07 (archive tables + whole DB + all user files)
            ->add(RecurringMessage::cron('7 3 * * 1', new RunCommandMessage('site:backup --full --report')))
            // Messenger cleanup: daily at 03:00
            ->add(RecurringMessage::cron('0 3 * * *', new RunCommandMessage('site:messenger-cleanup')));
    }
}
```

The `stateful()` call persists the last-run time via Symfony Cache so tasks are not re-run if the worker restarts.

### 2. Start the worker

Run the consumer as a long-lived process (supervised by Supervisor or systemd):

```bash
php bin/console messenger:consume scheduler_site
```

You may keep a cron entry that restarts the worker daily (e.g., at 00:25) to recover from crashes without monitoring the process continuously:

```bash
25 0 * * * systemctl --user start messenger-worker@your-site.service
```

---

## Lists

Two plain-text lists are available for validation purposes:

```php
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

$extensions = file(
    $this->parameterBag->get('kernel.project_dir') . '/../vendor/c975l/site-bundle/Lists/extensions.txt',
    FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
);

$bots = file(
    $this->parameterBag->get('kernel.project_dir') . '/../vendor/c975l/site-bundle/Lists/bots.txt',
    FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
);
```

---

## Full layout example

```twig
{% extends '@c975LSite/layout.html.twig' %}

{% set languagesAlt = {
    en: { title: 'English' },
    fr: { title: 'Français' },
    es: { title: 'Español' }
} %}

{% block meta %}
    {{ parent() }}
    <meta property="fb:app_id" content="YOUR_FACEBOOK_APP_ID">
{% endblock %}

{% block stylesheets %}
    {{ parent() }}
{% endblock %}

{% block navigation %}
    {{ include('navbar.html.twig') }}
{% endblock %}

{% block title %}
    {% if app.request.get('_route') is not null %}
        <h1>{{ title }}</h1>
    {% endif %}
{% endblock %}

{% block container %}
    <div class="container">
        {% block content %}{% endblock %}
    </div>
{% endblock %}

{% block share %}
    {# your sharing widget #}
{% endblock %}

{% block footer %}
    {{ include('footer.html.twig') }}
    <twig:c975LSite:General:HostedBy/>
    <twig:c975LSite:General:MadeBy/>
{% endblock %}

{% block javascripts %}
    {{ parent() }}
    <twig:c975LSite:General:CookieConsent/>
    <twig:c975LSite:General:Matomo/>
{% endblock %}
```

---

If this project **helps you save development time**, consider sponsoring via the **Sponsor** button at the top of the GitHub page. Thank you!
