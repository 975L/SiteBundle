---
name: c975l-site-menus
description: "Use this skill when working with the navigation of a Symfony application built on the c975L ecosystem with c975l/site-bundle — the navbar, the footer, the two email menus, menu links and their targets, anchors into a page's sections, the copyright line, the logo and tagline, or exposing another bundle's route as a menu target. Triggers on: Menu entity, menu_link, menu_group, MenuCrudController, menu_blocks, menu_link_url, menu_style, navbar, footer, email-header, email-footer, LinkableRouteProviderInterface, site-navbar-position, sticky navbar, navbar-z-index, site-navbar-show-name, site-tagline, site-menu-link-copyright-auto, anchor, absolute_urls, translate menu, management_menu_translate, TranslationController, translatable label."
---

# c975L SiteBundle — menus and navigation

> The navbar, the footer and the two email menus, each one row of the `Menu` entity, each composed of blocks like a page is.

**Package:** `c975l/site-bundle` · **Namespace:** `c975L\SiteBundle\` · **Twig namespace:** `@c975LSite` · **Translation domain:** `site`

**Key source paths** (relative to the package root):
`src/Entity/Menu.php`, `src/Controller/Management/MenuCrudController.php`, `src/Form/Block/MenuLinkType.php`, `src/Twig/MenuExtension.php`, `src/Controller/Management/TranslationController.php`, `src/Management/MenuBlockEditUrlProvider.php`, `templates/components/General/Navbar.html.twig`, `templates/components/General/Footer.html.twig`, `templates/blocks/`, `sass/_menu.scss`, `sass/_footer.scss`, `config/services.yaml`

**Related skills:** `c975l-site-layout`, `c975l-site-pages`, `c975l-site-seo` in this same package. The block system and its contexts are in `c975l/core-bundle`.

## One entity, four locations

`Menu` carries a `location` — `navbar`, `footer`, `email-header` or `email-footer` — one row each,
unique. Every location owns a single ordered `blocks` collection, the same UiBundle blocks a page
holds, so links and anything else sort freely together with no separate "items" collection.

**Except the navbar**, whose picker only ever offers `menu_link`: a navigation bar is a plain list of
links, anything else belongs in the page. That is UiBundle's exclusive `menu_navbar` context, not a
rule written here.

There is **no "new menu" form**: a menu's only own field is its location, so the index shows one create
button per location not created yet, each creating the row and opening its edit screen in one
CSRF-protected POST (`MenuCrudController::create()`). A location that already has its row just opens it
rather than hitting the unique constraint.

The navbar and the footer are rendered by the layout already — nothing to add in an app:

```twig
<twig:c975LSite:General:Navbar/>
<twig:c975LSite:General:Footer copyright="{{ copyright }}"/>
```

The email locations render inside `@c975LSite/emails/header.html.twig` and `footer.html.twig`,
independently, so a client keeps different content for emails than for the site. A `menu_link` resolves
to a **relative** url, which `emails/fullLayout.html.twig` rewrites against `site-url` on its way out,
so an email menu links to the live site — as long as that setting is filled, since an empty one leaves
the paths relative and the links dead.

## menu_link

Its target is either an existing `Page` — linked **by id**, so renaming the slug never breaks the link,
and an unpublished page stays pickable, flagged "(draft)", resolving to no url until published — or a
route another bundle contributed. A block disappears from the rendered menu on its own if its target
stops resolving: no dangling link, ever.

Optional fields: `label` (always overrides the derived one), `primary` (renders it as a filled button,
meant for a single stand-out item), `strong` (bolds the label alone). The two emphases stack.

### Anchors into a page

A target can point at a **section** of a page. Any UiBundle "Page sections" kind can carry an anchor;
once one block on a page has one, `MenuLinkType`'s target select lists it right under that page's own
entry (`Home → Services`) — the whole page tree is walked by UiBundle's `BlockAnchorCollector`, nested
sections included. The target is stored as `page:<id>#<fragment>` and resolved into `/home#services-42`.

A band rendered by the layout rather than by a page's own blocks is **not** in that list. To make one
linkable, drop its block kind into the page and give it an anchor there.

### In another language

A menu's items are read on every page of the site, so a site translated everywhere but in its navbar is
still half in the language it was written in. The **Translate** action of the Menus index
(`management_menu_translate`, `TranslationController::menu()`) writes a menu's own labels — the
`menu_link` `label` field, declared `translatable` in `config/services.yaml` — once for the whole site,
not per page. An item **deriving** its label from the page it points at carries no text of its own: it
is translated with that page, and `menu_link_label()` reads the page's translated title. `menu_link_url()`
likewise generates the localised routes while a page is read in another language, so the first click
does not send the visitor back into the writing language. Both actions show only where the site
declares more than one language.

