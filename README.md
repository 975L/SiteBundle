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
- **Sitemap generation** from both filesystem templates and database pages
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
- **CSS animations** stylesheet
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

### 1. Download

```bash
composer require c975l/site-bundle
```

### 2. Load configuration values

This bundle uses [c975L/ConfigBundle](https://github.com/975L/ConfigBundle) to manage its settings. Load the default configuration keys into the database:

```bash
php bin/console c975l:config:load 'vendor/c975l/site-bundle/config/configs.json'
```

Then open the ConfigBundle dashboard to set values for the following keys:

| Key | Description |
| --- | --- |
| `site-name` | Website name |
| `site-tagline` | Website tagline |
| `site-logo` | Logo path (used in emails) |
| `site-favicon` | Favicon path (default: `/favicon.ico`) |
| `site-apple-touch-icon` | Apple touch icon path (default: `/apple-touch-icon.png`) |
| `site-author` | `<meta name="author">` value |
| `site-first-online-date` | Date the site went online |
| `site-url` | Base URL, e.g. `https://example.com` |
| `url-terms-of-use` | URL of the terms of use page |
| `url-cookies-policy` | URL of the cookies policy page (used by CookieConsent) |
| `site-matomo-url` | Matomo instance URL, e.g. `https://matomo.example.com` |
| `site-matomo-id` | Matomo site ID (integer) |
| `site-hosted-by-url` | URL of the hosting company |
| `site-hosted-by-logo` | Path or URL of the hosting company logo |
| `site-made-by-url` | URL of the developer / agency |
| `site-made-by-logo` | Path or URL of the developer / agency logo |
| `site-backup-database` | MySQL database name to back up |
| `site-backup-mailto` | Email for backup reports and error notifications |

### 3. Enable routes

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

### 4. Install assets

```bash
php bin/console assets:install --symlink
```

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
{% set description = 'A short description of this page.' %}
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

- Title, slug (unique), description
- Published status and display position
- Sitemap fields: change frequency and priority (0–10)
- Blocks (content blocks from [c975L/UiBundle](https://github.com/975L/UiBundle))
- Creation / modification timestamps and author reference

Database pages are rendered with the bundle's `@c975LSite/pages/page.html.twig` template, which displays the page title, description, and its associated blocks.

### Admin management

Pages are managed in the EasyAdmin dashboard via `PageCrudController`. The menu entry is registered automatically through `MenuProvider`. Access is controlled by the `site-role-needed` key in ConfigBundle.

---

## SEO

### Sitemap generation

Run the following command to generate `public/sitemap-pages.xml`:

```bash
php bin/console site:sitemaps:create
```

The command aggregates URLs from:
1. Twig files in `templates/pages/` (reads `changeFrequency` and `priority` from comments)
2. Published database pages (uses their `changeFrequency` and `priority` fields)

A sitemap index template is also available at `@c975LSite/sitemap-index.xml.twig`.

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

Set a per-page OG image:

```twig
{% set ogImage = absolute_url(asset('images/my-og-image.jpg')) %}
```

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

---

## Email templates

Pre-built email templates are available at `@c975LSite/emails/`:

| Template | Description |
| --- | --- |
| `layout.html.twig` | Base email layout |
| `fullLayout.html.twig` | Full email layout |
| `footer.html.twig` | Email footer |

CSS is inlined automatically via `twig/cssinliner-extra`. Minified stylesheets (`emails.min.css`, `styles.min.css`, `animations.min.css`) are embedded.

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
| `php bin/console site:sitemaps:create` | Generates `public/sitemap-pages.xml` from filesystem and database pages |
| `php bin/console site:backup` | Backs up the database and `public/` files (replaces `BackupServer.sh`) |
| `php bin/console models:twig2md` | Converts Twig model templates to their Markdown equivalent |

---

## Scheduler

The bundle provides `site:sitemaps:create` and `site:backup` as schedulable commands. The schedule itself is defined in your app so each project controls its own timing.

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
            ->add(RecurringMessage::cron('7 3 * * 1', new RunCommandMessage('site:backup --full --report')));
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
