---
name: c975l-site-assets
description: "Use this skill when working on the front-end assets of a Symfony application built on the c975L ecosystem with c975l/site-bundle — the Stimulus controller barrels, the one Stimulus application a page shares, the importmap entries a bundle contributes, or the compiled stylesheets a bundle hands the layout. Covers why a barrel never starts an application of its own, what the consuming app owes its own stimulus_bootstrap.js, how a controller identifier is written and where a bundle's css comes from. Triggers on: controllers.js, controllers-admin.js, c975lStimulusApp, startStimulusApp, stimulus_bootstrap.js, barrel, importmap.php, ImportmapProvider, ScriptProvider, StylesheetProvider, BundleScriptProviderInterface, BundleScriptAdminProviderInterface, BundleStylesheetProviderInterface, BundleStylesheetManagementProviderInterface, assets:install, AssetMapper, sitemap-fields, publication-switch, basic, languages, menu-active, block-thumbs, styles.min.css, StimulusAppSharingTest, live controller registered twice."
---

# c975L SiteBundle — assets, barrels and the shared Stimulus application

> What a c975L bundle ships to the browser: two Stimulus barrels joining the one application of the page, the importmap entries that carry them, and the compiled stylesheets the layout loads.

**Package:** `c975l/site-bundle` · **Namespace:** `c975L\SiteBundle\` · **Twig namespace:** `@c975LSite` · **Translation domain:** `site`

**Key source paths** (relative to the package root):
`assets/controllers.js`, `assets/controllers-admin.js`, `assets/js`, `src/Service/ScriptProvider.php`, `src/Service/StylesheetProvider.php`, `src/Management/ImportmapProvider.php`, `sass`, `public/css`, `tests/Assets/StimulusAppSharingTest.php`

**Related skills:** `c975l-site-layout`, `c975l-site-pages`, `c975l-site-menus`, `c975l-site-seo` in this same package. The theme compiler, the stylesheet registry and the importmap checker are in `c975l/core-bundle`.

## Two barrels, front and back

A c975L bundle ships its Stimulus controllers as two barrels, never one:

| File | Loaded on | Registers |
| --- | --- | --- |
| `assets/controllers.js` | every public page | `basic`, `languages`, `menu-active` |
| `assets/controllers-admin.js` | every back-office page | `sitemap-fields`, `publication-switch` |

The controllers themselves are one file each under `assets/js`. Each barrel is loaded as its own
`<script type="module">` tag by the layout, so there is nothing to import in the app.

**Identifiers are kebab-case, and that is not a style choice.** Stimulus derives the
`data-<identifier>-*-value` attribute names from the identifier as registered, so a camelCase one
silently breaks every binding in the templates.

## One Stimulus application per page

**A barrel never calls `startStimulusApp()` for its own account.** It joins the application of the
page, held on `globalThis.c975lStimulusApp`, the first barrel loaded being the one that creates it:

```js
import { startStimulusApp } from '@symfony/stimulus-bundle';
import BasicController from './js/basic.js';

// Loaded as its own <script type="module"> tag (see importmap.php), joining the one Stimulus application of the page
globalThis.c975lStimulusApp ??= startStimulusApp();
const app = globalThis.c975lStimulusApp;
app.register('basic', BasicController);
```

The reason is what `startStimulusApp()` does beyond starting an application: it also registers
everything the **consuming app's** `controllers.json` enables — the Live Component controller, the
chart one, the autocomplete one. A page loading five c975L barrels, each starting its own, therefore
built those controllers five times over, and a Live Component answered five requests and morphed its
result in five times for one click. Nothing throws, nothing is logged: it only ever shows in a browser.

The contract is shared by every c975L bundle shipping a barrel, and written once in the ecosystem
notes rather than in each of them. `StimulusAppSharingTest` locks it here, reading the barrels off the
source — the repository has no browser to catch it in.

## What the consuming app owes

The app's own `assets/stimulus_bootstrap.js` is usually the file that creates the page's application,
so it is part of the contract and has to adopt the same line. Its `import` stays as it is; only the
assignment changes:

```js
const app = (globalThis.c975lStimulusApp ??= startStimulusApp());
```

An app left on a bare `startStimulusApp()` starts a second application next to the bundles' one, and
whatever its `controllers.json` enables is back to being registered twice.

## Importmap entries

Nothing is added to the app's `importmap.php` by hand. `ImportmapProvider` names the entry each barrel
needs, and ConfigBundle's importmap checker writes it on the first `composer update` after the install:

```php
'@c975l/site-bundle/controllers.js' => [
    'path' => 'assets/controllers.js',
    'entrypoint' => true,
],
```

`ScriptProvider` is the other half, and takes the same import names: it tells the front layout and the
dashboard **which** barrels to load, where `ImportmapProvider` tells the checker what entry each one
needs. A barrel added to one and forgotten in the other is either an entry nothing loads, or a script
tag pointing at an entry the importmap never got.

## Stylesheets

`StylesheetProvider` hands the registry the bundle's compiled sheets, by their public path:

- `bundles/c975lsite/css/styles.min.css` on every page, through `getStylesheets()`
- `bundles/c975lsite/css/block-thumbs.min.css` on the back office only, through `getManagementStylesheets()` — the silhouettes of this bundle's block kinds, for the visual picker

Those files are compiled from `sass` into `public/css`, and served from `bundles/c975lsite/` once
`php bin/console assets:install --symlink` has run. **Editing the css by hand is lost at the next
compilation** — the source is the sass, and both the expanded and the minified sheet are rebuilt.

The theme tokens are not here: the admin-editable palette is compiled by core-bundle and loaded after
every bundle's sheet so its values win the cascade, and a site's own design tokens live in its
`assets/styles/themes/` files, owned by the app.

## Do not

- **Do not call `startStimulusApp()` on its own in a barrel.** Join `globalThis.c975lStimulusApp`, or
  every controller the app's `controllers.json` enables is built once more per barrel.
- **Do not register a camelCase identifier.** Stimulus builds the `data-*` attribute names from it.
- **Do not add an `importmap.php` entry by hand** for a c975L bundle — `ImportmapProvider` carries it.
- **Do not name a barrel in `ScriptProvider` without naming it in `ImportmapProvider` too**, or the
  other way round.
- **Do not edit a file of `public/css`** — it is compiled from `sass`, and the edit dies at the next build.
- **Do not add a theme color or a font to a bundle stylesheet.** They belong to the admin's config screen.
- **Do not import a stylesheet from `assets/app.js`.** The Content-Security-Policy blocks the whole entrypoint.
