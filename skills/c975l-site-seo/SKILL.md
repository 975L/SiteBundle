---
name: c975l-site-seo
description: "Use this skill when working on the searchability or the monitoring of a Symfony application built on the c975L ecosystem with c975l/site-bundle — sitemaps, canonical urls, the Open Graph image, the content-quality and W3C health checks, the deployment smoke test or the dev profile. Covers what each command reads, which database it must run against, and what the checks deliberately do not flag. Triggers on: sitemap, c975l:sitemaps:create, c975l:site:smoke-test, c975l:health-check:run, c975l:dev-profile:run, canonical_url, ogImage, og:image, content-quality, pagespeed, w3c-html, w3c-css, mixed-content, deployment, noindex, PagePublicUrlResolver, llms.txt."
---

# c975L SiteBundle — SEO, health checks and deployment

> What makes a c975L site findable and what tells you it broke: sitemaps, canonical urls, share images, six page-level health checks, a deployment smoke test and a dev profile.

**Package:** `c975l/site-bundle` · **Namespace:** `c975L\SiteBundle\` · **Translation domain:** `site`

**Key source paths** (relative to the package root):
`src/Management/SitePageSitemapProvider.php`, `src/Management/ContentQualityHealthCheckProvider.php`, `src/Management/SitePageHealthCheckProvider.php`, `src/Management/W3cHtmlHealthCheckProvider.php`, `src/Management/W3cCssHealthCheckProvider.php`, `src/Management/MixedContentHealthCheckProvider.php`, `src/Management/PageDevProfilePathProvider.php`, `src/Service/PagePublicUrlResolver.php`, `src/Service/SmokeTestClient.php`, `src/Command/SmokeTestCommand.php`

**Related skills:** `c975l-site-pages`, `c975l-site-layout` in this same package. The sitemap writer, the health-check runner, the dashboard and the site-wide checks live in `c975l/core-bundle`.

## Sitemaps

```bash
php bin/console c975l:sitemaps:create
```

**The command belongs to ConfigBundle, not here.** It writes one `public/sitemap-<name>.xml` per bundle
implementing `SitemapProviderInterface`, plus the `sitemap-index.xml` declaring them all. This bundle
contributes `SitePageSitemapProvider` for the database pages — non-indexable ones excluded, urls built
by `PagePublicUrlResolver` so they are exactly the ones the health checks test.

**Point Search Console at `sitemap-index.xml` only**, never at a sub-sitemap: installing or removing a
bundle then changes what is crawled with nothing to update on Google's side.

Each url also carries the page's title and summary as optional `title` / `description` keys, which the
sitemap ignores — ConfigBundle's `SeoFilesWriter` reads them to build `public/llms.txt`.

To contribute a sitemap from another bundle, implement `SitemapProviderInterface`; the file and index
writing is none of the contributing bundle's business.

## Canonical url

`<link rel="canonical">` and `og:url` are built by ConfigBundle's `canonical_url()`, out of the
`site-url` config value and the current path — **not** out of `app.request.uri`, which made every
variant of a url declare itself canonical: query string, trailing slash, `www` vs apex, scheme. The
query string is dropped, and the path is the slashless form the sitemap declares. Nothing is emitted at
all outside an http request or before `site-url` is set, rather than a tag pointing at the wrong host.

`/pages/{slug}/` answers `301` to `/pages/{slug}` on top of that — a canonical link alone is only a
hint. `hreflang` is not emitted; a multilingual site uses locale-prefixed routes.

## Open Graph image

Resolved in this order: an `ogImage` Twig variable set by the template, then a database page's own
`ogImage`, then the url's `UrlMetadata` row, then the site-wide default og-image, then the logo.

Whichever answers, the layout keeps the `Media` row alongside the url and states
`og:image:width` / `-height` from its intrinsic dimensions — a media whose dimensions were typed as a
css length says nothing rather than something Open Graph cannot read — and `og:image:alt` from the
media's own alternative text.

**A bundle serving pages of its own sets `ogImage` itself**, so a share shows what the page holds
rather than the site logo.

## Health checks

Six providers, all about a **published page**, all plain HttpClient calls with no Node or headless
browser involved. The site-wide checks (TLS, security headers, robots.txt, redirect chains, declared
urls) are ConfigBundle's.

| `getKind()` | Checks | API key |
| --- | --- | --- |
| `pagespeed` | Lighthouse performance / accessibility / best-practices / SEO + console errors | optional `healthcheck-pagespeed-api-key`, the anonymous quota being shared worldwide |
| `w3c-html` | markup, via the W3C Nu checker | none |
| `w3c-css` | stylesheets; warnings the validator's CSS3 profile predates are counted apart as *benign* | none |
| `content-quality` | noindex contradictions, title and description length, `<h1>`, share tags, image `alt`, broken links | none |
| `mixed-content` | `http://` assets on an `https://` page | none |
| `deployment` | that `http://` really redirects, and that an unknown url answers a real 404 | none |

