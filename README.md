# SiteBundle

Symfony bundle that turns the c975L core into a complete website — full layout, database-driven pages, navbar and footer menus, collections, branded emails, per-page SEO and health check.

[![GitHub](https://img.shields.io/github/license/975L/SiteBundle)](https://github.com/975L/SiteBundle/blob/master/LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/c975l/site-bundle)](https://packagist.org/packages/c975l/site-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/c975l/site-bundle)](https://packagist.org/packages/c975l/site-bundle)
[![Codacy Grade](https://app.codacy.com/project/badge/Grade/612a27f799464950b0c5cfd13577ec20)](https://app.codacy.com/gh/975L/SiteBundle/dashboard)

## Why SiteBundle

![SiteBundle](.github/images/SiteBundle.svg)

Add SiteBundle on top of [c975L/CoreBundle](https://github.com/975L/CoreBundle) (ConfigBundle + UiBundle, one package) and get a complete website — pages, menus, SEO, EasyAdmin back office. Need a book catalog, an online shop, a photo gallery? Add [BookBundle](https://github.com/975L/BookBundle), [ShopBundle](https://github.com/975L/ShopBundle), [GalleryBundle](https://github.com/975L/GalleryBundle): they rest on the same foundation, alongside SiteBundle, never on top of it.

See it in action at [bundles.975l.com/pages/site-bundle](https://bundles.975l.com/pages/site-bundle), and browse every block kind live in the [block gallery](https://bundles.975l.com/pages/blocks).

---

> **TL;DR** — Turns the c975L core into a complete website: a base layout with SEO meta tags, pages served either from Twig templates or from the database as UiBundle blocks, navbar/footer menus, collections, branded emails, sitemap generation, and the commands to scaffold a whole new site. It also contributes six health check providers to ConfigBundle's Health check page, five of them about a published page and the sixth about the files its collection items declare.

## Contents

- **Building the site** — [layout](#creating-your-layout) · [pages](#pages) · [menus](#menus) · [themes](#themes) · [collections](#collections) · [error templates](#error-templates) · [legal models](#legal-models) · [full layout example](#full-layout-example)
- **Users & access** — [moved to ConfigBundle](#users)
- **SEO & quality** — [SEO and sitemap](#seo) · [Health check](#health-check) · [smoke test](#smoke-test) · [dev profile](#dev-profile)
- **Components** — [general components (navbar, footer, credits…)](#general-components) · [Twig extensions](#twig-extensions) · [email templates](#email-templates) · [CSS animations](#css-animations) · [lists](#lists)
- **Operating** — [commands](#commands) · [create a new site](#create-a-new-site) · [scheduler](#scheduler) · [admin help procedures](#admin-help-procedures) · [AI agent skills](#ai-agent-skills)

## Features

- **Base layout** with SEO-optimized meta tags (OpenGraph, robots, canonical, favicon, Apple touch icon)
- **Page display** from Twig templates (file-based) or from the database via the `Page` entity
- **Template-based page redirects** and 410 Gone handling (database redirects are ConfigBundle's, see its own readme)
- **Admin CRUD** for database pages via EasyAdmin
- **Admin CRUD** for the site's navbar/footer menus and the email header/footer via EasyAdmin
- **Sitemap generation** from database pages, collecting any other bundle's sitemap through `SitemapProviderInterface`, with a "Regenerate sitemap" dashboard shortcut
- **Error page templates** for 401, 403, 404, 410, and 500
- **Open Graph** image support
- **Email templates** with CSS inlining
- **Collections** of items (`CollectionGroup`/`CollectionItem`), exposed to UiBundle's `collection` block and given their own detail pages
- **Twig extensions**: `site_page`, `site_legal_pages`, `menu_blocks`, `page_health_check`
- **File lists**: `extensions.txt` and `bots.txt`
- **Admin help procedures** contributed to the dashboard AI assistant, describing how to create pages, redirects, menus, etc.
- **Four skills for coding agents**, shipped in the package and read straight from `vendor/` — see [AI agent skills](#ai-agent-skills)

---

## Requirements

- PHP >= 8.4
- Symfony ^8.0
- [c975L/CoreBundle](https://github.com/975L/CoreBundle) — ConfigBundle and UiBundle ship as the single `c975l/core-bundle` package, so requiring this bundle pulls both
- Doctrine ORM
- EasyAdmin
- symfony/ux-twig-component
- twig/cssinliner-extra

Your `App\Entity\User` must implement `c975L\ConfigBundle\Contract\UserInterface`, `Page::$user` and `CollectionItem::$user` being typed against it. The scaffolded `User` already does; an older one adds the `implements` itself, with no migration and no configuration change.

Optional: [nelmio/security-bundle](https://github.com/nelmio/NelmioSecurityBundle), if installed, has its CSP nonce generator decorated by `CookieNonceGenerator` — keeps the nonce stable across a visit, held in a signed cookie rather than per-request, so Turbo Drive/Frames/Streams re-executing `<script>` tags from a fetched page doesn't get them blocked by a mismatched nonce. A no-op if the bundle isn't installed.

Core-bundle's `layout.html.twig`, the parent this bundle's own extends, asks for a `style` nonce as well as a `script` one (Turbo nonces its progress bar's own `<style>` with the same value), which makes `'unsafe-inline'` inert for `style-src` on every public page. Any `style=""` attribute left in one of your own templates is therefore dropped by the browser: move those declarations to a class. Styles written from JavaScript (`el.style.display = '…'`) are unaffected, the CSSOM not being subject to `style-src`.

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
    resource: "@c975LSiteBundle/src/Controller/"
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

This bundle ships Stimulus controllers, front-end ones in `controllers.js` (basic) and back-office ones in `controllers-admin.js` (sitemap-fields, publication-switch). They are exposed via AssetMapper under the `@c975l/site-bundle` namespace. Identifiers are kebab-case on purpose: Stimulus derives its `data-<identifier>-*-value` attribute names from the identifier as registered, so a camelCase one silently breaks every binding.

Its `importmap.php` entry is added automatically the first time you `composer update` after installing SiteBundle — see [Contributing importmap entries from other bundles](https://github.com/975L/ConfigBundle#contributing-importmap-entries-from-other-bundles) in ConfigBundle's README, nothing to add by hand.

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

`c975l:scaffold:install` puts `templates/layout.html.twig` in your project for you — it is shipped by [c975L/ConfigBundle](https://github.com/975L/ConfigBundle), so that the login and reset-password pages it also scaffolds have a shell whether or not this bundle sits on top:

```twig
{% extends ['@c975LSite/layout.html.twig', '@c975LUi/layout.html.twig'] %}
```

Twig uses the first template of the list that exists, so with this bundle installed your pages get its full layout — header, footer, navigation, SEO, theme. The file is yours from then on: replace it with your own markup, or keep extending the bundle's layout and override its blocks.

Should you replace it, it becomes the parent of the database-driven pages too (`@c975LSite/pages/page.html.twig` extends `layout.html.twig`), so it has to hold up its end of that contract: define a `container` block for the page's blocks to render into, and read the `title` and `robots` variables the page sets. A replacement layout that defines neither renders the pages empty, without any error.

**This bundle's layout is a child of core-bundle's, not a copy of it.** `@c975LSite/layout.html.twig` extends `@c975LUi/layout.html.twig`, which is the single source for the whole `<head>`, the body skeleton and the share-image cascade — the minimal shell a site without this bundle is served already wrote all of it. What is added here is what having Pages, menus and a navbar brings: the `header`, `navigation`, `container` and `footer` blocks, and four variables handed over to the parent before it renders — `ogImageMedia` from the Page's own share image, `headingDisplayed` from `Page::isTitleDisplayed()`, `bodyClasses` for a fixed navbar and `bodyControllers` for this bundle's Stimulus controller. A child layout may set variables outside a block (Twig compiles its body ahead of the parent's yield) but never output, and **anything concerning the `<head>` belongs to core-bundle's layout**, where both shells read it.

### Page-specific variables

Declare these variables in each page template to populate meta tags and the page title:

```twig
{% set title = 'My Page Title' %}
{% set summarySocialNetwork = 'A short summary for social networks of this page.' %}
```

`summarySocialNetwork` feeds both `<meta name="description">` and `og:description`. A page that doesn't set it ships no meta description at all, which `ContentQualityHealthCheckProvider` reports.

A page no entity answers for — a listing, a filtered listing, a tool page — can state its `title` and its `summarySocialNetwork` from the back office instead, through the `UrlMetadata` row [c975L/CoreBundle](https://github.com/975L/CoreBundle) holds for its url. The layout only reads that row for what the template left unsaid, so a template setting the variables above keeps them.

### Template blocks

The layout exposes the following Twig blocks for you to override or extend:

All of them are core-bundle's but `navigation`, this bundle only overriding four of them:

| Block | Description |
| --- | --- |
| `head` | Entire `<head>` element |
| `meta` | Meta tags (charset, viewport, robots, og:*, etc.) |
| `title` | The `<title>` tag — **not** the page heading, which is `heading` below |
| `fontPreload` | `<link rel="preload">` for the theme's font files |
| `stylesheets` | CSS links |
| `preconnect` | `<link rel="preconnect">` hints |
| `body` | Entire `<body>` element |
| `bodyClass` | Class added to `<body>` itself, beside the navbar's own — for a screen laid on a ground of its own |
| `header` | Site header |
| `navigation` | Main navigation |
| `main` | Main content wrapper |
| `heading` | Page `<h1>` title — not printed for a database page whose *Display the page title* switch is unchecked (see [Pages](#pages)) |
| `flashes` | Flash messages — only rendered for a visitor carrying the session cookie, or a request that started the session itself, reading `app.flashes` being enough to open one. Only `success`, `info`, `warning` and `danger` carry a tint: `error` is printed as `danger`, `notice` as `info`, any other label as `info` |
| `container` | Container div wrapping `content` |
| `content` | Page-specific content |
| `share` | Sharing widgets |
| `navigationBottom` | Bottom navigation |
| `footer` | Site footer |
| `javascripts` | JavaScript includes |
| `importmap` | The AssetMapper import map and its entrypoints |

**A template suppressing its own heading overrides `heading`, never `title`** — the second empties the browser tab and every share along with it.

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

`<body>` is a flex column at least as tall as the viewport, with `<main>` taking whatever space is left over: the footer is held against the bottom of the window on a page too short to fill it, instead of floating halfway up, and a page longer than the viewport is never compressed. SocialBundle's automatic share buttons (`social-enable-share-buttons`) render outside `<main>`, in their own `<aside class="page-share">` between it and the footer, so the flex column leaves them against the footer rather than in the middle of the free space — the `share` Twig block above stays inside `<main>`, for a page's own bottom navigation.

That band is SocialBundle's own template, which the layout pulls in with `{{ include('@c975LSocial/shareButtons/default.html.twig', ignore_missing: true) }}` — the bundle is a suggestion, not a requirement, and a site without it renders nothing there. Calling its `share_buttons_default()` function directly would not work: Twig resolves a function call at compile time, so no runtime guard can keep the layout from failing on a missing extension, where an include is resolved at render time and `ignore_missing` swallows the unknown namespace. Any other c975L bundle contributing a site-wide band follows the same convention, its template path being the public contract.

---

## Pages

### File-based pages

Place Twig templates in `templates/pages/`. They are served at `/pages/{slug}` via the `page_display` route.

They are **not declared in the sitemap** — only database pages are (see [Sitemap generation](#sitemap-generation)), since a filesystem template carries no `lastmod`, no `priority` and no publication state. Create the page in the database instead if it has to be crawled.

### Redirects and deleted pages

| Location | Effect |
| --- | --- |
| `templates/pages/redirected/{slug}.html.twig` | Redirects to the slug written inside the file |
| `templates/pages/deleted/{slug}.html.twig` | Throws a 410 Gone exception |

**Database-managed redirects are ConfigBundle's** (`c975L\ConfigBundle\Entity\Redirect`, its CRUD under *Management → Advanced → Redirects*, its `redirect-chains` health check): a url that changed needs a redirect whether it was a page's or a product's, and the rows answer before the router, so they never depended on page management. See that bundle's readme for the `*` prefix convention and the "gone" rows.

Deleting a page permanently (see [Admin management](#admin-management)) leaves gone rows behind on its own: one for the page's own `/pages/{slug}` url — the trash's 410 only lasts as long as the page can still be restored — and one for each redirect that pointed at it, turned gone rather than deleted. `home` is skipped, being served at the site root, and a path an admin already redirects deliberately is left alone.

### Database pages

Use the `Page` entity to manage pages through the database. Each page supports:

- Title, slug (unique), summarySocialNetwork
- **Display the page title** — on by default, the layout printing the page's own title as its `<h1>`. Uncheck it for a page opened by a block that already carries one (a `hero`/`banner_title` left on its `h1` level, see UiBundle's *Heading level* field): the page keeps its title everywhere else — browser tab, share tags, menus — it just stops being printed twice on screen. The `content-quality` health check reports a page ending up with no `<h1>`, and one ending up with several: two `<h1>` are valid HTML and Google copes with them, but a screen reader announces them as two top-level subjects for one page
- Published status and display position
- Sitemap fields: indexable, change frequency and priority (0–10) — unchecking *indexable* both drops the page from `sitemap-site.xml` and switches its `robots` meta tag to `noindex` (change frequency/priority are then locked, keeping their value)
- **Unpublishing a page unreferences it**: `isIndexable` follows `isPublished` down, whatever unpublished the page — the edit form, the trash, *Publish as replacement*, a duplication or an import (`Page::unreferenceWhenUnpublished()`, a `PreFlush` callback). Publishing it again does *not* put it back: referencing a page is a deliberate call, made by checking *indexable* back. The page itself answers 404 the moment it's unpublished, the sitemap catching up at the next `c975l:sitemaps:create` run. Both screens follow the rule live rather than showing a value the database no longer holds: on the edit form *indexable* is unchecked and locked as soon as *published* is (`sitemap-fields`), and on the index — where each switch saves through ajax — the row's own *indexable* toggle is unchecked and disabled the same way (`publication-switch`). The trash's index carries neither column: every page there is unpublished, so both hold the same value for every row
- Blocks (content blocks from [c975L/UiBundle](https://github.com/975L/UiBundle))
- Creation / modification timestamps and author reference

The *Display the page title* switch above is stored in `Page::$options`, a single JSON column holding the page's benign display options — the same reasoning as UiBundle's `Block::$data`: adding an option is then a code change alone, with no schema migration for every app running this bundle to replay. Read and write them through named accessors (`isTitleDisplayed()`/`setIsTitleDisplayed()`, over the generic `getOption()`/`setOption()`), never as raw string keys scattered around: that's where each option's default lives, and it's the property path EasyAdmin's fields and Twig both resolve. Anything the database itself has to filter, sort or join on — `slug`, `isPublished`, `isIndexable`, read by the page queries and the sitemap — stays a real column.

Database pages are rendered with the bundle's `@c975LSite/pages/page.html.twig` template, which displays the page title, summarySocialNetwork, and its associated blocks.

`PageController::home()`/`display()` don't set an HTTP `Cache-Control: max-age` on the response (dropped in favor of UiBundle's per-block server-side cache, see its README's "Block render cache" section: infinite TTL, invalidated on save, shared by every visitor - rather than a per-browser cache with no way to invalidate it early after an edit).

`PageController::preview()` opts out of that cache entirely (UiBundle's `BlockRenderContext::disableCache()`), on both sides: an editor's preview has to show what was just saved, and its own render is not the public one — a `collection` block there builds its items' links against the preview route, which no visitor should ever be served.

### Blocks defined by this bundle

On top of the generic block system provided by [c975L/UiBundle](https://github.com/975L/UiBundle), SiteBundle registers the following blocks (see `config/services.yaml`):

| Kind | Category | Description |
| --- | --- | --- |
| `twig_content` | `label.category_twig` | Includes an existing Twig template by its path (`templatePath`, e.g. `pages/details/project.html.twig`) - a general-purpose "drop this template in here" block, not limited to the [collection item detail pages](#item-detail-pages) use case that motivated it. |
| `articles_slider` | `label.category_navigation` | Picks another database page and renders its `article` blocks (that have at least one media) as a clickable slider, using the `<twig:c975LUi:Slider:Slider>` component from UiBundle. |
| `menu_link` | `label.category_navigation` | A link to a published `Page` or a bundle-contributed route (see [Linking to a bundle's own route](#linking-to-a-bundles-own-route)); this is how `Menu` rows (navbar/footer/email-header/email-footer, see [Menus](#menus)) build their navigation, sortable alongside any other block. Restricted to the `menu`/`menu_navbar`/`menu_slot` contexts (UiBundle's `contexts` tag attribute, requires `c975l/ui-bundle` with context-aware `BlockRegistry::groupedByCategory()`), so it isn't offered when picking blocks for a `Page`, nor inside a container placed on one - and, `menu_navbar` being exclusive, it is the only kind a `navbar` offers at all. Not cacheable: its "active" state depends on the current request path. |
| `menu_group` | `label.category_navigation` | A chromeless wrapper grouping several items of a menu on a line of their own, e.g. a footer's social links above its links (see [Footer: grouping items](#footer-grouping-items)). Reuses UiBundle's `block_group` form, template and `.blocks-group` markup, its own kind only so a menu's grouping stays out of a `Page`'s containers: it declares the `menu` context alone, which also keeps it out of a `navbar`. Its slots take the `menu_slot` context, where containers are excluded, so a group can never hold another group. |

Each block is registered as a `ui.block`-tagged service, with a dedicated form (`c975L\SiteBundle\Form\Block\*Type`) and template (`templates/blocks/*.html.twig`). The `articles_slider` block relies on the `site_page(id)` Twig function (`PageExtension`) to eager-load the target page along with its blocks and medias.

### Admin management

Pages are managed in the EasyAdmin dashboard via `PageCrudController`. The menu entry is registered automatically through `MenuProvider`. Access is controlled by the `site-role-editor` key in ConfigBundle.

A whole page is one form — every block, every nested slot, every media in a single POST — so it is the form that reaches PHP's `max_input_vars` (1000 by default) first. Past that limit PHP drops the rest of the body silently, and the blocks that fell past the cut arrive as an absent key, which reads as "the editor removed them". Nothing can recover what PHP never parsed, so such a submission is refused whole and said so, rather than half-saved (`SubmissionIntegrity`, UiBundle). A site whose pages stack many blocks raises `max_input_vars` in its `php.ini`.

Selected pages can also be exported as a zip (title/slug/blocks, any Block's Media files bundled in) via the index's "Export selection" batch action, gated by `site-role-admin` — meant to be re-uploaded on another site/environment through ConfigBundle's **Import content** dashboard screen (see `PageImportProvider`, and ConfigBundle's README, "Contributing import providers from other bundles"). Ids never need to match between the two sites: pages/media are matched by slug on import. `PageExportProvider` (the same serialization, every non-deleted page) also plugs Pages into ConfigBundle's **Export sync (everything)** dashboard shortcut.

### Publish as replacement

Any non-deleted page's edit screen carries a "Publish as replacement" action group, listing every other page as a target — picking one swaps the current page in for it: the target's slug is archived and it's moved to the trash (recoverable via the usual "Restore" action, which reclaims the archived slug if still free, otherwise keeps the technical one).

The usual way to prepare such a replacement is the "Duplicate" action: it builds an unpublished copy of a page — its whole block tree, a container's own columns and everything nested inside them included, each block's medias copied to their own files — which is then reworked at leisure and swapped in once ready. A page's `replaces` field is only a fallback default for the action group's target, never a requirement.

There is deliberately **no page-template mechanism**: a page's block arrangement is composed in the admin, block by block, and never derived from a stored arrangement. A "template" here could only ever be a snapshot of example content copied once, with no relation kept afterwards — a maintenance cost with no matching benefit, since a page's structure is built once in a site's life. Use "Duplicate" for a page that should look like an existing one.

---

## Menus

The site-wide navbar, footer, email header and email footer are managed entirely from the database — no app-side template override needed. Each is a `Menu` (`location`: `navbar`, `footer`, `email-header` or `email-footer`, one row per location, same singleton pattern as the site-wide graphics managed via UiBundle’s `SiteGraphicCrudController`).

Every location owns a single ordered `blocks` collection (same generic UiBundle `Block` system as `Page`, see [Blocks defined by this bundle](#blocks-defined-by-this-bundle)) — menu links and any other registered block kind (e.g. SocialBundle's `social_links_display`) are freely sortable together, no separate "items" collection to keep in sync. **Except in the `navbar`**, whose picker only ever offers `menu_link`: a navigation bar is a plain list of links, anything else belongs in the page itself (UiBundle's exclusive `BlockRegistry::MENU_NAVBAR_CONTEXT`, see its README's `contexts` section). A menu link is itself a block, of kind `menu_link` (form: `MenuLinkType`), targeting either:

- an existing `Page` (linked by its id, so renaming the page's slug never breaks the link) — unpublished pages stay pickable too, flagged "(draft)" in the picker, and simply resolve to no URL until published, or
- a route contributed by another bundle (see [Linking to a bundle's own route](#linking-to-a-bundles-own-route) below)

`menu_link` resolves to a relative URL (`UrlGeneratorInterface::generate()`), fine for `navbar`/`footer` but not usable as-is inside an email — `email-header`/`email-footer` are meant for content that doesn't need one (e.g. social icons, legal blurbs), not for reusing the site's own links.

Managed via `MenuCrudController` (drag-and-drop reordering, same mechanism as [Blocks](#blocks-defined-by-this-bundle)). Access is controlled by the `site-role-editor` key in ConfigBundle. There is no "new menu" form at all: a menu's only own field is its location, one of four, each usable once — so the index shows one **create button per location not created yet**, each creating the row and opening its edit screen in a single click (`MenuCrudController::create()`, a CSRF-protected POST; `Action::NEW` is disabled). Composing the blocks then happens there, on a menu whose location — and therefore whose available block kinds — is already settled. A location that already has its row (double submit, stale index in another tab) just opens the existing one instead of hitting the unique constraint on `Menu::$location`.

`navbar` and `footer` are rendered by built-in components already wired into the bundle's layout (`navigation`/`footer` blocks) — nothing to add in your app:

```twig
<twig:c975LSite:General:Navbar/>
<twig:c975LSite:General:Footer copyright="{{ copyright }}"/>
```

`email-header` and `email-footer` are rendered the same way inside `@c975LSite/emails/header.html.twig` and `@c975LSite/emails/footer.html.twig` respectively (see [Email templates](#email-templates)), independently from the site's own `navbar`/`footer` — each location is edited separately, so the client can keep different content for emails than for the site.

A block disappears from the rendered menu automatically (no dangling link) if its `menu_link` targets a page that's later unpublished/deleted, or a route whose contributing bundle is removed.

Hovering a **footer** item as a `site-role-editor` user shows UiBundle's "Edit" button on it, the same one a `Page`'s blocks carry (see its README's `BlockEditUrlProviderInterface` section) — it opens the footer menu's edit screen with that very item's row already unfolded (`MenuBlockEditUrlProvider`). The `navbar` renders the same blocks but is deliberately left out: it is hovered on every single visit just to navigate, and a button popping over each of its links would be in the way rather than of help.

### Footer: display style

The `footer` menu's edit screen carries one field of its own beyond its blocks — **Display style**, a `<select>` picking how its items are laid out (`Menu::$style`):

| Choice | Rendered as |
| --- | --- |
| *Site theme's own choice* (default) | Whatever the site's `themes/site.css` left the `--footer-items-direction`/`--footer-items-justify` tokens at — a column, unless the design retuned them |
| Inline | One centered row, wrapping (`.menu-items--inline`) |
| Block | Stacked, one item below the other (`.menu-items--block`) |

The two classes **retune those very tokens** on the `.menu-items` element rather than writing `flex-direction` themselves (`sass/_footer.scss`), so one class is enough for both the wrapper and its `.blocks` child, and it beats what the theme left on `:root` — a declaration on the element always wins over an inherited one. Leaving the select on its placeholder stores `null` and adds no class at all, which is what every menu saved before the field existed holds: **a site that already picked its layout in `themes/site.css` keeps rendering exactly as it did**, and only starts following the backoffice once an admin picks a style there. Anything but the two known values is stored as `null` (`Menu::setStyle()`) — the value ends up in a class name, and an unknown one would only ever name a rule no stylesheet carries.

No other location offers it: a navbar lays its items out on its own (stacked below 768px, inlined above), and both email menus render as one inline row whatever the site does. The choice is cached like the menu's blocks are (`menu_style()`, same `menus_all` tag), so it costs no query per page — `MenuCacheInvalidationListener` now watches the `Menu` row itself, which no `Block` event would ever signal.

### Footer: grouping items

A footer often needs more than one line: its social links on one, its legal links on the next. Any menu that takes the full picker (`footer` and both email locations, i.e. everything but the `navbar`) can therefore hold **`menu_group`** blocks — a chromeless wrapper around several other blocks, which takes a line of its own inside the footer's flex row (`footer .menu-items .blocks-group { flex: 0 0 100% }`, `sass/_footer.scss`). How the group itself lays its blocks out — row or column, and how they're justified — is picked on the group, UiBundle's `BlockGroupType` being reused as is.

**`menu_group` is the only group a menu offers**, and UiBundle's own `block_group` — same form, same template — is not: a container is only offered in the `menu` context if it declared that context itself, and a `menu_link` only opts into `menu_group`'s slot context. A group taken from anywhere else would be picked happily, then refuse every link dropped into it. A footer composed before `menu_group` existed may still hold a `block_group`: it renders exactly as it did and keeps whatever it holds (social links, an image...), but a `menu_link` was never accepted in it and still isn't — group links in a `menu_group` instead.

Items are moved in or out of a group by drag and drop, like any container's slots. A group can't hold another group, so the tree is always one level deep, which is what `Footer.html.twig` walks when it checks whether a "Copyright" link is already there. Editing a group (or a link) invalidates the cached menus through `MenuCacheInvalidationListener`, exactly as editing the `Menu` row does.

`MenuExportProvider`/`MenuImportProvider` plug all four locations into ConfigBundle's **Export sync (everything)** dashboard shortcut and **Import content** screen — matched by `location` on import, same "whole Block collection replaced" approach as `PageImportProvider`.

### Linking to a section of a page (anchors)

A `menu_link`'s target can also point at a specific **section** of a page, not just the page itself - useful for a one-page nav (`#services`, `#contact`...). Any UiBundle "Page sections" block kind (`hero`, `feature_bar`, `section_cards`, `expertise_banner`, `process_steps`, `portfolio_grid`, `cta_band`, `collection` - see UiBundle's README, "Anchors (in-page navigation)") can carry an anchor; once at least one block on a page has one, `MenuLinkType`'s target select lists it right under that page's own entry (e.g. `Home → Services`), with no extra step - it's still the same flat list, just with more rows.

Sections nested in a container (a `text_section` inside a `flex_columns`, for instance) are listed too, as are the kinds whose anchor is an auto-derived `slug` rendered as-is (`text_section`, `article`) - the whole page tree is walked by UiBundle's `BlockAnchorCollector`, which also labels the saved target back in the rendered menu, so picker and menu can't disagree.

Under the hood, the target is stored as `page:<id>#<fragment>` and resolved by `MenuExtension::getMenuLinkUrl()` into `/home#services-42` - the trailing `#fragment` is only added when present, so a plain `page:<id>` target keeps working exactly as before.

A site-wide band rendered by the layout rather than by a page's own blocks (e.g. SocialBundle's automatic share buttons) is **not** in that list: only anchors carried by a page's own blocks are. To make one linkable, drop its block kind (e.g. `share_buttons_display`) into the page and give it an anchor there.

### Copyright

The "© firstYear - currentYear[ : siteName]" copyright notice (built from the `site-first-online-date`/`site-name` keys) is computed by ConfigBundle's `site_copyright(bool $withSiteName = true)` Twig function, used here by `layout.html.twig` and `emails/fullLayout.html.twig` — it moved there with its two configs, a site with no pages needing a copyright line just as much.

A `menu_link` targeting the site's own "Copyright" page (the one `DefaultPagesImporter` seeds under the `france/copyright` model — `copyright`/`copyright-notice`/`aviso-de-copyright` depending on locale) automatically shows this live-computed text as its label instead of the page's own title, gated by the `site-menu-link-copyright-auto` ConfigBundle key (`bool`, default `true`) — lets a footer's "Copyright" page link double as the copyright notice instead of showing both side by side. `menu_link` also has an optional `label` field (`MenuLinkType`) that always overrides the auto-derived label (page/section title or computed copyright) when filled in.

A `menu_link` also has an optional `primary` checkbox (`MenuLinkType`) that renders it as a filled, primary-color button (`.menu-item--primary`, see `sass/_menu.scss`) instead of a plain text link — meant for a single stand-out item (e.g. a "Contact" link in the navbar), not for every item in a `Menu`. A lighter emphasis sits beside it: the `strong` checkbox bolds the label alone (`.menu-item--strong`, the rule sitting on `.menu-label`, which is what carries the typography), so the item stands out without taking a button's room — in a footer, where a button would be out of place, or on an item a button would over-sell. The two are independent and stack: checked together, the button reads bolder.

In the navbar, the button's label reads `--navbar-btn-color`, its rule restated after the two that paint every navbar label: the three tie at the same specificity, so only source order keeps the button's own ink on top of its filled background (`NavbarRuleScopeTest`).

### Navbar: logo, site name, tagline

`Navbar` reads `site_media('logo')`, `config('site-name')` and `config('site-tagline')` — nothing to pass in. `site-name` stays mandatory (used across meta tags, page titles, etc.), but showing it in the navbar specifically is optional via the `site-navbar-show-name` ConfigBundle key (`bool`, default `true`).

The logo and the name are wrapped in **one single link** to the home page (`.menu-brand-link` here, `.nav-simple-brand` on the fallback bar below), not two adjacent ones — a screen reader announced the same destination twice in a row. Both are `<span>`s inside it, so `.menu-logo`/`.menu-site-name`/`.nav-simple-name` stay the classes to style; the logo's `alt` is emptied when the name is printed beside it, the link already reading it.

The navbar's CSS `position` is set via the `site-navbar-position` ConfigBundle key (`choice`, picked from `relative` (default), `sticky`, `fixed`, `static`, `absolute`), carried by a `.menu.menu-position-*` class on the `<nav>` that sets the `--navbar-position` custom property (`sass/_menu.scss`), rather than by a `style=""` attribute a nonced `style-src` would drop — `static` is written out as `relative`, the two being identical in the flow except that only the latter is a containing block for the dropdown hanging off the bar. `fixed` additionally adds `.menu-fixed` on the `<nav>` and a `navbar-fixed` class on `<body>` to compensate the space it frees from the normal flow; other values are left to the theme's own `--navbar-position` fallback.

`sticky` and `absolute` set `--navbar-z-index: 1000` (the value `.menu-fixed` already carried), a bar overlapping the page having to be painted above it — `relative` keeps `auto`, never overlapping anything. `sticky` also needs a containing block taller than the bar to travel in, so the sticky box is the `<header>` `layout.html.twig` wraps it in, targeted as `header:has(> .menu.menu-position-sticky:only-child)`: an app rendering a `<header>` holding more than the navigation is deliberately left out of it, and has to make its own sticky arrangement.

With no `navbar` Menu at all, the component falls back to the logo, centered, with the site name stacked under it (`.nav-simple-name`, subject to the same `site-navbar-show-name` key), on a `<nav class="nav-simple">` — that class, and not the bare `nav` element, is what `sass/_navbar.scss` styles, so an app rendering a `<nav>` of its own is left untouched by it. That bar keeps the same `--navbar-padding-y` room above and below its line as the menu one, and the name is set in `--font-family-title` on the `<span>` itself, `_typography.scss` handing the body font to `*` where it would otherwise be inherited from the link.

`site-tagline` is authored as rich text in the backoffice (Trix wraps the value in its own `<div>`), so it's rendered with `|raw` — style `.menu-site-tagline` in your own SCSS if you need to adjust it. It is not shown on a `fixed` navbar, which has to stay on the height `<body>` gets back from it.

### Linking to a bundle's own route

A `menu_link` block isn't limited to database pages. Any bundle can expose one of its own front-end routes as a selectable target by implementing ConfigBundle's `LinkableRouteProviderInterface` — see [ConfigBundle's README](https://github.com/975L/ConfigBundle#contributing-linkable-routes-for-sitebundle-menus) for how to write the provider. The same approach will apply to ShopBundle and BookBundle.

A contributed target isn't necessarily a whole screen: a bundle can offer one entry per row of its own data (GalleryBundle lists the gallery index *and* each of its categories), each entry naming the route to generate and the parameters to fill it with. Those show up in the picker beside the pages, and the url is generated at each render rather than stored — renaming that row leaves no menu item behind. Such an entry can also carry a `picker_label`, so it says what it is in that list ("Galerie - Paysages") while the rendered menu item keeps the row's bare title ("Paysages").

Since login isn't a SiteBundle route but scaffolded straight into `App\Controller` (see [Users](#users) below), the scaffold also ships `App\Management\LinkableRouteProvider`, so `app_login` shows up in the `menu_link` picker out of the box — re-run the scaffold install (or copy the file by hand) on sites that predate it. Register and reset-password aren't routes to link to anymore: they're the ordinary database `Page`s seeded by `DefaultPagesImporter` (see [Import default pages](#import-default-pages)), picked from the `menu_link` picker like any other page. `app_verify_email` and `app_reset_password` are deliberately left out of `LinkableRouteProvider`: they only make sense reached through a signed link, not as a standalone menu target.

### Social links

Social icons in the footer (site or email) are no longer a dedicated component/config toggle — they're a regular block, dropped into the footer's own `blocks` collection like any other. See [c975L/SocialBundle](https://github.com/975L/SocialBundle)'s README for the `social_links`/`social_links_display` block kinds it registers.

---

## Users

Accounts moved to [c975L/ConfigBundle](https://github.com/975L/ConfigBundle), which already owned the `Contract\UserInterface` every c975L entity relates to — back-office CRUD, the `ROLE_SUPER_ADMIN` rules, registration, its anti-spam protections, login throttling, back-office access control and account activation are all documented there. This bundle only contributes `SiteFormPageUrlProvider`, which answers UiBundle's `form_url()` with the real `Page` carrying a `register`/`reset_password_request` form Block, so the scaffolded login page links the admin-editable per-locale slug rather than the bare form route.

---

## SEO

### Sitemap generation

Run the following command to generate every sitemap the site needs:

```bash
php bin/console c975l:sitemaps:create
```

The command belongs to ConfigBundle (`SitemapWriter`), not to SiteBundle: it writes one `public/sitemap-<name>.xml` per bundle implementing `SitemapProviderInterface`, plus the `public/sitemap-index.xml` declaring them all. SiteBundle contributes `SitePageSitemapProvider`, which gives `sitemap-site.xml` from the database pages — non-indexable pages excluded, urls built by `PagePublicUrlResolver` so they're exactly the ones the health checks test. BookBundle, ShopBundle… each contribute their own the same way, with nothing to declare in the app.

Each url also carries the page's `title` and its social network summary as optional `title`/`description` keys. The sitemap itself ignores them; ConfigBundle's `SeoFilesWriter` is what reads them, to build the `public/llms.txt` listing what every page of the site is about.

Point Google Search Console at `sitemap-index.xml` only, never at the sub-sitemaps — installing or removing a bundle then changes what's crawled with nothing to update on Google's side.

The same writer is also behind the "Create sitemaps" dashboard shortcut (ConfigBundle's `ConfigShortcutProvider`), for when publishing shouldn't wait for the next scheduler run.

On a site scaffolded before this, `App\Command\SitemapCreateCommand` (`app:sitemaps:create`) is obsolete — running each bundle's sitemap command and writing the index is exactly what `c975l:sitemaps:create` does. `php bin/console c975l:scaffold:install` deletes `src/Command/SitemapCreateCommand.php` and its test for you, both being declared in `scaffold/removed.json`; point `MaintenanceSchedule` at `c975l:sitemaps:create`.

To contribute a sitemap from another bundle, see [Contributing a sitemap](https://github.com/975L/ConfigBundle#contributing-a-sitemap-from-other-bundles) in ConfigBundle's own README — it's a two-method interface, and the file/index writing is none of the contributing bundle's business.

### Canonical url

`<link rel="canonical">` and `og:url` are built by the `canonical_url()` Twig function, out of the `site-url`
config value and the current path — not out of `app.request.uri`, which made every variant of a url declare
itself canonical: its query string (`?fbclid=…`, `?utm_source=…` each counted as a page of their own), its
trailing slash and its scheme/host (`www` vs apex, `http` vs `https`). The query string is dropped, and the
path is the slashless form the sitemap declares (the site root keeping its own slash). Nothing is emitted at
all outside an http request or before `site-url` is set, rather than a tag pointing at the wrong host.

`/pages/{slug}/` is answered with a `301` to `/pages/{slug}` on top of that: both used to serve the same
content under two urls, and a canonical link alone is only a hint.

### Error pages are not indexable

The layout defaults the `robots` meta to `noindex, follow` on every error page (`status_code` is only ever set
by Symfony's error renderer) — a 404 rendered with the site's own layout used to inherit the `index, follow`
default and offer itself to the index. `follow` is kept, so the links the page carries still pass on. It is
defaulted in the layout rather than set in each error template, so none of them can forget it.

`hreflang` tags are no longer emitted: the previous implementation was built on `app.request.uri` and, with no
`languagesAlt` defined, declared every page its own alternate — query string included. A site serving several
languages does it through its own locale-prefixed routes for now.

### Open Graph image

Resolved in this order: an `ogImage` variable set by the template/page takes priority, then a database `Page`'s own `ogImage` (settable from `PageCrudController`), then the url's own `UrlMetadata` row, then the site-wide default og-image managed via [Site graphics](#site-graphics), then the site's logo.

Whichever of the last four answers, the layout keeps the `Media` row alongside the url it built, and states `og:image:width`/`og:image:height` from it — from `getIntrinsicWidth()`/`getIntrinsicHeight()`, so a media whose dimensions were typed as a css length (`50%`, `auto`) says nothing rather than something Open Graph cannot read — and `og:image:alt` from the media's own alternative text.

To override it manually for a file-based page:

```twig
{% set ogImage = absolute_url(asset('images/my-og-image.jpg')) %}
{% set ogImageAlt = 'A knight riding through the mist' %}
```

A template setting `ogImage` itself is the only one that knows what the image shows, hence `ogImageAlt` next to it; no dimensions are emitted in that case, the layout having no row to read them from.

The page's own url is handed to the edit screen as `page_public_path` (published pages only), where CoreBundle's sharing-debugger note points at Facebook's re-scrape tool: a network caches the preview under the page's url, so an image chosen later is only picked up by asking for a refresh.

---

## Smoke test

```bash
php bin/console c975l:site:smoke-test
```

Meant to run at the end of a deployment: it checks that every published page — the very same list the sitemap and the health checks use, resolved through `PagePublicUrlResolver` — plus every css/js asset the home page references, answer 200, and exits non-zero on the first failure so a CI job fails instead of leaving a broken site online. Only failures are printed; `-v` lists every url checked. `--pages-only` skips the asset pass.

A site left in maintenance (`site-maintenance`) answers 503 on every public url by construction, so the command checks nothing and exits 0 rather than reporting a deployment that went fine as broken — run it once the site is back online.

Assets are read out of the home page's rendered HTML rather than declared anywhere: AssetMapper's filenames are hashed (`app-EiPntxm.css`), so this is what actually proves `asset-map:compile` and the stylesheet cache warmer both ran, and ran in the right order. Requests are all fired before any status is read, so checking a few dozen urls costs about a second.

Deliberately **not** a `HealthCheckProviderInterface` implementation (see [Health check](#health-check) below): that one judges a live site's quality on a weekly schedule and persists rows for a dashboard, this one answers "is it broken, right now" and has to be able to fail a pipeline.

The bundle also runs it every night, in a 5am-7am window, through `SiteMaintenanceTaskProvider` — see [Scheduler](#scheduler).

---

## Dev profile

```bash
php bin/console c975l:dev-profile:run
```

ConfigBundle's dev-only command (see its own README for what it measures and how to contribute paths from another bundle) lists what the Symfony dev toolbar would flag on every page — n+1 queries, deprecations, missing translations, external HTTP calls during rendering. SiteBundle contributes `PageDevProfilePathProvider`, declaring every published `Page`, so there's nothing to write for an app installing it.

Note the difference with the health check and the smoke test above: both fetch the **live** site over HTTP at `site-url`, which points at production even from a dev machine. This one hands each page's local path (`PagePublicUrlResolver::resolvePath()`) straight to the local kernel, no HTTP and no host involved, so it profiles the code and database you're working on.

---

## Health check

SiteBundle contributes six `HealthCheckProviderInterface` implementations, five of them about a **published page** (see `c975l/config-bundle`'s own README for the dashboard page, the `c975l:health-check:run` command, history/export/trend chart, and the site-wide checks — TLS certificate, security headers, robots.txt/sitemap, redirect chains, deployment, declared urls — which live there now, none of them needing a Page). No Node/Lighthouse-CLI/JS tooling, only plain Symfony HttpClient calls:

| Provider | `getKind()` | Checks | API key |
| --- | --- | --- | --- |
| `SitePageHealthCheckProvider` | `pagespeed` | Lighthouse performance/accessibility/best-practices/SEO scores + console errors, via Google's PageSpeed Insights v5 API | Optional (`healthcheck-pagespeed-api-key`) - works without one, but Google's anonymous quota is shared worldwide and easily exhausted (HTTP 429); a key raises it |
| `W3cHtmlHealthCheckProvider` | `w3c-html` | HTML markup (W3C Nu Html Checker) - skips a page outright (single "not tested" row) if it doesn't resolve on the checked environment, instead of forwarding a 404 to the validator | None |
| `W3cCssHealthCheckProvider` | `w3c-css` | CSS markup (W3C CSS Validator) - same not-deployed guard as `W3cHtmlHealthCheckProvider`. Split from HTML into its own kind/row so each shows its own count (eg. "51 CSS warnings") without being buried in one combined line, and each gets its own direct link to its validator's report for the page. Warnings the validator's own CSS3 profile predates (one per `var()` usage, vendor prefixes and prefixed pseudo-elements/classes, values it doesn't know but reports as browser-supported) are counted apart as *benign*: the summary still shows the report's own total, and only the actionable count drives the row's status, so a stylesheet built on custom properties isn't permanently orange. Nothing is dropped - both lists are persisted, under `warnings` and `benignWarnings` (see `W3cValidatorClient::BENIGN_CSS_WARNING_PATTERNS`) | None |
| `ContentQualityHealthCheckProvider` | `content-quality` | A `noindex` on a page checked as *indexable*, missing/too short/too long `<title>` (10-65 characters) and meta description (50-160), missing `<h1>`, missing share tags (`og:title`, `og:description`, `og:image` - read from either the `property` or the `name` attribute), images without `alt`, broken links - internal (`<a href>` pointing at this site's own host) **and** external, each unique link checked once per run regardless of how many pages link to it, and an external one only ever a warning - parses the page's own rendered HTML (`DOMDocument`/`DOMXPath`, no dependency) rather than reverse-engineering it from block data, so it works regardless of theme/block kinds used. Same not-deployed guard as `W3cHtmlHealthCheckProvider`. See [what counts as an offence](#what-content-quality-actually-flags) - a decorative image and an unreachable server are deliberately *not* flagged | None |
| `MixedContentHealthCheckProvider` | `mixed-content` | `http://` images/scripts/stylesheets loaded from an `https://` page, per published page - skipped entirely if `site-url` isn't `https://` | None |
| `CollectionFilesHealthCheckProvider` | `files-site` | That every file a `CollectionItem` names is still under `public/` — the one check here about a stored file rather than a published page. Everything it does is UiBundle's `AbstractDeclaredFilesHealthCheckProvider`, this only names the rows to look at; an item whose image is gone renders a hole no error anywhere reports | None |

### Checking other bundles' urls

`content-quality` only knows about this bundle's own `Page` entities. Everything else a site publishes — a book, a product, a photo, a crowdfunding campaign — is checked by **ConfigBundle's `DeclaredUrlsHealthCheckProvider`** (`urls-<bundle>` kinds), which runs the very same `ContentQualityAnalyzer` over the urls a bundle already declares for its sitemap. Nothing to implement bundle-side; see ConfigBundle's own readme.

This bundle's `SitePageSitemapProvider` opts out of it (`SelfCheckedSitemapProviderInterface`): `content-quality` already checks every `Page`, and does it better — each offence traced back to the block holding it through `PageContentOffenceLocator`, each row linking to the page's own edit screen. A declared url has no `Page` and no admin screen behind it, so its rows carry no edit link and no block link; every other check is identical, down to the advice lines.

### What content-quality actually flags

What it reports, and the rules that keep it from reporting things you cannot fix:

- **`noindex`.** A page asking crawlers to drop a url its own site declares to them is an error, listed before everything else: whether the page is in the results at all comes before how well it reads there. It's the contradiction Search Console reports as *Excluded by 'noindex' tag*, and one nothing else can see — the page answers `200` and reads perfectly well to anyone but a crawler. Only judged on a page checked as *indexable*: the pages meant to stay out of the results (the account ones) carry their `noindex` on purpose, and reporting them would leave them red forever with nothing to fix.
- **Redirects.** A url that answers `301`/`302` before serving its content is a warning: whatever was measured belongs to the url the hop landed on, not to the one declared, and Search Console reports the hop as a *redirect error* rather than following it the way a browser silently does. The fix is to declare the final url. It costs no extra request - the hop count and final url are read off the analysis response's own `redirect_count`/`url` info, which the transport already resolved.
- **Share tags.** Only the three a preview actually needs to render are required (`og:title`, `og:description`, `og:image`). `og:url`/`og:type` belong to the Open Graph protocol too, but nothing visible breaks without them, so they stay out rather than turning every page orange over a tag no one sees. A tag with an empty `content` counts as absent. `og:title`/`og:description` are left out of the list when the title/description they mirror is itself reported missing: the layout only emits them alongside their own meta tag, so listing both would report one empty field twice and send the reader looking for two things to do. Schema.org/JSON-LD is deliberately **not** checked: whether a page carries structured data depends on what it is, not on whether it's well built.

- **Images.** A missing `alt` attribute is always an error. An explicitly empty `alt=""` is the *correct* markup for a decorative image, so it is only flagged when nothing marks it as decorative: no `aria-hidden="true"`, no `role="presentation"`/`role="none"`, and no enclosing `<a>`/`<button>` already carrying its own `aria-label`/`aria-labelledby`. A share button's icon inside a labelled link, or a logo inside a labelled link, is therefore correct as-is and stays out of the report - flagging it would leave the page in warning forever with nothing to fix.
- **Every offender is listed individually** under its advice line on the page's own "Health check" tab (a collapsed list, so a page with a dozen images without `alt` doesn't bury the rest of the table), each linking straight to the block that produced it (`PageBlockLocator`, best-effort: it traces the rendered `src`/`href` back through the page's blocks, and falls back to the page's plain edit url when no block claims it).
- **Advice points at the field to fill, by its own label.** The meta description is named after the form field carrying it ("Social network summary") rather than after the tag it feeds — nothing in the back office calls it a meta description — and its advice line opens the page's edit form straight on that field (UiBundle's `focusField` query param, see its own README). A url with no `Page` behind it (another bundle's declared url) has no form to send the user to, so the line stays unlinked.
- **Links.** Only a conclusive HTTP status `>= 400` counts as broken. A transport failure (timeout, DNS, refused connection) and any status describing how the server treats *this client* rather than whether the url exists — `405`/`501` (the `HEAD` method refused), `403` and LinkedIn's non-standard `999` (bot filtering, which most big retailers and social sites apply to datacenter IPs), `429` (rate limiting) — are retried once with a `GET`, and if they still can't be concluded they are left out rather than reported. Link checks identify themselves as `Mozilla/5.0 (compatible; c975LHealthCheck/1.0; +https://github.com/975L/SiteBundle)`: honest enough that a WAF operator can look it up and allow it, while keeping the crawler shape far fewer filters reject outright than a bare library default. **External links are checked too, but a dead one is only ever a warning** — it isn't yours to fix on your own schedule, and it's the check most exposed to a false positive; a dead link on your own pages stays an error. Both are listed separately, in the same dedup/batching pass, so an external host is hit once per run and not once per page linking to it. Link checks are also fired in batches of 10 instead of all at once: Symfony's `HttpClient` caps concurrent connections per host, so a site-wide burst only queues the surplus while each queued request's own timeout is already running - which used to turn perfectly valid links into timeouts, and timeouts into "broken" rows.

`W3cHtmlHealthCheckProvider`/`W3cCssHealthCheckProvider` share their page-existence-check-then-validate logic via `AbstractW3cValidationHealthCheckProvider`, only their `W3cValidatorClient` method and translation ids differ. Their advice lines list the validator's own messages under each count, the same collapsed way content-quality lists its offenders, so the row says *which* errors that very run found — the "full report" link cannot, since it revalidates live and a page fixed since the run would read as the validator contradicting the dashboard. `SitePageHealthCheckProvider`, both W3C providers and `ContentQualityHealthCheckProvider` resolve each page's public URL the same way (`PagePublicUrlResolver`, shared to avoid duplicating it) and its EasyAdmin edit URL the same way too (`PageEditUrlResolver`, so each row also links straight to the page behind it, alongside `MixedContentHealthCheckProvider`); the W3C providers and `ContentQualityHealthCheckProvider` also share ConfigBundle's `UrlStatusChecker` (a single `HEAD` request) so a page that doesn't resolve never reaches the actual check as a confusing raw HTTP error. The two react to it differently, on purpose: the W3C providers (like `SitePageHealthCheckProvider`) emit a "not tested" row (`HealthCheckResult::STATUS_SKIPPED`, shown neutrally - there is nothing for a validator to say about a page it never got, and nothing to spend a paid quota call on), while `ContentQualityHealthCheckProvider` reports what the page actually answered: **`404` is an error**, `410` a warning (removed on purpose, still listed), any other `4xx`/`5xx` an error carrying its own code, and a host that never answered at all an "unreachable" error told apart from the rest (`UrlStatusChecker::status()`, which returns the code where `exists()` only returns a bool). It used to be skipped there too, which meant a published page 404ing in production raised no dashboard alert at all - the very 404s Search Console reports. One kind reports each 404, not four. `'home'` maps to the site root, any other slug to `/pages/{slug}`, matching the routing `ContentAccessTest` already exercises. None of these providers run from a controller: only `c975l:health-check:run` (manually, or via your app's own [scheduler](#scheduler)) invokes `runChecks()`, so a slow or paid API call never blocks a request.

**Run this against production's own database**, same constraint as `c975l:sitemaps:create`/`c975l:config:backup`: the page list comes from `PageRepository::findAllOrdered()` against whichever database the command is connected to, while the urls it builds always point at `site-url` (production). Run it from a dev/staging environment whose database has pages not yet synced to production (see [Contributing export providers](https://github.com/975L/ConfigBundle#contributing-export-providers-from-other-bundles)'s "Sync" zip) and you'll get failures for pages that simply aren't live yet - not a bug, just the wrong database for the question being asked. In normal operation this is a non-issue: the scheduler consumer already runs on production.

**On WCAG/accessibility specifically**: there is no free, pure-PHP equivalent to a real WCAG scanner (tools like axe-core need a headless browser, i.e. Node) - `SitePageHealthCheckProvider`'s `accessibility` score is the only automated signal here, a rougher one than an itemized audit (it's the same axe-core engine under the hood, but reports one score, not per-criterion detail). A prior version of this bundle called WebAIM's WAVE API for itemized RGAA/WCAG detail; it was removed as credit-based pricing (~$0.04/page) doesn't scale to checking many pages across many sites. If you need an itemized audit trail for an accessibility declaration (RGAA 4.1's 106 criteria map to WCAG 2.1 AA, EAA enforceable since June 2025), run WAVE's own browser extension manually per page, or reintroduce a paid provider on your own `HealthCheckProviderInterface` implementation.

## Site graphics

**Moved to `c975l/ui-bundle`.** The favicon, Apple touch icon, logo, default Open Graph image and error-image pool are `c975L\UiBundle\Entity\Media` rows carrying a `role`, and their CRUD, dashboard alerts, export/import and `OgImageType` now ship with the bundle that owns `Media` — a site running a shop with no page management has a favicon to upload just the same. See UiBundle's readme.

What stays here is `Management\SiteMediaUsageProvider`, which tells the Media library where a media is used **within this bundle's own entities**: as a page's og-image, or attached to a block a page owns.

---

## Admin help procedures

`ProcedureProvider` (implements ConfigBundle's `ProcedureProviderInterface`) reads `config/procedures.json` and contributes one entry per documented admin workflow (creating a page, a redirect, a menu, a user, site graphics, collection items) to ConfigBundle's `ProcedureBuilder`, which aggregates every bundle's procedures for the dashboard AI assistant. Each entry ships `fr`/`en`/`es` translations, resolved to the current locale by `ProcedureJsonReader` (falling back to English, then to whichever translation comes first).

`SiteEssentialActionProvider` (implements ConfigBundle's `EssentialActionProviderInterface`) contributes a homepage/navbar+footer menus/at-least-one-font entry to the dashboard's "Essential actions" checklist (see ConfigBundle's README, "Contributing essential actions from other bundles").

`SiteAlertProvider` (implements ConfigBundle's `AlertProviderInterface`) raises a dashboard alert when **no published page carries the `france/legal-notice` or the `france/cookies` legal model**, and when a `home` page exists but is unpublished or deleted — the site then answers 404 on `/` while holding the page that should be there. The legal *models* themselves are UiBundle's (its "Legal models" screen writes the text), but only this bundle knows whether a `Page` carries one and whether it is published, which is why the alert lives here. A site with no `home` page **at all** raises nothing: answering `/` from a route of its own is a supported way to run (see `PageController::home()`), and nagging about it would be a false alert on every such site. Both are gated by `site-role-editor` — an admin who cannot edit a page can do nothing about either, and an alert nobody can act on is noise. Each links straight to what fixes it: the page creation form when no page carries the model at all, that very page's own form when it exists but is unpublished or trashed — legal page and home alike — where the publication switch is.

`SiteGuidedProjectProvider` (implements ConfigBundle's `GuidedProjectProviderInterface`) contributes nine replayable exercises to the dashboard's "Guided projects" panel: building a collection, creating a page, filling in what a page needs to be found and shared, checking a page's health, reworking one already online through duplicate + "publish as replacement", moving a page to the trash and pulling it back out, exporting pages to another site, putting a page in the navigation bar, and laying out the footer.

They are **ordered like the sidebar itself reads** — Collections, Pages, then the advanced "Menus" — so a project sits where the user finds the screen it walks, and two projects sharing a screen follow each other. `order` runs 50 → 74 in steps of **3**, not the 10 the neighbouring bundles use: ConfigBundle opens the sequence at 10, UiBundle picks it up at 90, and nine projects only fit between the two at that step. Two projects sharing an `order` would not fail — `GuidedProjectBuilder` sorts with `usort` — they would simply fall back on the order their providers happen to be registered in, which is exactly what `order` exists to decide.

Only the opening step of each carries an `url`: from there the panel walks the screen the user has been sent to, highlighting the button or the field they are meant to use next (`.action-new`, `#Page_title`, `[data-ui-sort-group="block"]`…). A collection field is pointed at through its row marker rather than an id: EasyAdmin's `collection_widget` replaces `form_widget_compound` entirely, so no `id` is rendered on it and its label is a `<legend>` with no `for`. A `ChoiceField` is pointed at through `#Entity_property + .ts-wrapper`, the widget TomSelect inserts: EasyAdmin gives a choice the autocomplete widget unless it asks for the native or the expanded one, and the original `<select>` is then left behind `.ts-hidden-accessible`, clipped to a pixel — an outline around it shows nothing. Both rules are locked by `SiteGuidedProjectProviderTest`, which reads each highlighted property's field class straight out of its controller. An action is pointed at through the `action-<name>` class EasyAdmin builds from the action's own name — `action-saveAndReturn` for the save button, not `action-save` — and an `ActionGroup` has to state that class itself through `setCssClass()`, `ActionFactory` only giving a default one to a plain action. The batch-export step points at `#form-batch-checkbox-all`, an id EasyAdmin's own index template renders rather than this bundle, and comes **before** the export button: the batch actions stay hidden until at least one row is checked.

Each project is gated by the strictest role any of its own steps needs — `site-role-editor` for seven of them, `site-role-admin` for the trash and the export, whose `restore`/`deletePermanently`/`exportSelection` are restricted to it. An admin without the role is never offered a parcours ending on an access-denied page. The export project stops at the zip: re-uploading it is ConfigBundle's "Content import" screen, which is `ROLE_SUPER_ADMIN` and belongs to that bundle's own projects, so the last step names it instead of sending the user there — a step carrying a second `url` would leave the page the previous one points into. Nothing is derived from the site's own data, so a project is worth following on a site already full of pages, and worth replaying once done (see ConfigBundle's README, "Contributing guided projects from other bundles").

---

## Collections

A `CollectionGroup` (e.g. "Projects") is a named, slugified container of `CollectionItem` rows — a generic "title/description/image/link" item. One `CollectionItem` table backs every collection across a site; what separates "Projects" from "Team" is only which `CollectionGroup` each item belongs to. Managed via two CRUDs, on purpose kept as two separate steps:

1. **`CollectionCrudController`** ("Collections" in the dashboard menu) creates/renames/deletes the collections themselves — just a `name` and its `slug` (via EasyAdmin's `SlugField`, auto-filled from `name` but editable, unique site-wide like `Page::$slug`, normalized and de-duplicated server-side by the same recipe as `PageCrudController`). Its index row exposes an "Items" action.
2. **`CollectionItemCrudController`** manages one collection's items at a time, reached only via that "Items" action (`?collectionGroup=<id>`) — never a free-typed field on the item itself, so a typo can no longer silently spawn a brand-new, unrelated collection. The familiar EasyAdmin grid (drag-and-drop reorder included) is filtered to that one collection; a "← Collections" action switches back. An item's own `slug` is unique **within its collection only** (`UNIQ_COLLECTION_ITEM_GROUP_SLUG`, not globally like `Page::$slug`).

Access to both is controlled by the `site-role-editor` key in ConfigBundle.

`CollectionItemSourceProvider` (implements `c975l/ui-bundle`'s `CollectionSourceProviderInterface`) exposes every `CollectionGroup` as its own source, keyed `site.collection.{slug}` — creating a brand new collection via `CollectionCrudController` is enough to make it pickable in UiBundle's `collection` block, no code change needed.

Each source also declares its own cache tag (`site_collection_<id>`, keyed on the id so renaming a collection doesn't orphan its entries), which `CollectionCacheInvalidationListener` invalidates whenever an item or the collection itself is saved, added or removed — without it, a `collection` block would keep serving UiBundle's cached render of the items as they were before the edit.

`c975l:site:collection-item:import` (see [Commands](#commands)) migrates a legacy hand-maintained JSON array of items into `CollectionItem` rows for a given collection (`--group`, matched/created by name if it doesn't exist yet), for an app switching from a JSON-driven list to this CRUD + the `collection` block. `CollectionItemExportProvider`/`CollectionItemImportProvider` plug Collection items into ConfigBundle's **Export sync (everything)** dashboard shortcut and **Import content** screen, doing the same collection auto-creation on import.

### Item detail pages

A `CollectionSourceProviderInterface` implementation can optionally expose a `detail` callable (see UiBundle's own README) so each item in its source gets a per-item URL, without a dedicated `Page`/`Block` row per item. `CollectionItemSourceProvider` implements it: each `CollectionItem`'s own `slug` (scoped to its `group`) is what `detail($slug)` resolves against, and it's also what UiBundle's `collection` block uses to link an item's title straight to its detail page once the block's `detailPage` field is set (see UiBundle's README, "Item detail pages") — no extra wiring needed beyond filling in `detailPage`.

Unlike the collection listing itself, the detail view is a **real `Page`**, with its own blocks — nothing here is auto-created. Setting it up is two ordinary editorial steps, done once per collection:

1. Create a `Page` for the detail view (any slug you like, e.g. `catalog-detail`), and give it whichever blocks the detail view needs — a `twig_content` block for a fully custom template, native blocks like `card`/`text_section` for a simpler layout, or a mix. Nothing is special-cased: this is a `Page` like any other, managed the same way (`PageCrudController`).
2. On the **other** `Page` that carries the `collection` block (the listing), fill that block's own `detailPage` field with the detail Page's slug from step 1.

From then on, requesting `/pages/{page}/{itemSlug}` (an unknown slug one level under the listing page) calls the source's `detail($itemSlug)`; a non-null result makes the detail Page's own blocks render as usual, with a `collectionItem` Twig global exposing that result to any of them for the duration of this one render (see `CollectionItemContext`) — a `twig_content` block reads it via its own `templatePath` field (`{% include templatePath with collectionItem.get() %}`), but any other block kind could read it too. The item's own `title` (by convention) becomes that URL's `<title>` instead of the listing page's. A `null` result (unknown item slug, no `detail` callable, no `detailPage` set, or that Page not found) falls through to a normal 404.

Nothing is persisted per item — see `PageController::resolveCollectionDetail()`.

---

## Themes

The site's colors, fonts and light/dark mode are admin-editable config keys (`group: theme`), **declared by `c975l/ui-bundle`** since the tokens they compile to are the ones its own CSS reads: `theme-color-primary`, `theme-color-secondary`, `theme-color-primary-dark-mode`, `theme-color-secondary-dark-mode`, `theme-color-background`, `theme-color-text`, `theme-font-family-title`, `theme-font-family-body`, `theme-font-family-accent`, `theme-mode` (`auto`/`light`/`dark`). Edited from the config screen's own `theme` group.

The 3 `theme-font-family-*` keys are `kind: font` (ConfigBundle), rendering a `<select>` instead of free text: the 3 CSS generics (`serif`/`sans-serif`/`monospace`) are always offered, topped up with the `font-family` names of whatever fonts an admin has uploaded (`FontService`, see below). Unlike the color keys, they're not `restricted` — any `ROLE_ADMIN`, not just `ROLE_SUPER_ADMIN`, can change them.

Uploading a font is [c975L/UiBundle](https://github.com/975L/UiBundle)'s job, which owns the `Font` entity, its back-office and the compiled `@font-face` stylesheet — see its readme, "Fonts". What stays here is the `theme-font-family-*` configs above: they are the three slots a design picks a family for, and their `<select>` is topped up with whatever an admin has uploaded.

Every change is compiled by **UiBundle's** `ThemeVariablesCssListener` (a Doctrine listener, also a `CacheWarmerInterface` so a fresh `public/bundles/build/site-theme.css` exists after a deploy even without an admin re-saving anything) into `--c975l-*` CSS custom properties, contributed by UiBundle's `ThemeVariablesStylesheetProvider` at a priority sitting between every bundle's compiled defaults and the app's own theme files, so the admin's values win over the former and lose to the latter. A bare custom font name picked for `theme-font-family-title`/`-body`/`-accent` also gets a generic fallback appended (`sans-serif`/`sans-serif`/`monospace` respectively) so the browser has somewhere to go if the `@font-face` fails to load. The same compiled file is inlined into emails via the `theme_variables_css()` Twig function — no more per-app `_user-variables.css`/`_user-typography.css` override stubs to keep in sync, the backoffice is now the single source of truth for both the site and its emails. Because the real site links UiBundle's concatenated `bundles/build/site.css` rather than `site-theme.css` directly, the listener also calls UiBundle's `StylesheetCacheWarmer::compileAll()` after every regeneration, so a theme change is reflected immediately instead of waiting for the next `cache:warmup`.

`theme-mode: dark` (or `auto` following the visitor's OS preference via `prefers-color-scheme`) swaps in a dark palette (see `sass/_theme-dark.scss`); `theme-color-primary-dark-mode`/`-secondary-dark-mode` optionally override just the accent colors for dark mode, falling back to the light-mode ones otherwise.

That palette swaps `--white` with `--black`, which is right for a surface that was white and turns dark, and wrong for ink laid on a ground staying colored in both modes: `--footer-text` and the mobile dropdown's `.menu-label` are a stated `#fff` rather than `var(--white)` for that reason — a design retuning the footer band's ink states its own value here the same way. `DarkThemeTextTokensTest` locks both. Printing is outside the modes altogether: the print sheet forces black ink on white paper with `!important`, pseudo-elements included, so a page read in dark mode doesn't print its own near-black ground.

### A site's own theme

One site, one theme: there is no catalog to pick from, and nothing to switch between. `c975l:scaffold:install` copies editable theme files into `assets/styles/themes/`, and the app owns them from then on, never synced back from the bundle. Each holds tokens shipped commented out at their own default value: uncomment a line to take that value over; what stays commented keeps following the bundle, later changes to its default included, so the active lines are at a glance exactly what the design decides.

**One file per bundle, named after it.** Each c975L bundle ships the catalogue of what *it* reads, the same way each ships its own `config/configs*.json`:

| File | From | Holds |
|---|---|---|
| `themes/ui.css` | UiBundle | shapes, buttons, forms, alerts, surfaces, sections, hero, card accents — everything the block layer reads |
| `themes/site.css` | SiteBundle | this site's chrome: navbar, footer, `--scroll-offset`, `--frame-background` |
| `themes/social.css`, `themes/shop.css`, … | the bundle that ships them | that bundle's own tokens |

**A bundle ships its file the day it reads its first token, never before.** An empty one placed in a site now would stay empty forever: `assets` is the one scaffold directory never overwritten once the target exists (see ConfigBundle's `ScaffoldInstaller`), so the tokens that bundle grows later would never reach the sites already holding the placeholder. Waiting means the file lands complete on its first install — and a bundle with no file of its own is not an oversight, it simply reads nothing but what `ui.css` already lists.

The split follows *who reads a token*, not who declares it. Everything UiBundle reads is in `ui.css` even when SiteBundle reads it too, because UiBundle runs without SiteBundle while the reverse is impossible — a site running ShopBundle or BookBundle standalone still gets its whole retunable surface. The commented values mirror each bundle's own defaults (`ScaffoldThemeTest` fails if the two drift apart, checking `site.css` and `ui.css` together — what matters to a design is that the union covers the surface, not which file a token landed in).

**Nothing to wire by hand.** The scaffold also installs `App\Service\ThemeStylesheetProvider`, which contributes the whole `themes/` directory to UiBundle's stylesheet registry — so the files are concatenated into the single `bundles/build/site.css` the bundles already share, instead of costing one request each (AssetMapper never merges CSS). Installing another c975L bundle drops its theme file next to the others and it is picked up with no change to that class. Carrying no `priority` where the bundles' providers carry `100`, it loads last, which is exactly where a theme has to be for its tokens to win. A site migrated from the era of a single hand-imported `themes/theme.css` gets a warning from `c975l:scaffold:install` as long as that stale `import` is still in its `app.js` or `app.css`, where it would now fetch a sheet the compiled stylesheet already holds — the warning tells you to keep that import instead, and re-run the command, as long as `App\Service\ThemeStylesheetProvider` isn't there to take its place.

**The site's own sheets go through it too.** Whatever the app keeps in `assets/styles/` itself — `app.css` and any partial next to it — is registered the same way, right after the themes, `app.css` always read last so the design keeps the final word. Importing a stylesheet from `assets/app.js` is the one thing not to do: that puts it in the import map, where AssetMapper addresses a CSS entry by a `data:application/javascript,` URL — a scheme `script-src` has no reason to carry, so the site's own Content-Security-Policy blocks it and the whole app entrypoint fails with it. Symfony's own recipe writes exactly that import, which is what `c975l:site:create` warns about.

That file is for **shapes and layout** — radii, navbar/footer, section flats (see UiBundle's [colored backgrounds](https://github.com/975L/UiBundle#colored-backgrounds), whose `--section-bg-*` are declared here). Colors and fonts are not in it: they belong to the admin, edited from the backoffice as described above, and no design token should second-guess them. Two other families stay out as well: the per-variant section tokens UiBundle mixes out of each flat's own background inside its `.section--bg-*` rules (`--section-text`, `--section-accent`, `--section-border`, `--section-overlay`…), which declared in `:root` would collapse the three variants into one — retune `--section-bg-*` instead — and the tokens JS writes on the element at runtime (`--image-compare-position`, `--slider-freeflow-vw`).

`--bottom-bar-height` stays out for a reason of its own: it isn't this bundle's to set. A bundle fixing a bar to the bottom of the viewport — ShopBundle's basket bar — declares it on `<body>` with that bar's height, and UiBundle's scroll buttons step over it by reading it (`a.backTop`, `bottom: calc(25px + var(--bottom-bar-height, 0px))`), neither bundle knowing the other's classes. A value in `:root` would raise them on every site, bar or not.

UiBundle's twelve `--block-accent-*` hues are the one exception to colors staying out: a card's accent field stores a color's name, not a place in the site's palette (see UiBundle's [card accents](https://github.com/975L/UiBundle#card-accents)), so pointing one at `var(--primary)` here folds that hue into the design without a single stored value changing.

A navbar painted with `--navbar-background: var(--primary)` has three tokens to inverse what would otherwise be invisible on it: `--navbar-site-name-color`/`--navbar-site-tagline-color` for the brand block, and `--navbar-btn-background`/`-background-hover`/`-color` for the single "primary" nav item's pill, whose defaults are UiBundle's `.btn-primary` colors.

Navbar and footer both bleed full-viewport-width past `--body-max-width`, each through its own pair of tokens: `--navbar-width`/`--navbar-margin-x` and `--footer-width`/`--footer-margin-x`. A design that frames the page inside that max-width sets the pairs it needs to `auto`/`0` here, instead of overriding `.menu`/`footer` from `app.css`. `--footer-margin-top` goes with them: a design stacking colored flats sets it to `0` so the footer band follows the previous flat with no strip of page background between the two. Left alone it reads UiBundle's `--section-space` — the one step the section-level blocks are parted by — so the footer follows the page's own rhythm at every width instead of the fixed `3em` it used to carry. The mobile base doesn't move — 48px, which is both what `3em` gave and where the step starts — and from there the gap grows with the viewport up to the 84px the sections part by, where the fixed value stayed short. `FooterMarginTopTest` locks it in both compiled stylesheets and in the scaffolded `site.css`.

The bar's own height comes from `--navbar-padding-y` (`8px`, the room kept above **and** below its line) and `--navbar-content-height` (`44px`, which sizes the burger — the touch target it asks for — and holds the 40px logo centered inside it). `--navbar-height` is the two put together and is what `.menu-header` takes as its floor, so the bar measures the same `60px` whether the burger is on screen or not, and `body.navbar-fixed` gives back exactly what a fixed navbar took out of the flow rather than an approximation of it. A design wanting a taller or tighter header retunes either of the first two here, and the third follows. The mobile dropdown hangs off the navbar itself (`position: absolute; top: 100%`) rather than off a viewport offset, so it follows the bar's real height whatever the brand block holds — a tagline, a site name — and stays attached to it once the page is scrolled. A fixed navbar is the one case where the bar has to stay on its floor, since that floor is what `<body>` gets back, so `.menu-fixed` hides the tagline. `NavbarHeightTest` locks all of it in both compiled stylesheets and in the scaffolded `site.css`.

`--footer-items-direction`/`--footer-items-justify` are the one pair an admin can take over: the footer menu's own **Display style** select (see [Footer: display style](#footer-display-style)) retunes both on the `.menu-items` element, which wins over whatever this file left on `:root`. A design still sets them here for the layout a site ships with — the select's default is precisely "whatever the theme decided".

`--font-size-body` is the size that copy is *read* at - a `text` block's paragraphs, a caption, an article, a legal page, a form, everything set in `--font-family-body` and left to inherit. Nothing declared it until now, so a site was read at whatever the browser defaults to (16px) and a design wanting a fuller body copy had nothing to turn. It defaults to `1rem`, i.e. exactly that, and is set in `rem` rather than `px` so a visitor who raised their browser's own font size is still followed. **Headings stay out of it**: `h1`-`h6` and `.lead` are sized in `rem`, laid out against the root rather than against the body copy, so raising this token opens up the paragraphs alone. They have a knob of their own instead, UiBundle's `--font-size-title-scale` (and `--font-size-eyebrow-scale` for the eyebrows), a factor applied to every title of the page at once — see [that bundle's readme](https://github.com/975L/UiBundle#colored-backgrounds) for why the titles move by a factor where the body copy moves by a size. UiBundle's own block titles, eyebrows and section texts are outside it too, being sized in `px` from `sass/_page-sections.scss` - a `feature_bar`'s items or a `cta_band`'s text follow their own scale, not this one. `BodyFontSizeTest` locks the token and the headings' unit in both compiled stylesheets and in the scaffolded `site.css`.

`--title-color` is the ink every heading is set in — `h1`-`h6`, wherever they are written. It defaults to `var(--primary)`, exactly what they were hardcoded to, so nothing moves until a design says otherwise. It exists because `--primary` is a **surface** everywhere else — the primary button, a `primary` flat, the footer band — where the white `--button-color` needs it dark: a page wanting its titles in another ink had nowhere to say so short of repainting the brand, and repainting the brand takes the buttons with it. Same pattern as UiBundle's own `--card-accent` / `--flip-card-accent`. What reaches for it is a page laid on a ground that is not the site's: a deep brand color reads at barely more than 1:1 on a near-black page, which is why GalleryBundle's `dark` style sets this token rather than touching `--primary`.

`--link-hover-color` is what a link turns when the mouse passes over it, the underline being what marks the state either way. It defaults to `var(--link-color)`, i.e. no change of ink at all. It exists because `--link-color` was carrying both jobs: a link *at rest* read `--primary` directly, and only the hover rule ever read `--link-color` — so setting the token that names a link's color moved the hover alone, and the lightened value a dark page gives it never reached a link at rest, which went on being painted in the raw brand color. A link at rest reads `--link-color` now, which is a **breaking change** for a site that had set that token to a hover-only accent: point `--link-hover-color` at the old value and set `--link-color` back to `var(--primary)` to keep exactly what it had. `TextInkTokensTest` locks both rules in the compiled stylesheets.

`--reading-max-width` is the measure body copy is laid out on — `.legal div`, `.text`, `.site-article` and the sliders sharing that column — well under `--body-max-width`, which frames the page and never carries a line of text. It defaults to `min(75ch, 90vw)`, in `ch` so the measure follows the body font instead of drifting as it changes; a design that kept the previous 800px sets `--reading-max-width: min(800px, 90vw)` here.

**A token is declared on `:root, [data-theme]`, never on `:root` alone** — the scaffolded `site.css` opens with that selector, and so do `sass/_variables.scss` here and `sass/_tokens.scss` in UiBundle. A `var()` written inside a custom property's value is substituted **on the element carrying the declaration**, not where the token is read: declared on `:root` only, every derived token (`--title-color`, `--surface-alt`, the `--footer-*` and `--navbar-*` pairs, UiBundle's `--section-bg-*`) is computed once against the root's palette and descends already resolved. A scope opened further down — a `<body>`, a section, a single card — then repaints `--background` and `--text` and gets everything else in the colors of the ambiance it sits in. With the second selector, any element carrying `data-theme` redeclares the whole chain against the palette in scope, so a site holding two universes (`<div data-theme="chaos">`) writes its own values under `[data-theme="chaos"]` in `app.css` and the derived tokens follow. Keeping only `:root` in your own theme file is what breaks it, the bundle's default being declared on the very element your rule reaches by inheritance alone. The admin's palette is unaffected: it is compiled into `--c975l-*` properties on `:root`, which the tokens read through `var()` — and a custom property inherits into every scope. `ThemeScopeTest` locks the selector in the compiled stylesheets and in the scaffolded `site.css`.

Fixed dark mode stays on `:root[data-theme="dark"]`, i.e. the page as a whole: it has to outrank whatever a site left on `:root` in its own theme file, which loads last. A `data-theme` scope inside an auto-dark page therefore reads the light defaults again — which is what "opening an ambiance of its own" means, the scope stating its colors rather than inheriting the visitor's.

Rules that override a bundle's own classes belong in `app.css`, not in `theme.css` — the split is "values on one side, rules on the other", not "mine versus the bundle's".

Only this bundle's and UiBundle's tokens are listed, not those of every c975L bundle a site happens to install — `theme.css` ships with SiteBundle, which has no business enumerating what it doesn't own. Each bundle documents its own (e.g. SocialBundle's `--social-share-*`), and several of them are read per-variant, so declaring one in `:root` collapses every variant that bundle offers into a single look — SocialBundle's share button styles, picked from its dashboard, stop having any visible effect. A design that really needs one overrides it in `app.css`, where taking a bundle's own rules over already belongs.

---

## General components

All components below read their data from ConfigBundle. No props are needed — just include the tag and set the corresponding keys via the ConfigBundle dashboard.

### Matomo

**Moved to `c975l/ui-bundle`**, as `<twig:c975LUi:Analytics:Matomo />` — the same move the cookie banner made, and for the same reason: UiBundle already writes the legal text naming the Matomo instance (its cookies model offers the opt-out link), so it read a key this bundle declared. The three keys `site-matomo-url`, `site-matomo-id` and `site-enable-matomo` are declared there now, next to `site-enable-cookie-consent` in the same `analytics` group — the backoffice screen doesn't move, and neither do the values already stored. The component carries its own `site-enable-matomo` guard, and core-bundle's layout renders it, so a site overriding `footer` keeps its tracking. See UiBundle's readme.

### CookieConsent

**Moved to `c975l/ui-bundle`**, as `<twig:c975LUi:Cookie:Consent />` — a GDPR banner is not something a site running a shop without page management may go without, and UiBundle already owned the other half of the contract (its `video_iframe` block waits on `window.CookieConsent`). The component carries its own `site-enable-cookie-consent` guard now, and core-bundle's layout renders it, so a site overriding `footer` keeps its banner. `url-cookies-policy` is declared there too. See UiBundle's readme.

### HostedBy / MadeBy

Set `site-hosted-by-url` + `site-hosted-by-logo` + `site-hosted-by-name` and/or their `site-made-by-*` counterparts in ConfigBundle, then include the components (typically in the footer):

```twig
<twig:c975LSite:General:HostedBy/>
<twig:c975LSite:General:MadeBy/>
```

What each one shows is `display-hosted-by`/`display-made-by`, a choice between `none`, `logo`, `name` and `logo-name`, read through ConfigBundle's `credits_mode()` Twig function. A part the mode asks for but no config fills is skipped: `logo-name` with no name renders the logo alone, and a mode asking for a logo nobody set renders nothing. The logo also needs its URL (it goes through `<twig:c975LUi:Image:Link/>`), the name doesn't — without one it is plain text rather than a dead link.

`MadeBy` takes its label from ConfigBundle's `made_by_label()`, which reads `made-by-wording`: `made` renders "Réalisé par", `powered` renders "Propulsé par". A site built by the party it credits says the first, one only running its system says the second.

### Preconnect

Set `site-preconnect` in ConfigBundle to a JSON array of external origins to preconnect to, i.e. `["https://975l.com"]`. Useful when `HostedBy`/`MadeBy` logos are served from a third-party domain. Empty by default, so it has no effect unless configured.

`site-matomo-url`'s own origin is preconnected automatically, without having to be repeated here — its script is fetched from a third-party host, so the DNS lookup and TLS handshake would otherwise only start once that JS runs. The key is UiBundle's since it owns the component, this layout only reads it.

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

The legal models (legal notice, privacy policy, terms of sales, terms of use, cookies, copyright), the
`legal_model` block rendering them and the **Management → Legal models** screen customizing them section by
section all live in **UiBundle** — a site running it with a shop but no page management needs them just as
much. See its readme, under "Legal models".

What stays here is the page side of it: `c975l:site:pages:import-defaults` creates one page per model (see
[Import default pages](#import-default-pages)), `site_legal_pages()` lists them
(see [Twig extensions](#twig-extensions)), and `SiteBlockLocationProvider` tells that screen which page each
document sits on, and at which public address.

---

## Download controller

`DownloadController` moved to `c975l/ui-bundle` along with the rest of the shared plumbing; the `download_file` route keeps its name:

```twig
{{ path('download_file', { file: 'path/to/your_file.csv' }) }}
```

`AssetController` and its `/asset/{file}` route are **gone**: nothing in the ecosystem or in any site called them, and the web server already serves `public/`.

---

## Twig extensions

`route_exists()`, `template_exists()`, `asset_exists()`, `|nl2br` and `|linkify` are contributed by [c975L/UiBundle](https://github.com/975L/UiBundle), and `canonical_url()`/`site_copyright()` by [c975L/ConfigBundle](https://github.com/975L/ConfigBundle) — nothing about them concerns the notion of a site, and every bundle's templates can use them. What this bundle adds on top, all of it about a `Page` or a `Menu`:

| Function | Description |
| --- | --- |
| `site_page(id)` | The `Page` of that id, or `null` |
| `site_legal_pages(models)` | The pages carrying one of the given legal models, keyed by model (see [Legal models](#legal-models)) |
| `site_page_for_form_block(formName)` | The page carrying the `form` Block of that form, backing UiBundle's `form_url()` (see [Users](#users)) |
| `menu_blocks(location)` | The ordered blocks of the `navbar`/`footer`/`email-header`/`email-footer` Menu (see [Menus](#menus)), alongside `menu_link_url()`, `menu_link_label()` and `menu_link_is_copyright()` |
| `page_health_check(page)` | The page's own health check panel, rendered in the back-office (see [Health check](#health-check)) |

All of them are declared with Twig's `#[AsTwigFunction]` attribute, directly on the method that backs them: `MenuExtension`, `PageExtension` and `PageHealthCheckExtension` are plain autowired services, no longer `AbstractExtension` subclasses with a `getFunctions()` to keep in sync. A site overriding one of them decorates or replaces the service as usual, and carries the attributes over — the function names live nowhere else (`TwigFunctionRegistrationTest` locks them for this bundle).

Two of UiBundle's own are worth knowing here, both used by core-bundle's `layout.html.twig`: `theme_variables_css()`, returning the CSS compiled from the admin-editable theme configs (see [Themes](#themes)) for inlining where a `<link>` isn't possible (e.g. emails), and `font_preloads()`, returning the font files the current theme actually uses to emit as `<link rel="preload">` in the `<head>`.

---

## Email templates

Pre-built email templates are available at `@c975LSite/emails/`:

| Template | Description |
| --- | --- |
| `layout.html.twig` | Base email layout |
| `fullLayout.html.twig` | Full email layout |
| `header.html.twig` | Email header — renders the `email-header` Menu's blocks (see [Menus](#menus)), edited from the backoffice, independently from the site navbar |
| `footer.html.twig` | Email footer — renders the `email-footer` Menu's blocks (see [Menus](#menus)), edited from the backoffice, independently from the site footer, as one centered inline row of grey, small, undecorated links matching the "sent by" line below it (`sass/_email-footer.scss`, not the page's colored footer band) |
| `contact_notification.html.twig` | "contact" Form's notification email |
| `emailTemplateLayout.html.twig` | Wraps an admin-authored `EmailTemplate`'s rendered body (see below) |

CSS is inlined automatically via `twig/cssinliner-extra`. The minified stylesheet (`emails.min.css`, compiled from `sass/emails.scss`, including its `:root` variables) is embedded, followed by the admin-editable [theme](#themes) colors/fonts (`theme_variables_css()`) so they win the cascade.

`fullLayout.html.twig`'s own copy (no-spam notice, "hello", closing/thanks, "sent by", legal mentions) isn't hardcoded translations — it's authored as rich text (`kind: html`) directly in ConfigBundle, under the `email` group: `email-text-no-spam`, `email-text-hello`, `email-text-closing`, `email-text-sent-by`, `email-text-legal`. Each block only renders if its config value is non-empty, and **all five ship empty**: an email carries the copy its own site wrote and nothing else. They used to ship four ready-made French paragraphs, which read as French on a site that isn't — and, being the value rather than a fallback, could not be told apart from a paragraph an admin had deliberately taken away. `email-text-closing` and `email-text-sent-by` support a `%site%` placeholder, replaced with `site-name`. This lets the client rewrite their own email copy, including in `email-text-legal` (share capital, registration number...), from the backoffice without touching translations or templates.

`contact_notification.html.twig` just `extend`s `@c975LSite/emails/layout.html.twig` and renders its actual content via [c975L/UiBundle](https://github.com/975L/UiBundle)'s `email_template_body('contact_notification', {...})` Twig function — its body is an admin-editable `c975L\UiBundle\Entity\EmailTemplate` row (`EmailTemplateCrudController`, "Email templates" dashboard entry) rather than hardcoded markup. `DefaultPagesImporter` seeds it through UiBundle's `FormSeeder` the first time pages are imported. See UiBundle's own README for the `EmailTemplate`/`EmailBlock` mechanism.

The registration and reset-password emails have no template file at all anymore: ConfigBundle composes them from their own `account_validation`/`password_reset` `EmailTemplate` and hands the result to `EmailLayoutProvider` below — so they come out in this bundle's branded layout when it is installed, and in UiBundle's plain fallback otherwise.

`EmailLayoutProvider` implements UiBundle's `EmailLayoutProviderInterface` (auto-discovered), wrapping any other `EmailTemplate`'s rendered body (`EmailTemplateCrudController`'s preview, a real send via `SendEmailFormAction`...) in `emailTemplateLayout.html.twig`, itself extending `layout.html.twig` — so it renders with the exact same header/footer/theme as the rest of the site's emails, instead of UiBundle's bare standalone document.

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
| `php bin/console c975l:scaffold:install` | Re-runnable: (re)installs every installed c975L bundle's scaffold files into the project (`--path=` to restrict it, `--dry-run` to only list what would change) |
| `php bin/console c975l:site:pages:import-defaults` | Creates default pages (home, legal notice, privacy policy, CGU, CGV, cookies) if they do not already exist |
| `php bin/console c975l:site:smoke-test` | Checks every published page, and the css/js assets the home page references, answer 200 - non-zero exit code on the first failure (`--pages-only` skips the assets, a site in maintenance is skipped entirely) |
| `php bin/console c975l:site:collection-item:import --group=<group> --json-file=<path>` | Imports a legacy JSON array of items into [`CollectionItem`](#collections) rows for a given collection (`--images-dir`, `--dry-run` options) |

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
   you don't want it committed (the command adds it to `.gitignore` automatically, alongside
   `public/medias` and the singleton graphics written at the root of `public/` — what the
   back-office uploads at runtime travels between environments through the content export/import,
   never through git, or the next deploy's working-tree reset would wipe it).
2. **Default config** — runs `c975l:config:load-all` internally.
3. **Vault key** — generates and writes `C975L_VAULT_KEY` to `.env.local` if it isn't defined yet,
   then stops if the database still holds sensitive config encrypted with another key (a reused
   database, or an earlier run whose key was lost): the slugs are named right there, instead of the
   config-values step dying on a bare "Decryption failed" thrown from inside the vault. Put the old
   key back by replacing the `C975L_VAULT_KEY` line just written at the end of `.env.local` (Dotenv
   keeps the last assignment), or empty those values, then re-run.
4. **Admin account** — asks for an email and password (typed in clear text, not masked, so you can
   see what you're entering — it is never echoed back afterwards, including in the final summary),
   creates the user with `ROLE_ADMIN`, `isVerified = true`, `isEnabled = true`.
5. **Config values** — asks for the values listed in `config/site-create-questions.json` (see
   below), validated against each config's `kind`.
6. **Default pages** — same as `c975l:site:pages:import-defaults` below, but page by page:
   confirms the import and the initial `isPublished` state for each page not already in database.
7. **Footer menu** — offers to add, one by one (yes by default), every bundle-contributed route
   registered via `LinkableRouteProviderInterface`, then the legal pages just imported, in a fixed
   order (mentions légales, règles de confidentialité, CGU, CGV, cookies, copyright). Re-running
   the command never creates duplicate items.

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

`c975l:scaffold:install` is shipped by [c975L/ConfigBundle](https://github.com/975L/ConfigBundle) — the tool installing every bundle's `scaffold/` has no reason to live in one of them — and is the standalone, re-runnable counterpart of step 1 of `c975l:site:create`
(the one gated by `.c975l-site-created`). Use it whenever you `composer require` a c975L bundle
into a site that's already past the one-shot wizard, to pull in that bundle's
`scaffold/{src,templates,tests,translations,assets}` files:

```bash
php bin/console c975l:scaffold:install
```

Where the wizard forces its install — a brand new site has nothing of its own to preserve — this
one keeps what the site made its own: a target already identical to the scaffold source is left
untouched (so running it again on an unmodified project is a no-op), a target the site never
touched is refreshed silently, and a target the site has customized since is reported rather than
overwritten. `--force` takes the wizard's behavior back for those, each moved to
`existingFiles/<same path>.old` first (never silently erased).
`assets/` files are the one exception: once a target exists there, it's left alone for good (no
backup, no overwrite, even if the bundle's own copy later changes) since it's meant to become the
app's own editable file from that first copy onward — see [A site's own theme](#a-sites-own-theme).

The same rule deletes what a bundle has *withdrawn*, from `c975l/core-bundle` v1.2 on: `scaffold/removed.json` lists every scaffold file this bundle stopped shipping, against the hashes of all the versions it ever delivered. A site's copy still matching one of them is deleted, anything else is the site's own work and is only reported as obsolete. That's how `src/Command/SitemapCreateCommand.php`, `src/Security/EmailVerifier.php` or the stale `templates/bundles/c975LSiteBundle/emails/footer.html.twig` override finally leave a site scaffolded before they were dropped — `--dry-run` lists them first, like everything else.

Two options narrow that down, for the case the command was awkward at: propagating **one** upgraded
scaffold file to a site (or to a dozen of them), without passing over every other file that site may
have diverged on since.

```bash
# What would change, writing nothing - the files are listed, not just counted
php bin/console c975l:scaffold:install --dry-run

# Only this subtree, or only this file (repeatable)
php bin/console c975l:scaffold:install --path=src/Scheduler --path=tests/Scheduler
```

`--path` takes a path relative to the project, naming a directory or a single file — never a mere
prefix of one, so `--path=src/Scheduler` leaves a `src/SchedulerOther/` alone. Combine the two and
`--dry-run` wins: nothing is written, no backup is moved, and the `.gitignore` isn't touched either.

A path no scaffold file answers to — a typo, or a path given as it stands in the bundle
(`scaffold/src/Scheduler`) — is named and the command exits non-zero, rather than reporting the zero
counts of an already up-to-date site: a loop propagating one file across a dozen sites stops there
instead of scrolling green.

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

### Panther console-error test

Not part of the scaffold (no `scaffold/tests/Manual` in this bundle), so it has to be copied by
hand into a new site — but every site that has one should also carry the matching `composer.json`
change below. `tests/Manual/ConsoleErrorsTest.php` drives a real headless Chrome over every public
page (`Page` entities + a few hardcoded routes) and fails on any `SEVERE` browser console entry —
catches things BrowserKit-based functional tests can't see, since those never execute JS. Excluded
from the default PHPUnit suite (see `phpunit.dist.xml`'s `testsuite` exclude, or keep it in a
separate `tests/Manual` directory the default suite doesn't cover) and run separately:

```json
"scripts": {
    "test": "@php bin/console cache:warmup --env=test && phpunit && phpunit tests/Manual"
}
```

The `cache:warmup` step matters: `public/bundles/build/*.css` (`site.css`, `admin.css` from
UiBundle's `StylesheetCacheWarmer`, `site-theme.css` from its `ThemeVariablesCssListener`)
is gitignored — `public/bundles/` is never committed, only ever (re)generated by `cache:warmup`/
`cache:clear`. Without warming the `test` environment first, a stale or freshly-cloned checkout
gets a real 404 on those stylesheets, which `ConsoleErrorsTest` correctly reports as a console
error — a local-environment gap, not a code regression, but indistinguishable from one without
this line. Needs `symfony/panther` (`composer require --dev symfony/panther`) plus a
version-matched chromedriver + Chrome/Chromium binary (`PANTHER_CHROME_BINARY` in `.env.test.local`).

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
| `creer-un-compte` | `register` | `crear-una-cuenta` | Créer un compte | `form` → `register` |
| `mot-de-passe-oublie` | `forgot-password` | `contrasena-olvidada` | Mot de passe oublié | `form` → `reset_password_request` |
| `contact` | `contact` | `contacto` | Contact | `form` → `contact` |

The last three each carry a generic `form` Block pointing at their matching `c975L\UiBundle\Entity\Form` by name (see [Users](#users), the registration and anti-spam side of it being ConfigBundle's now) — `DefaultPagesImporter` seeds that `Form` (and its `EmailTemplate`, for register/reset-password-request) alongside the page itself if not already present.

`home` is always the same slug across locales — `PageController` looks it up literally, so only one homepage can ever exist. All pages are created as **unpublished** — review and publish them individually from the admin. Pages whose slug already exists are silently skipped, so re-running the command after adding a new `enabled_locales` entry only creates the missing locale's pages.

The legal pages and `contact` are seeded with a meta description of their own, each within the 50-160 character window `content-quality` checks, so a fresh site doesn't start with a health check warning on the pages it just created. `home` and the account pages get none: a home page's description belongs to the site, not to a default. The seeded form fields carry no placeholder either — a field shows its label alone until an admin types one in.

---

## Scheduler

This bundle declares one scheduled command, `c975l:site:smoke-test`, nightly in a 5am-7am window through `SiteMaintenanceTaskProvider` (see [Smoke test](#smoke-test)). The rest moved to ConfigBundle alongside the whole Messenger stack: `c975l:config:messenger-cleanup` joined `c975l:sitemaps:create`/`c975l:health-check:run`/`c975l:config:backup`/`c975l:config:backup:digest` there (see [ConfigBundle's Backup section](https://github.com/975L/ConfigBundle#backup) — every satellite bundle needs them whether or not it has SiteBundle installed). The schedule class itself is scaffolded by ConfigBundle and lives in your app, so each project controls its own timing.

### 1. Create the schedule class

```php
// src/Scheduler/MaintenanceSchedule.php
namespace App\Scheduler;

use c975L\ConfigBundle\Scheduler\MaintenanceScheduleBuilder;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('site')]
class MaintenanceSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private readonly MaintenanceScheduleBuilder $builder,
        private readonly CacheInterface $cache,
    ) {}

    public function getSchedule(): Schedule
    {
        return $this->builder->addTasks(
            (new Schedule())->stateful($this->cache)
        );
    }
}
```

The `stateful()` call persists the last-run time via Symfony Cache so tasks are not re-run if the worker restarts.

**This file lists no command at all**, and that's the point: each bundle declares the commands it needs run through ConfigBundle's [`MaintenanceTaskProviderInterface`](https://github.com/975L/ConfigBundle#contributing-maintenance-tasks-from-other-bundles) — backups, sitemaps, health checks and the nightly smoke test all come from the bundles that own them. Installing a bundle schedules its tasks, removing it stops them, and neither needs an edit here. Their exact minutes are drawn from this site's own identity ([`ScheduleSpreader`](https://github.com/975L/ConfigBundle#spreading-scheduled-commands-across-installs)), so sites sharing a server don't all dump their database at the same minute; `bin/console debug:scheduler` shows the times this one ended up with.

Two things stay yours. A command no bundle knows about is added after the call, spread (inject `ScheduleSpreader` alongside the builder) or on a fixed time with a plain `RecurringMessage::cron()`:

```php
$schedule = $this->builder->addTasks((new Schedule())->stateful($this->cache));
$schedule->add($this->spreader->spread('# #(4-7) # * *', new RunCommandMessage('app:check-external-links')));

return $schedule;
```

And a declared command this site shouldn't run at all is named as the second argument, rather than having to drop the bundle:

```php
return $this->builder->addTasks(
    (new Schedule())->stateful($this->cache),
    ['c975l:health-check:run --frequency=monthly']
);
```

This class is scaffolded once (`c975l:scaffold:install`, or `c975l:site:create` on a brand new site) into your app's own `src/Scheduler/MaintenanceSchedule.php`. Since it carries no command list of its own anymore, a bundle upgrade that changes the *scaffold* can be taken as it stands: `c975l:scaffold:install --dry-run --path=src/Scheduler --path=tests/Scheduler` to see what would change, then the same command without `--dry-run` to apply it — instead of being merged back by hand from the moved-aside `existingFiles/*.old` copy. Take the two together: the scaffolded test is written against this class's constructor, and re-scaffolding one without the other leaves them disagreeing on it.

### 2. Start the worker

Run the consumer as a long-lived process (supervised by Supervisor or systemd):

```bash
php bin/console messenger:consume scheduler_site
```

You may keep a cron entry that restarts the worker daily (e.g., at 00:25) to recover from crashes without monitoring the process continuously:

```bash
25 0 * * * systemctl --user start messenger-worker@your-site.service
```

### 3. Route `RunCommandMessage`, for the on-demand runs

The scheduler dispatches its own commands through its `scheduler_site` transport, but the dashboard's **"Run health check now"** button (ConfigBundle) queues `RunCommandMessage` on the default bus instead — it has no schedule to attach to. Route it to an asynchronous transport, and have the worker consume that one too, otherwise Messenger handles it synchronously and the button blocks the admin request just as it used to:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            Symfony\Component\Console\Messenger\RunCommandMessage: async
```

```bash
php bin/console messenger:consume async scheduler_site
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

{% block heading %}
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
{% endblock %}
```

---

## AI agent skills

The package ships four skills of its own, written for the coding agent of the site installing this bundle rather than for someone modifying it. Point your agent at the directory:

```text
vendor/c975l/site-bundle/skills/
```

| Skill | Covers |
| --- | --- |
| `c975l-site-layout` | the layout and its Twig blocks, the theme tokens, the CSP nonce, the error pages, the branded email layouts |
| `c975l-site-pages` | `Page`, file-based pages, the block kinds this bundle adds, publish as replacement, collections and their item detail pages |
| `c975l-site-menus` | the four menu locations, `menu_link` targets and anchors, `menu_group`, the navbar and the footer's display style |
| `c975l-site-seo` | sitemaps, canonical urls, the Open Graph image, the six health checks, the smoke test and the dev profile |

They are split by subject rather than shipped as one file so that an agent loads the one it needs — a question about a menu doesn't pull in the health checks. Each holds what an agent gets wrong when left to its own habits: that a replacement layout still owes a `container` block, that a `style=""` attribute is dropped by the nonce, that a menu link stores an id rather than a slug, that a satellite exposes a route through `LinkableRouteProviderInterface` instead of depending on this bundle.

Nothing is installed, nothing is copied into your project: the files sit in `vendor/` like any other part of the package and follow it at each `composer update`. A user of Claude Code wanting one to load by itself symlinks it into their own skills directory:

```bash
ln -s ../../vendor/c975l/site-bundle/skills/c975l-site-menus .claude/skills/c975l-site-menus
```

`Tests\SkillsTest` keeps them honest: every path, route, config slug, command, class member, Twig function, block kind and component they quote is checked against the sources — and, for a name belonging to another c975L package, against the installed vendor tree — so renaming any of them fails the build rather than leaving an agent confidently wrong.

---

> [!TIP]
> If this project **helps you save development time**:
>
> - [**star** it on GitHub](https://github.com/975L/SiteBundle) — helps others find it
> - [**open an issue**](https://github.com/975L/SiteBundle/issues/new) to share how you use it — genuinely useful feedback
>
> And if you'd like to support the work directly, the **Sponsor** button at the top of the GitHub page is there for that. Thank you!
