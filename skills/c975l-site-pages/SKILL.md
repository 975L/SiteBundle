---
name: c975l-site-pages
description: "Use this skill when working with pages or collections in a Symfony application built on the c975L ecosystem with c975l/site-bundle — the Page entity, file-based pages, the trash and the redirects a deletion leaves behind, the block kinds this bundle adds, publish-as-replacement, and CollectionGroup/CollectionItem with their per-item detail pages. Triggers on: Page entity, page_display, page_home, page_preview, PageCrudController, twig_content, articles_slider, CollectionGroup, CollectionItem, collection block, detailPage, collectionItem, reorder, ea-index-sort, publish as replacement, duplicate page, trash, restore, site-role-admin, SiteBlockEditUrlProvider, FormEditUrl, max_input_vars, site_page."
---

# c975L SiteBundle — pages and collections

> Database-driven pages composed of UiBundle blocks, the file-based pages beside them, and the generic collections a `collection` block draws from.

**Package:** `c975l/site-bundle` · **Namespace:** `c975L\SiteBundle\` · **Twig namespace:** `@c975LSite` · **Translation domain:** `site`

**Key source paths** (relative to the package root):
`src/Entity/Page.php`, `src/Entity/CollectionGroup.php`, `src/Entity/CollectionItem.php`, `src/Controller/PageController.php`, `src/Controller/Management/`, `src/Service/CollectionItemSourceProvider.php`, `src/Service/PagePublicUrlResolver.php`, `src/Twig/PageExtension.php`, `src/Twig/CollectionItemContext.php`, `src/Form/Block/`, `templates/blocks/`, `templates/pages/`, `config/services.yaml`

**Related skills:** `c975l-site-layout`, `c975l-site-menus`, `c975l-site-seo` in this same package. The block system itself, the media library and the legal models are in `c975l/core-bundle`.

## Two kinds of pages

| Kind | Where | Served at |
| --- | --- | --- |
| File-based | `templates/pages/*.html.twig` in the app | `/pages/{slug}` (`page_display`) |
| Database | the `Page` entity, composed in EasyAdmin | `/pages/{slug}`, or `/` for the `home` slug (`page_home`) |

**File-based pages are not in the sitemap** — a template carries no `lastmod`, no priority and no
publication state. Create the page in the database if it has to be crawled.

Two conventions sit beside them: `templates/pages/redirected/{slug}.html.twig` redirects to the slug
written inside the file, and `templates/pages/deleted/{slug}.html.twig` throws a 410.
**Database-managed redirects are ConfigBundle's** (`Redirect` entity, its own CRUD and health check) —
they answer before the router and serve any bundle's urls, not only pages.

## The Page entity

Title, unique slug, `summarySocialNetwork`, published state, position, blocks, timestamps and author,
plus the sitemap fields (indexable, change frequency, priority).

- **Unpublishing unreferences.** `isIndexable` follows `isPublished` down, whatever unpublished the
  page — form, trash, replacement, duplication or import (`Page::unreferenceWhenUnpublished()`, a
  `PreFlush` callback). Publishing again does **not** put it back: referencing a page is a deliberate
  call. The page answers 404 the moment it is unpublished.
- **Display the page title** is stored in `Page::$options`, one JSON column for the page's benign
  display options — the same reasoning as `Block::$data`, so adding an option is a code change with no
  migration for every app running this bundle. Read and write through named accessors
  (`isTitleDisplayed()` / `setIsTitleDisplayed()`), never as raw string keys. Anything the database has
  to filter, sort or join on (`slug`, `isPublished`, `isIndexable`) stays a real column.
- Deleting permanently leaves gone rows behind on its own: one for the page's url, one per redirect
  that pointed at it.
- **The trash's two row actions are GETs**, so each carries a CSRF token in its url
  (`PageCrudController::RESTORE_CSRF_TOKEN`, `PageCrudController::DELETE_PERMANENTLY_CSRF_TOKEN`). An
  override re-declaring either one builds that url too — `linkToCrudAction()` on its own gets the
  action refused and sent back to the trash.
- **`restore` and `deletePermanently` are `site-role-admin`**, where every other page action is
  `site-role-editor`: deleting only moves a page to the trash, which an editor may do, but pulling one
  back out or removing it for good is the bar those two methods state themselves — a button leading to
  its own 403 is a button not to draw.

`PageController::preview()` opts out of the block render cache entirely — an editor's preview must show
what was just saved, and its render is not the public one.

**A whole page is one form**, every block and every media in a single POST, so it is what reaches PHP's
`max_input_vars` (1000 by default) first. Past that limit PHP drops the rest of the body silently and
the missing blocks read as "the editor removed them", so such a submission is refused whole
(`SubmissionIntegrity`, UiBundle). A site stacking many blocks raises `max_input_vars` in `php.ini`.

## Block kinds this bundle adds

| Kind | Category | What it does |
| --- | --- | --- |
| `twig_content` | twig | includes an existing Twig template by its path (`templatePath`) — a general-purpose "drop this template in here" |
| `articles_slider` | navigation | picks another page and renders its `article` blocks that carry a media as a slider |
| `menu_link` | navigation | a link to a page or a contributed route; restricted to the menu contexts |
| `menu_group` | navigation | a chromeless wrapper grouping several items on a line of a menu |

The last two belong to menus — see the `c975l-site-menus` skill. `articles_slider` uses the
`site_page(id)` Twig function to eager-load the target page with its blocks and medias.

Hovering a block as an editor raises UiBundle's Edit button, and `Management\SiteBlockEditUrlProvider`
answers where it goes. Two kinds are edited somewhere other than the Page form carrying them: a
`legal_model` on its wording screen (`LegalModelEditUrl`) and a `form` on the Form's own, where its
fields are (`FormEditUrl`). Anything else — and either of those pointing at something that no longer
exists, which would 404 — opens the Page form with that row unfolded (`BlockFocusUrl`).

## Publish as replacement

Any non-deleted page's edit screen lists every other page as a replacement target: picking one archives
the target's slug, moves it to the trash and swaps the current page in. The usual way to prepare one is
the **Duplicate** action, which builds an unpublished copy of the whole block tree, medias included.

**There is deliberately no page-template mechanism.** A page's arrangement is composed in the admin,
block by block, never derived from a stored arrangement — a "template" could only be a snapshot of
example content with no relation kept afterwards. Use Duplicate.

## Collections

A `CollectionGroup` ("Projects") is a named, slugified container of `CollectionItem` rows — one table
backs every collection of a site, what separates two collections being only which group an item belongs
to. Two CRUDs on purpose: **Collections** creates and renames the groups, and its index's *Items*
action opens `CollectionItemCrudController` filtered to that one group (`?collectionGroup=<id>`), so a
typo can never spawn an unrelated collection. An item's slug is unique **within its collection only**,
unlike `Page::$slug`.

`CollectionItemSourceProvider` exposes every group to UiBundle's `collection` block, keyed
`site.collection.{slug}` — **creating a collection is enough to make it pickable, no code change**.
Each source declares its own cache tag, invalidated when an item or the group is saved.

The items index reorders by drag-and-drop through **UiBundle's `ea-index-sort.js`**, which SiteBundle no
longer duplicates: `collection_item_crud_index.html.twig` opts in by declaring `data-reorder-url`,
`data-reorder-group` and `data-reorder-token` on each row, and `CollectionItemCrudController::reorder()`
answers `{positions: {id: position}}` — what it actually persisted, the submitted ids being re-checked
against the submitted group rather than trusted.

The image an item names is health-checked on the server by `CollectionFilesHealthCheckProvider` (kind
`files-site`, see `c975l-site-seo`) — a file gone from `public/` is an error row, not a silent hole.

### Item detail pages

Each item gets a per-item url without a row of its own. Two editorial steps, once per collection:

1. create a `Page` for the detail view (any slug) and give it the blocks it needs — a `twig_content`
   block for a custom template, native kinds for a simpler layout;
2. on the **other** page, the one carrying the `collection` block, fill that block's `detailPage` field
   with the detail page's slug.

From then on `/pages/{page}/{itemSlug}` calls the source's `detail($itemSlug)`; a non-null result
renders the detail page's blocks with a `collectionItem` Twig global exposing it for that one render
(`CollectionItemContext`). A `twig_content` block reads it as
`{% include templatePath with collectionItem.get() %}`. A null result falls through to a 404. **Nothing
is persisted per item.**

## Commands

```bash
php bin/console c975l:site:pages:import-defaults        # home + the legal pages, if absent
php bin/console c975l:site:collection-item:import --group=<group> --json-file=<path>
php bin/console c975l:site:create                       # one-shot wizard bootstrapping a new site
```

## Twig functions

`site_page(id)`, `site_legal_pages(models)`, `site_page_for_form_block(formName)`,
`page_health_check(page)`. All declared with `#[AsTwigFunction]` on the method backing them — a site
overriding one decorates the service and carries the attribute over.

## Do not

- **Do not create a file-based page for content that must be crawled** — it is absent from the sitemap
  by construction.
- **Do not write a redirect mechanism.** ConfigBundle's `Redirect` rows answer before the router, for
  every bundle's urls.
- **Do not add a column to `Page` for a display option.** It goes in `Page::$options`, behind a named
  accessor.
- **Do not re-publish a page expecting it to be indexed again** — `isIndexable` has to be checked back
  deliberately.
- **Do not build a page-template feature.** Duplicate is the answer.
- **Do not add a free-text collection field on an item.** The group is picked by the screen it is
  opened from.
- **Do not create a `Page` per collection item.** One detail page serves the whole collection.
- **Do not cache a preview render.**