What `content-quality` deliberately does **not** flag, and why it matters when reading a report:

- an explicitly empty `alt=""` on an image marked decorative (`aria-hidden`, `role="presentation"`, or
  inside an already-labelled link) — flagging it would leave the page orange forever with nothing to fix;
- an inconclusive link: a transport failure, `405`/`501`, `403`, LinkedIn's `999`, `429` — retried once
  with `GET`, then left out rather than reported;
- an external broken link is only ever a **warning**; one of your own is an error;
- `og:title` / `og:description` when the title or description they mirror is itself already reported;
- Schema.org / JSON-LD, whose presence depends on what a page is.

`content-quality` only knows this bundle's own pages. **Everything else a site publishes — a book, a
product, a photo — is checked by ConfigBundle's `DeclaredUrlsHealthCheckProvider`**, which runs the very
same analyzer over the urls a bundle already declares for its sitemap. Nothing to implement bundle-side.

**Run the checks against production's own database.** The page list comes from whichever database the
command is connected to, while the urls always point at `site-url`. Running from a staging database
whose pages are not live yet reports failures that are not bugs, just the wrong database for the
question. None of the providers run from a controller — only `c975l:health-check:run` does, so a slow
or paid API call never blocks a request.

On accessibility: there is no free pure-PHP WCAG scanner. The `pagespeed` accessibility score is the
only automated signal here, and it reports one score rather than per-criterion detail.

## Smoke test

```bash
php bin/console c975l:site:smoke-test [-v] [--pages-only]
```

Meant for the end of a deployment: every published page plus every css/js asset the home page
references must answer 200, and it **exits non-zero on the first failure** so a CI job fails instead of
leaving a broken site online. Assets are read out of the home page's rendered HTML rather than declared
anywhere — AssetMapper's filenames are hashed, so this is what proves `asset-map:compile` and the
stylesheet cache warmer both ran, and in the right order.

A site in maintenance answers 503 on every url by construction, so the command checks nothing and exits
0. It is deliberately **not** a health-check provider: that one judges quality weekly and persists rows,
this one answers "is it broken right now" and has to be able to fail a pipeline.

## Dev profile

```bash
php bin/console c975l:dev-profile:run
```

ConfigBundle's dev-only command, listing what the Symfony toolbar would flag on every page — n+1
queries, deprecations, missing translations, external HTTP calls during rendering. This bundle
contributes `PageDevProfilePathProvider`, so an app installing it has nothing to write.

The difference with the two above: the health check and the smoke test fetch the **live** site at
`site-url`, which points at production even from a dev machine. The dev profile hands each page's local
path straight to the local kernel — no HTTP, no host — so it profiles the code and database being
worked on.

## Do not

- **Do not write a sitemap command in a bundle.** Implement `SitemapProviderInterface` and let
  `c975l:sitemaps:create` write the file and the index.
- **Do not build a canonical url from `app.request.uri`.** Use `canonical_url()`.
- **Do not declare a file-based page in a sitemap** — it carries no lastmod, priority or publication
  state.
- **Do not point Search Console at a sub-sitemap.**
- **Do not run the health check or the sitemap command against a staging database** while `site-url`
  points at production.
- **Do not implement a health check that runs from a controller.**
- **Do not write a per-bundle content-quality check.** Declare the urls for the sitemap and
  ConfigBundle checks them.
- **Do not add a page-level check to the smoke test** — it must stay fast enough to run on every
  deployment.