### Exposing another bundle's route

Implement ConfigBundle's `LinkableRouteProviderInterface` — **never add a dependency on SiteBundle to
do it**, which is precisely why that interface lives in ConfigBundle. A provider can offer one entry
per row of its own data, each naming the route and its parameters; the url is generated at each render,
so renaming a row leaves no menu item behind. An entry can carry a `picker_label` so it says what it is
in the list while the rendered item keeps the bare title.

## menu_group

A chromeless wrapper taking a line of its own inside the footer's flex row — social links on one line,
legal links on the next. It reuses UiBundle's `block_group` form, template and markup; its own kind
exists only so a menu's grouping stays out of a page's containers.

**`menu_group` is the only group a menu offers**, and UiBundle's own `block_group` is not: a group
taken from anywhere else would be picked happily, then refuse every link dropped into it. A group
cannot hold another group, so the tree is always one level deep.

## Footer display style

The footer's edit screen carries a **Display style** select (`Menu::$style`): the site theme's own
choice (default), *Inline* or *Block*. The two classes **retune `--footer-items-direction` /
`--footer-items-justify` on the `.menu-items` element** rather than writing `flex-direction`, so one
class covers the wrapper and its `.blocks` child and beats what the theme left on `:root`. Left on its
placeholder it stores `null` and adds no class: **a site that already picked its layout in
`themes/site.css` keeps rendering exactly as it did.** Anything but the two known values is stored as
`null` — the value ends up in a class name.

No other location offers it. The choice is cached with the menu's blocks (`menu_style()`, same
`menus_all` tag).

## Navbar

`Navbar` reads `site_media('logo')`, `config('site-name')` and `config('site-tagline')` — nothing to
pass in. `site-navbar-show-name` (bool, default true) decides whether the name shows there;
`site-navbar-position` (`relative`, `sticky`, `fixed`, `static`, `absolute`) is carried by a
`.menu.menu-position-*` class setting `--navbar-position`, **not by a `style=""` attribute a nonced
`style-src` would drop**.

`sticky` and `absolute` also raise `--navbar-z-index` to 1000, a bar overlapping the page having to be
painted above it. `sticky` sticks through the `<header>` `layout.html.twig` wraps the bar in — the bar
alone is its own containing block and has nowhere to travel — via
`header:has(> .menu.menu-position-sticky:only-child)`. **An app overriding that `header` block with
more than the navigation inside opts out of it** and has to arrange its own sticky header.

Logo and name are wrapped in **one single link** to the home page, not two adjacent ones — a screen
reader announced the same destination twice. The logo's `alt` is emptied when the name is printed
beside it.

With no navbar menu at all, the component falls back to the logo centered with the name under it, on a
`<nav class="nav-simple">` — that class, not the bare element, is what the stylesheet targets, so an
app rendering its own `<nav>` is untouched. `site-tagline` is rich text, rendered with `|raw`, and is
hidden on a fixed navbar.

## Copyright

The "© firstYear - currentYear[ : siteName]" line is ConfigBundle's `site_copyright()` Twig function.
A `menu_link` targeting the site's own Copyright page shows that live-computed text as its label
instead of the page title, gated by `site-menu-link-copyright-auto` (bool, default true).

## Twig functions

`menu_blocks(location)`, `menu_link_url()`, `menu_link_label()`, `menu_link_is_copyright()`,
`menu_style()`.

Hovering a **footer** item as an editor raises UiBundle's Edit button, opening the footer's edit screen
with that row unfolded (`MenuBlockEditUrlProvider`). The navbar is deliberately left out: it is hovered
on every visit just to navigate.

## Do not

- **Do not add a dependency on SiteBundle to expose a route in a menu.** Implement
  `LinkableRouteProviderInterface`, which lives in ConfigBundle for exactly that reason.
- **Do not store a page's slug in a menu link.** The id is what survives a rename.
- **Do not offer `block_group` in a menu.** Use `menu_group`.
- **Do not put anything but links in a navbar** — the context forbids it, and the picker will not
  offer it.
- **Do not use a `menu_link` in an email menu while `site-url` is empty.** Nothing makes it absolute then.
- **Do not write `flex-direction` for the footer layout.** Retune the two `--footer-items-*` tokens.
- **Do not set the navbar position with a `style` attribute.** The nonce drops it.
- **Do not build a second navigation table.** A menu is a block collection like a page's.
- **Do not create a menu per language.** One menu, translated once through its Translate screen.
- **Do not read `page.title` for a derived menu label.** It is the writing language's, whatever
  language the page is being read in.
