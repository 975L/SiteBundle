# SiteBundle


SiteBundle does the following:

- Defines a layout used to display the web pages,
- Variables are used to display data linked to website, name, etc.,
- Allows to add Matomo javascript by just set url and id,
- Allows to add CookieConsent by just adding its data,
- Allows to have templates to override TwigBundle/Exception templates,
- Allows to use pre-defined Terms of use, Terms of sales, etc.

[SiteBundle dedicated web page](https://975l.com/en/pages/site-bundle).

[SiteBundle API documentation](https://975l.com/apidoc/c975L/SiteBundle.html).

## Bundle installation


### Step 1: Download the Bundle

Use [Composer](https://getcomposer.org) to install the library

```bash
    composer require c975L/site-bundle
```

### Step 2: Enable the Bundle

Then, enable the bundles by adding them to the list of registered bundles in the `app/AppKernel.php` file of your project:

```php
<?php
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = [
            // ...
            new c975L\SiteBundle\c975LSiteBundle(),
        ];
    }
}
```

### Step 3: Configure the Bundle

v2.0+ of c975LSiteBundle uses [c975L/ConfigBundle](https://github.com/975L/ConfigBundle) to manage configuration parameters. Use the Route "/site/config" with the proper user role to modify them.

Upgrading from v1.x? **Check UPGRADE.md**

### Step 4: Enable the Routes

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

### Step 5: install assets to web folder

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

You can define the meta `<link rel="alternate" hreflang="YOUR_LANGUAGE" href="URL_WITH_ALTERNATE_LANGUAGE" />` by setting a `languagesAlt` array in your `app/Resources/views/layout.html.twig`

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

If you want to display pictures in those error pages, simply add the following array in your `app/Resources/views/layout.html.twig`:

```twig
{%
    set errImages = {
        'err': asset('PATH_TO_YOUR_IMAGE_FOR_ERR'),
        'err401': asset('PATH_TO_YOUR_IMAGE_FOR_ERR_401'),
        'err403': asset('PATH_TO_YOUR_IMAGE_FOR_ERR_403'),
        'err404': asset('PATH_TO_YOUR_IMAGE_FOR_ERR_404'),
        'err410': asset('PATH_TO_YOUR_IMAGE_FOR_ERR_410'),
        'err500': asset('PATH_TO_YOUR_IMAGE_FOR_ERR_500'),
    }
%}
```

### Add stylesheets

To add stylesheets, simply add the following  in your `app/Resources/views/layout.html.twig`:

```twig
{% block stylesheets %}
    {{ parent() }}
    {# Using c975L/IncludeLibraryBundle #}
    {# With url #}
    {{ inc_lib('SUPPORTED_LIBRARY', 'css', 'SUPPORTED_VERSION_SELECTOR') }}
    {# Local file #}
    {{ inc_lib(absolute_url(asset('YOUR_STYLESHEET.css')), 'local') }}

    {# Of course you can provide the full "link" html data #}
{% endblock %}
```

### Add javascripts

To add javascripts, simply add the following  in your `app/Resources/views/layout.html.twig`:

```twig
{% block javascripts %}
    {{ parent() }}
    {# Using c975L/IncludeLibraryBundle #}
    {# With url #}
    {{ inc_lib('SUPPORTED_LIBRARY', 'js', 'SUPPORTED_VERSION_SELECTOR') }}
    {# Local file #}
    {{ inc_lib(absolute_url(asset('YOUR_JAVASCRIPT_FILE.js')), 'local') }}

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
{%
    set errImages = {
        'err': asset('PATH_TO_YOUR_IMAGE_FOR_ERR'),
        'err401': asset('PATH_TO_YOUR_IMAGE_FOR_ERR_401'),
        'err403': asset('PATH_TO_YOUR_IMAGE_FOR_ERR_403'),
        'err404': asset('PATH_TO_YOUR_IMAGE_FOR_ERR_404'),
        'err410': asset('PATH_TO_YOUR_IMAGE_FOR_ERR_410'),
        'err500': asset('PATH_TO_YOUR_IMAGE_FOR_ERR_500'),
    }
%}

{# Meta #}
{% block meta %}
    {{ parent() }}
{# Facebook app_id #}
    <meta property="fb:app_id" content="YOUR_FACEBOOK_APP_ID" />
{% endblock %}

{# Css #}
{% block stylesheets %}
    {{ parent() }}
    {% if display == 'pdf' %}
        {{ inc_content(absolute_url(asset('css/styles.min.css')), 'local') }}
    {% else %}
        {{ inc_font('Wendy One') }}
        {{ inc_lib(absolute_url(asset('css/styles.min.css')), 'local') }}
    {% endif %}
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
    {% if display == 'html' %}
        {{ inc_lib(absolute_url(asset('js/functions.min.js')), 'local') }}
    {% endif %}
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
