---
name: c975l-site-layout
description: "Use this skill when working on the shell of a page in a Symfony application built on the c975L ecosystem with c975l/site-bundle — the layout, its Twig blocks, the theme tokens, the error pages, the email layouts or the footer components. Covers what a template must set, which block to override, where a design token belongs and what a Content-Security-Policy nonce forbids. Triggers on: layout.html.twig, bodyClass, heading, summarySocialNetwork, theme, themes/site.css, ScaffoldThemeTest, flashes, Scroll:Buttons, backTop, pullDown, --navbar-height, --reading-max-width, --title-color, --bottom-bar-height, error404, emails/fullLayout, HostedBy, MadeBy, Preconnect, theme_variables_css."
---

# c975L SiteBundle — layout, theme and emails

> The shell every page of a c975L site renders into: the base layout and its Twig blocks, the admin-editable theme, the error templates and the branded email layouts.

**Package:** `c975l/site-bundle` · **Namespace:** `c975L\SiteBundle\` · **Twig namespace:** `@c975LSite` · **Translation domain:** `site`

**Key source paths** (relative to the package root):
`templates/layout.html.twig`, `templates/components/General/`, `templates/Exception/`, `templates/emails/`, `src/Service/EmailLayoutProvider.php`, `sass/`, `scaffold/assets/styles/themes/site.css`, `config/configs.json`

**Related skills:** `c975l-site-pages`, `c975l-site-menus`, `c975l-site-seo` in this same package. The theme compiler, the block system and the fonts are in `c975l/core-bundle`.

## The layout contract

`c975l:scaffold:install` puts `templates/layout.html.twig` in the app — shipped by ConfigBundle, so the
scaffolded login pages have a shell whether or not this bundle is installed:

```twig
{% extends ['@c975LSite/layout.html.twig', '@c975LUi/layout.html.twig'] %}
```

Twig takes the first template of the list that exists: full layout with this bundle, minimal shell
without. **The file is the app's from then on.** Replacing it with your own markup makes it the parent
of the database pages too (`@c975LSite/pages/page.html.twig` extends `layout.html.twig`), so it must
hold up that end of the contract: define a `container` block, and read the `title` and `robots`
variables. A replacement defining neither renders every page empty, with no error at all.

**This bundle's layout is a child of core-bundle's, not a copy of it.** `@c975LUi/layout.html.twig` is
the single source for the whole `<head>`, the body skeleton and the SEO cascade; `@c975LSite/layout.html.twig`
extends it and adds only what having Pages, menus and a navbar brings — four block overrides (`header`,
`container`, `footer`, `navigation`) and four variables handed over to the parent before it renders:

```twig
{% set ogImageMedia = page.ogImage %}      {# opens the parent's share-image cascade #}
{% set headingDisplayed = page.isTitleDisplayed %}
{% set bodyClasses = bodyClasses|default('') ~ 'navbar-fixed ' %}   {# appended, never assigned #}
{% set bodyControllers = (bodyControllers|default('') ~ ' basic')|trim %}
```

A child layout may set variables outside a block — Twig compiles its body before the parent's — but
never output. The two body variables are **appended, never assigned**: an app layout extending this one
runs its own `set` first, and assigning would drop what it declared. **Anything concerning the `<head>`
belongs to core-bundle's layout**, where both shells read it.

**A satellite bundle never ships a page layout.** Its templates extend `layout.html.twig` by name, no
`@` prefix, and the app's file resolves it.

## What a page template sets

```twig
{% set title = 'My page title' %}
{% set summarySocialNetwork = 'A short summary for social networks.' %}
```

`summarySocialNetwork` feeds both `<meta name="description">` and `og:description`. A page setting
neither can state them from the back office instead, through the `UrlMetadata` row CoreBundle holds for
its url — the layout only reads that row for what the template left unsaid.


## Twig blocks of the layout

`head`, `meta`, `title`, `stylesheets`, `preconnect`, `fontPreload`, `body`, `bodyClass`, `header`,
`navigation`, `main`, `heading`, `flashes`, `container`, `content`, `share`, `navigationBottom`,
`footer`, `javascripts`, `importmap` — all of them core-bundle's except `navigation`.

`title` is the `<title>` tag and `heading` the `<h1>` printed above the content. **A template
suppressing its own heading overrides `heading`, never `title`** — the second empties the browser tab
and every share instead.

```twig
{% block share %}{{ parent() }}{# added content #}{% endblock %}
{% block share %}{% endblock %}   {# disabled #}
```

`flashes` is the one block carrying a guard, and the guard is **inside** it: the bag is only read for a
visitor carrying the session cookie, or for a request that started the session itself. Reading
`app.flashes` is enough to open a session — and a connection to its store — so an unguarded read costs
one on every anonymous page and every crawler hit, for flashes that cannot exist. **An override takes
the guard over with the block**: filled with something else than flashes it renders for everyone, and
filled with another read of `app.flashes` it has to ask `ui_can_hold_flash()` itself. Core-bundle owns
that function and its test suite locks the condition.

Only `success`, `info`, `warning` and `danger` carry a tint. The block maps the labels other bundles
emit onto them — `error` onto `danger`, `notice` onto `info` — and any other label onto `info`, an
untinted `alert-*` printing black ink on the dark page's own background.

Overriding `header` with more than the navigation inside drops the sticky navbar the stylesheet
arranges through that `<header>` (see `c975l-site-menus`).

`<body>` is a flex column at least as tall as the viewport, `<main>` taking the free space, so the
footer holds the bottom of a short page. A site-wide band contributed by another bundle renders
outside `<main>`, pulled in by core-bundle's layout with an include that keeps that bundle optional:

```twig
{{ include('@c975LSocial/shareButtons/default.html.twig', ignore_missing: true) }}
```

A direct call to that bundle's Twig function would resolve at compile time and break the layout of a
site not installing it. **Any bundle contributing a site-wide band follows this convention, its
template path being the public contract.**

## Content-Security-Policy

Core-bundle's layout asks for a `style` nonce as well as a `script` one, which makes `'unsafe-inline'` inert for
`style-src` on every public page: **a `style=""` attribute in one of your templates is dropped by the
browser.** Move those declarations to a class. Styles written from JavaScript are unaffected, the CSSOM
not being subject to `style-src`. If `nelmio/security-bundle` is installed, `CookieNonceGenerator`
decorates its nonce generator to keep the nonce stable across a visit, so Turbo re-executing a fetched
page's `<script>` is not blocked.

## Theme

Colors, fonts and light/dark mode are **admin-editable config keys declared by UiBundle** (`theme-color-*`,
`theme-font-family-*`, `theme-mode`), compiled into `--c975l-*` custom properties. Do not restate them
in CSS.

What a design owns is the shapes, in `assets/styles/themes/*.css`, copied into the app by
`c975l:scaffold:install` and owned by it from then on. **One file per bundle, named after it** —
`ui.css` for everything the block layer reads, `site.css` for this site's chrome (navbar, footer,
`--scroll-offset`, `--frame-background`), one more per satellite that reads tokens of its own. Every
token ships commented out at its default: uncomment a line to take it over, leave it and it keeps
following the bundle.

Tokens worth knowing, all in `site.css`:

| Token | Default | Retunes |
| --- | --- | --- |
| `--navbar-padding-y` / `--navbar-content-height` | `8px` / `44px` | the bar's height; `--navbar-height` is the two together |
| `--navbar-width` / `--navbar-margin-x`, `--footer-width` / `--footer-margin-x` | full viewport | whether the bars bleed past `--body-max-width` |
| `--footer-margin-top` | `--section-space` | the gap above the footer band |
| `--font-size-body` | `1rem` | the size copy is read at; headings are sized against the root and stay out |
| `--title-color` | `var(--primary)` | the ink of every heading, without repainting the brand |
| `--link-color` / `--link-hover-color` | `var(--primary)` / `var(--link-color)` | a link at rest, and on hover |
| `--reading-max-width` | `min(75ch, 90vw)` | the measure body copy is laid out on |
| `--footer-items-direction` / `--footer-items-justify` | column | the footer's layout, unless an admin picks a Display style |

**Declare a token on `:root, [data-theme]`, never on `:root` alone.** A `var()` inside a custom
property's value is substituted on the element carrying the declaration: on `:root` only, every derived
token is computed once against the root palette and descends already resolved, so a scope opened
further down cannot repaint it. `ThemeScopeTest` locks the selector.

Stay out of the theme file: colors and fonts (the admin's), the per-variant section tokens UiBundle
mixes inside its own `.section--bg-*` rules (declaring them in `:root` collapses the three variants),
and the tokens JavaScript writes at runtime. `--bottom-bar-height` stays out too, for a reason of its own: a
bundle fixing a bar to the bottom of the viewport (ShopBundle's basket bar) declares it on `<body>` with that
bar's height, and UiBundle's scroll buttons step over it by reading it — a value in `:root` would raise them on
every site, bar or not.

`App\Service\ThemeStylesheetProvider`, also scaffolded, contributes the whole `themes/` directory to
UiBundle's stylesheet registry, so the files are concatenated into the single `bundles/build/site.css`
rather than costing one request each. **Never import a stylesheet from `assets/app.js`**: AssetMapper
addresses a CSS entry by a `data:application/javascript,` URL, which the site's own CSP blocks, taking
the whole entrypoint down with it.

## Error templates

`error`, `error401`, `error403`, `error404`, `error410`, `error500` under `@c975LSite/Exception/`:

```twig
{% extends 'layout.html.twig' %}
{% block content %}{% include '@c975LSite/Exception/error404.html.twig' %}{% endblock %}
{% block share %}{% endblock %}
```

The layout defaults `robots` to `noindex, follow` on any page carrying a `status_code`, so no error
template can forget it.

## Emails

Templates under `@c975LSite/emails/`: `layout.html.twig`, `fullLayout.html.twig`, `header.html.twig`
and `footer.html.twig` (which render the `email-header` / `email-footer` menus), and
`emailTemplateLayout.html.twig`. CSS is inlined by `twig/cssinliner-extra`, the compiled theme being
appended through `theme_variables_css()` so the admin's colors win the cascade.

`fullLayout`'s own copy is not translations: it is rich text authored in the back office under the
`email` group — `email-text-no-spam`, `email-text-hello`, `email-text-closing`, `email-text-sent-by`,
`email-text-legal`. **All five ship empty**, each block rendering only when its value is not. The last
two accept a `%site%` placeholder.

`EmailLayoutProvider` implements UiBundle's `EmailLayoutProviderInterface`, so any `EmailTemplate` sent
anywhere in the ecosystem comes out in this bundle's branded layout — and in UiBundle's plain fallback
on a site not installing it. **Do not write an email layout in a satellite bundle**; send with
`wrapLayout: true` and this provider answers.

## Footer components

```twig
<twig:c975LSite:General:HostedBy/>
<twig:c975LSite:General:MadeBy/>
```

Both read `site-hosted-by-*` / `site-made-by-*` from the config, and what they show comes from
`display-hosted-by` / `display-made-by` (`none`, `logo`, `name`, `logo-name`) through ConfigBundle's
`credits_mode()`. `MadeBy` words its label through `made_by_label()`, reading `made-by-wording`
(`made` → "Réalisé par", `powered` → "Propulsé par" for a site only running the system).
`site-preconnect` is a JSON array of external origins to preconnect to, read by core-bundle's layout,
which adds the Matomo origin to it on its own.

Matomo, the cookie banner and the scroll buttons **belong to UiBundle, and its layout renders them** —
`<twig:c975LUi:Analytics:Matomo/>`, `<twig:c975LUi:Cookie:Consent/>` and
`<twig:c975LUi:Scroll:Buttons/>`. Rendered by the layout and not by the footer, so **a site overriding
`footer` keeps its tracking and its cookie banner**. The first two carry their own enable guard; the
buttons point at the `id="top"` set on `<body>` and at the `<span id="bottom">` closing it, so a layout
of your own keeps both anchors.

## Do not

- **Do not ship a page layout from a satellite bundle**, and do not `{% extends '@c975LSite/…' %}`
  with the `@` prefix from one — extend `layout.html.twig` by name.
- **Do not replace the scaffolded layout without defining `container` and reading `title`/`robots`** —
  the database pages render empty, silently.
- **Do not write a `style=""` attribute** in a template. The nonce makes it inert.
- **Do not read `app.flashes` outside the layout's guard.** The read opens a session for every visitor.
- **Do not import a stylesheet from `assets/app.js`.** CSP blocks the whole entrypoint.
- **Do not declare a theme token on `:root` alone.** Use `:root, [data-theme]`.
- **Do not set colors or fonts in a theme file** — they belong to the admin, in the config screen.
- **Do not call another bundle's site-wide band function from the layout.** Include its template with
  `ignore_missing`.
- **Do not write a `<head>` tag in this bundle's layout.** Core-bundle's is the only one, and a copy
  here drifts from it without a word.
- **Do not override `title` to hide a page heading** — it is the `<title>` tag. Override `heading`.
- **Do not hardcode the email copy.** The five `email-text-*` keys are the site's own.
