# SiteBundle

**-- README IS NOT REALLY UP TO DATE ;( --**

SiteBundle does the following:

- Defines a layout used to display the web pages,
- Variables are used to display data linked to website, name, etc.,
- Allows to add Matomo javascript by just set url and id,
- Allows to add CookieConsent by just adding its data,
- Allows to have templates to override TwigBundle/Exception templates,
- Allows to use pre-defined Terms of use, Terms of sales, etc.

## Bundle installation

### Step 1: Download the Bundle

Use [Composer](https://getcomposer.org) to install the library

```bash
    composer require c975L/site-bundle
```

### Step 2: Configure the Bundle

v2.0+ of c975LSiteBundle uses [c975L/ConfigBundle](https://github.com/975L/ConfigBundle) to manage configuration parameters. Use the Route "/site/config" with the proper user role to modify them.

Upgrading from v1.x? **Check UPGRADE.md**

### Step 3: Enable the Routes

Then, enable the routes by adding them to the `config/routes.yaml` file of your project:

```yml
c975_l_site:
    resource: "@c975LSiteBundle/Controller/"
    type: annotation
    prefix: /
    #Multilingual website use the following
    #prefix: /{_locale}
    #defaults:   { _locale: '%locale%' }
    #requirements:
    #    _locale: en|fr|es
```

### Step 4: install assets to web folder

Install assets by running

```bash
php bin/console assets:install --symlink
```

It will create a link from folder `Resources/public/` in your web folder. These files are used in the `layout.html.twig`.

### How to use

You **must** create a file named `layout.html.twig` in your `app/Resources/views/` that extends `@c975LSite/layout.html.twig`, so simply add this `{% extends '@c975LSite/layout.html.twig' %}` at its top.

SiteBundle use the following variables which are page-based, meaning that they change for each page. If you want to use them, simply declare them on each page that extend your `app/Resources/views/layout.html.twig`.

```twig
{% set title = 'YOUR_PAGE_TITLE' %}
{% set description = 'YOUR_PAGE_DESCRIPTION' %}
```

Note: If you use [c975L/PageEdit](https://github.com/975L/PageEditBundle) the variables are already passed to `layout.html.twig`.

### Override a block

You can override any block in the template, to do so, simply add the following in your `app/Resources/views/layout.html.twig`:

```twig
{% block share %}
    {# You can also use {{ parent() }} #}
    {# YOUR_OWN_TEXT #}
{% endblock %}
```

Have a look at `Resources/views/layout.html.twig`, to see all available blocks.

### Disable a block

To disable a block, simply add the following in your `app/Resources/views/layout.html.twig`:

```twig
{% block share %}
{% endblock %}
```

Have a look at `Resources/views/layout.html.twig`, to see all available blocks.

### Use the display variable

In your `app/Resources/views/layout.html.twig` you can use the following to include (or not) templates:

```twig
{% if display == 'pdf' %}
    {% include 'header-pdf.html.twig' %}
{% else %}
    {% include 'header.html.twig' %}
{% endif %}
```

if `display` is not defined, hten it's define to `html`.

### Matomo javascript

You can easily add a call to matomo by adding the following in your `app/Resources/views/layout.html.twig`:

```twig
{%
    set matomo = {
        'id': YOUR_MATOMO_ID,
        'url': 'YOUR_MATOMO_URL'
    }
%}
```

### CookieConsent

You can easily add a call to CookieConsent by adding the following in your `app/Resources/views/layout.html.twig`

```twig
{%
    set cookieConsent = {
        'message': 'YOUR TEXT',
        'dismiss': 'YOUR_DISMISS_TEXT',
        'link': 'YOUR_COOKIES_POLICY_LINK_TEXT',
        'href': 'YOUR_COOKIES_POLICY_LINK'
    }
%}
{# or use the texts defined in SiteBundle #}
{%
    set cookieConsent = {
        'message': 'text.cookies_banner'|trans,
        'dismiss': 'text.cookies_dismiss'|trans,
        'link': 'label.cookies_policy'|trans,
        'href': 'YOUR_COOKIES_POLICY_LINK'
    }
%}
```

### Alternate languages

You can define the meta `<link rel="alternate" hreflang="YOUR_LANGUAGE" href="URL_WITH_ALTERNATE_LANGUAGE">` by setting a `languagesAlt` array in your `app/Resources/views/layout.html.twig`

```twig
{%
set languagesAlt = {
    en: { title: 'English' },
    fr: { title: 'Français' },
    es: { title: 'Español' }
    }
%}
```

It will replace the current language by the ones set in `languagesAlt` using the following scheme `https://example.com/LANGUAGE/pages/XXX`.

Having this array set, you can also use `navbarLanguagesDropdownMenu.html.twig` in your navbar to display a dropdown menu to select available languages.

### ogImage

You can define an ogImage to use on page basis, with the following code:

```twig
{% set ogImage = absolute_url(asset('PATH_TO_YOUR_IMAGE')) %}
```

### Animations

There's a css file in `public/css/` that you can link to to use some animations

```twig
<link rel="stylesheet" href="bundles/c975lsite/css/animations.min.css">
```

### Error pages

You can also use the templates for common error pages. For this, you need to follow [How to Customize Error Pages](http://symfony.com/doc/current/controller/error_pages.html) to create the structure `app/Resources/TwigBundle/views/Exception` and files for each type of error. Of course you can still stop at the level of overidding `TwigBundle/Exception`, but if you want to use the pre-defined error templates, do the following:

The types of error covered by SiteBundle are:

- error
- error401
- error403
- error404
- error410
- error500

In each file copy/paste the following code:

```twig
{% extends 'layout.html.twig' %}

{% block content %}
    {# Take care to modify the error code in the included template name, i.e. "404" given here #}
    {% include('@c975LSite/Exception/error404.html.twig') %}
{% endblock %}

{% block share %}
{% endblock %}
```

### Add stylesheets

To add stylesheets, simply add the following  in your `app/Resources/views/layout.html.twig`:

```twig
{% block stylesheets %}
    {{ parent() }}
    {# Of course you can provide the full "link" html data #}
{% endblock %}
```

### Add javascripts

To add javascripts, simply add the following  in your `app/Resources/views/layout.html.twig`:

```twig
{% block javascripts %}
    {{ parent() }}
   {# Of course you can provide the full "script" html data #}
{% endblock %}
```

## Full layout example

You can use this full layout example as a basis for your project:

```twig
{% extends '@c975LSite/layout.html.twig' %}

{%
set languagesAlt = {
    en: { title: 'English' },
    fr: { title: 'Français' },
    es: { title: 'Español' }
    }
%}
{%
    set matomo = {
        'id': YOUR_MATOMO_ID,
        'url': 'YOUR_MATOMO_URL'
    }
%}
{%
    set cookieConsent = {
        'message': 'text.cookies_banner'|trans,
        'dismiss': 'text.cookies_dismiss'|trans,
        'link': 'label.cookies_policy'|trans,
        'href': 'YOUR_COOKIES_POLICY_LINK'
    }
%}

{# Meta #}
{% block meta %}
    {{ parent() }}
{# Facebook app_id #}
    <meta property="fb:app_id" content="YOUR_FACEBOOK_APP_ID">
{% endblock %}

{# Css #}
{% block stylesheets %}
    {{ parent() }}
{% endblock %}

{# Navigation #}
{% block navigation %}
    {{ include('navbar.html.twig') }}
{% endblock %}

{# Title #}
{% block title %}
    {% if app.request.get('_route') != null %}
        <h1>
            {{ title }}
        </h1>
    {% endif %}
{% endblock %}

{# Container #}
{% block container %}
    <div class="container">
        {% block content %}
        {% endblock %}
    </div>
{% endblock %}

{# Share #}
{% block share %}
    {# YOUR SHARING TOOL  #}
{% endblock %}

{# Footer #}
{% block footer %}
    {{ include('footer.html.twig') }}
{% endblock %}

{# Javascript #}
{% block javascripts %}
    {{ parent() }}
{% endblock %}
```

## Use pre-defined models

There are two ways to use the pre-defined models, `include` or `embed`, both are based on country an language: `{% include '@c975LSite/models/COUNTRY/LANGUAGE/terms-of-sales.html.twig' %}`. You can see an example below for `Terms of sale` for `France` in `fr` (french).

If you have a **multlingual website** you can call by ommitting the language `{% include '@c975LSite/models/COUNTRY/terms-of-sales.html.twig' %}`, SiteBundle will check if your current language is available and will display it, or will display the default language if not.

## Use whole file (include)

You want to use the whole file, place this code in your template:

```twig
{% extends 'YOUR_LAYOUT.html.twig' %}

{% trans_default_domain 'site' %}
{# Title value is made of 'label.' + name of page, replacing "-" by "_" #}
{# i.e. page 'terms-of-sales' gives title = 'label.terms_of_sales' #}
{% set title = 'label.terms_of_sales'|trans %}

{% block content %}
    {# set the defined data (indicated at the top of the template file) before including #}
    {% set latestUpdate = '2018-03-08' %}

    {% include '@c975LSite/models/france/fr/terms-of-sales.html.twig' %}

    {# You can your own data at the end #}
    <h2>Achat de crédits</h2>
    <p class="text-justify">
        L’achat de crédits ...
    </p>
{% endblock %}
```

### Select blocks (embed)

You want to select the displayed blocks, place this code in your template. **Note** that you have to specify the language in the `embed` function:

```twig
{% extends 'YOUR_LAYOUT.html.twig' %}

{% trans_default_domain 'site' %}
{% set title = 'label.terms_of_sales'|trans %}

{% block content %}
    {# set the defined data (indicated at the top of the template file) before including #}
    {% set latestUpdate = '2018-03-08' %}

    {% embed '@c975LSite/models/france/fr/terms-of-sales.html.twig' %}
        {# Then you can disable block #}
        {% block acceptation %}
        {% endblock %}

        {# Or append information to it #}
        {% block acceptation %}
            {{ parent() }}
            Your added content
        {% endblock %}

        {# Or replace content #}
        {% block acceptation %}
            Your replacing content
        {% endblock %}
    {% endembed %}
{% endblock %}

```

### Available models

You can find below a table containing all the models available per country and language. **Feel free to update them, add translations or countries.** By convention files are named using "-" with the english name.

| Model          | France |
|---             |---     |
| Cookies        | fr     |
| Copyright      | fr     |
| Legal notice   | fr     |
| Privacy policy | fr     |
| Tems of sales  | fr     |
| Tems of use    | fr     |

To facilitate reading, models are also available in Markdown format. If you do a modification, you can use Command `php bin/console models:twig2md` to convert Twig models templates to their Markdown equivalent.

If this project **help you to reduce time to develop**, you can sponsor me via the "Sponsor" button at the top :)

### AssetController

You can use this route to serve an asset file, by using the following code in your Twig template: `{{ path('asset_file', {'file': 'your/path/your_file.ext[.ext2]'}) }}`.

file name can contain uppercase, lowercase, accented letters, "-", "_", "/", "\", only spaces are not allowed. You can also use 2 file extensions.

This will be helpful if you want to give access to your assets to registered users. You simply need to add `- { path: ^/your/path, roles: ROLE_USER }` to `config/packages/security.yaml` > `access_control`part. And you can add an http basic authentication on the asset folder itself.

### DownloadController

You can use this route to force the download of an asset file, by using the following code in your Twig template: `{{ path('download_file', {'file': 'your/path/your_file.ext[.ext2]'}) }}`.

file name can contain uppercase, lowercase, accented letters, "-", "_", "/", "\", only spaces are not allowed. You can also use 2 file extensions.

This will be helpful in case of text files like json or whatever.nt to give access to your assets to registered users. You can also protect your route by adding `- { path: ^/your/path, roles: ROLE_USER }` to `config/packages/security.yaml` > `access_control`part. And you can add an http basic authentication on the download folder itself.

### Twig Components

Some Twig components are available, check `templates/components` to see them. An example of use is in each component file.


### Resize image

If you want to resize an image, you can do the following:

```php
use c975L\SiteBundle\Service\ServiceImageInterface;

class YourClass
{
    private $imageService;

    public yourMethod(ServiceImageInterface $imageService)
    {
        //Do your stuff...

        //Resizes image
        $imageService->resize($file, string $folder, string $filename, string $format = 'jpg', int $finalHeight = 400, int $compression = 75, bool $square = false, $stamp = null);
    }
}
```

### Create Flash message

If you want to create a flash message, you can do the following:

```php
use c975L\SiteBundle\Service\ServiceToolsInterface;

class YourClass
{
    private $toolsService;

    public yourMethod(ServiceToolsInterface $toolsService)
    {
        //Do your stuff...

        //Create flash
        $toolsService->createFlash(string $translationDomain = null, string $text, string $style = 'success', array $options = array());
    }
}
```

## `.sh` scripts

These scripts are not directly related to Symfony but to its production steps for `GitHookPostUpdate.sh` and its backup `BackupXXX.sh`. **They are programmed to work on the Synfony 4(flex) structure AND on a GNU/Linux server. You can find more information on them below.

### GitHookPostUpdate.sh

This script is to be run after the Git repository has been updated (via `git pull`), for this, it's call should be placed in the `.git/hooks/post-update` file with the following code:

```bash
#!/bin/bash
Folder="$( cd "$(dirname "${BASH_SOURCE[0]}")"; pwd -P )";
#YOUR_PHP_VERSION is the name of the php binary you will use i.e. `php-7.3`
source $Folder/../../PATH_TO_ROOT_FOLDER/vendor/c975l/site-bundle/Scripts/GitHookPostUpdate.sh YOUR_PHP_VERSION;
exit 0
```

### ImportSqlFile.sh

This script is useful if you store some SQL queries in a file to allow bulk import directly to MySql server. The script will rename the imported file (must be "/var/tmp/sqlFile.sql") before processing, to avoid collisions, and will rename it, after, with date and time. You can then simply add a new cron with the following code:

```bash
MAILTO=YOUR_EMAIL_ADDRESS
*/20    *       *       *       *       bash ~/run.as/httpdocs/vendor/c975l/site-bundle/Scripts/ImportSqlFile.sh 1> /dev/null
```

It will also delete files older than 7 days. It uses the data define in `/config/backup_config.cnf`, see below.

### BackupXXX.sh

These scripts helps for the backup of a website, they are detailed below. The backup files are stored in `/var/backup/{year}/[year-month]/{year-month-day}`. The files are named using the following scheme: "[MYSQL|WEBSITE]_-_NAME_-_YYYY-MM-DD_-_HH-II_-_[WithoutArchives|Archives|Complete|Partial].tar.bz2".

You can include them in a crontab like in the following to execute each hour between 06 and 22 at the 15 minute:

```bash
MAILTO=YOUR_EMAIL_ADDRESS
15       6-22       *       *       *       bash /server_path_website/vendor/c975l/site-bundle/Scripts/BackupXXX.sh
```

An email wil be sent via cron on each error and only once a day (at the hour specified in config file, see below) to sum up the backup actions.

You have to create a config file `/config/backup_config.cnf` with the following data (without space) **Keep in mind to add this file to your `.gitignore`**:

```txt
[client]
user=DB_USER
password=DB_PASSWORD
host=DB_HOST
[config]
website=WEBSITE_NAME
database=DATABASE_NAME
day=DAY_FOR_COMPLETE_BACKUP
hour=HOUR_FOR_COMPLETE_BACKUP This hour has to be one of which the cron will be launched otherwise it will never be reached
```

### BackupServer.sh

This script groups calls for `BackupMysql.sh` and `BackupFiles.sh` to allow only one crontab but they can be called individually.

### BackupMysql.sh

This script makes a backup of the tables in MySql server. All the tables are mysqldumped (one by one, to allow restore table by table) at each run, except those named with `_archives` which occurs once a day at the hour specified in `/config/backup_config.cnf`. There is also a mysqldump of the whole database, at the same hour specified as for `*_archives`, to allow a restore with only one file. The format used for the naming is "NAME_-_TABLE.sql".

### BackupFolders.sh

This script makes a backup of the `public` folder. There is a complete backup once a week and a partial backup (only new and newer files) other times.
You can specify a list of patterns to exclude, separated with lines break, in a file named `/config/backup_exclude.cnf` i.e `*/folder_to_exclude`.

## Twig Extensions

Using the provided Twig extension `RouteExists` you can check via `{% if route_exists('YOUR_ROUTE_TO_CHECK') %}` if the Route is available.

Using the provided Twig extension `TemplateExists` you can check via `{% if template_exists('YOUR_TEMPLATE_TO_CHECK') %}` if the template is available.

## Lists

You can use the provided lists:

- extensions
- bots
to check against. They can be called by the following code (requires [c975L/ConfigBundle](https://github.com/975L/ConfigBUndle)):

```php
use c975L\ConfigBundle\Service\ConfigServiceInterface;

class YourClass
{
    private $configService;

    public function __construct(ConfigServiceInterface $configService)
    {
        $this->configService = $configService;
    }

    public function yourMethod()
    {
        $extensions = file($this->configService->getContainerParameter('kernel.project_dir') . '/../vendor/c975l/site-bundle/Lists/extensions.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (in_array('txt', $extensions)) {
            //Do your stuff
        }
    }
}
```