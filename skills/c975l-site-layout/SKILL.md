---
name: c975l-site-layout
description: "Use this skill when working on the shell of a page in a Symfony application built on the c975L ecosystem with c975l/site-bundle — the layout, its Twig blocks, the theme tokens, the error pages, the email layouts or the footer components. Covers what a template must set, which block to override, where a design token belongs and what a Content-Security-Policy nonce forbids. Triggers on: layout.html.twig, bodyClass, summarySocialNetwork, display mode, theme, themes/site.css, ScaffoldThemeTest, --navbar-height, --reading-max-width, --title-color, error404, emails/fullLayout, HostedBy, MadeBy, Preconnect, theme_variables_css."
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

`display` (default `html`) is the display-mode variable, for a template serving a screen and a PDF.

## Twig blocks of the layout

`head`, `meta`, `stylesheets`, `preconnect`, `body`, `bodyClass`, `header`, `navigation`, `main`,
`title`, `flashes`, `container`, `content`, `share`, `navigationBottom`, `footer`, `javascripts`.

```twig
{% block share %}{{ parent() }}{# added content #}{% endblock %}
{% block share %}{% endblock %}   {# disabled #}
```

`<body>` is a flex column at least as tall as the viewport, `<main>` taking the free space, so the
footer holds the bottom of a short page. A site-wide band contributed by another bundle renders
outside `<main>`, pulled in by an include that keeps that bundle optional:

```twig
{{ include('@c975LSocial/shareButtons/default.html.twig', ignore_missing: true) }}
```

A direct call to that bundle's Twig function would resolve at compile time and break the layout of a
site not installing it. **Any bundle contributing a site-wide band follows this convention, its
template path being the public contract.**

## Content-Security-Policy

The layout asks for a `style` nonce as well as a `script` one, which makes `'unsafe-inline'` inert for
`style-src` on every public page: **a `style=""` attribute in one of your templates is dropped by the
browser.** Move those declarations to a class. Styles written from JavaScript are unaffected, the CSSOM
not being subject to `style-src`. If `nelmio/security-bundle` is installed, `SessionNonceGenerator`
decorates its nonce generator to keep the nonce stable for the session, so Turbo re-executing a fetched
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
and the tokens JavaScript writes at runtime.

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
`credits_mode()`. `site-preconnect` is a JSON array of external origins to preconnect to; the Matomo
origin is added on its own.

Matomo and the cookie banner **moved to UiBundle** — `<twig:c975LUi:Analytics:Matomo/>` and
`<twig:c975LUi:Cookie:Consent/>`, each carrying its own enable guard.

## Do not

- **Do not ship a page layout from a satellite bundle**, and do not `{% extends '@c975LSite/…' %}`
  with the `@` prefix from one — extend `layout.html.twig` by name.
- **Do not replace the scaffolded layout without defining `container` and reading `title`/`robots`** —
  the database pages render empty, silently.
- **Do not write a `style=""` attribute** in a template. The nonce makes it inert.
- **Do not import a stylesheet from `assets/app.js`.** CSP blocks the whole entrypoint.
- **Do not declare a theme token on `:root` alone.** Use `:root, [data-theme]`.
- **Do not set colors or fonts in a theme file** — they belong to the admin, in the config screen.
- **Do not call another bundle's site-wide band function from the layout.** Include its template with
  `ignore_missing`.
- **Do not hardcode the email copy.** The five `email-text-*` keys are the site's own.
