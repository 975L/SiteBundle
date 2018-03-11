SiteBundle
==========

SiteBundle does the following:
- Defines a layout used to display the web pages,
- Variables are used to display data linked to website, name, etc.,
- Allows to add Matomo javascript by just set url and id,
- Allows to add CookieConsent by just adding its data,
- Allows to have templates to override TwigBundle/Exception templates,
- Allows to use pre-defined Terms of use, Terms of sales, etc.

[Site Bundle dedicated web page](https://975l.com/en/pages/site-bundle).

Bundle installation
===================

Step 1: Download the Bundle
---------------------------
Use [Composer](https://getcomposer.org) to install the library
```bash
    composer require c975L/site-bundle
```

Step 2: Enable the Bundle
-------------------------
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

Step 3: install assets to web folder
------------------------------------
Install assets by running
```bash
php bin/console assets:install --symlink
```
It will create a link from folder `Resources/public/` in your web folder. These files are used in the `layout.html.twig`.

How to use
----------
You **must** create a file named `layout.html.twig` in your `app/Resources/views/` that extend `@c975LSite/layout.html.twig`, so simply add this `{% extends '@c975LSite/layout.html.twig' %}` at its top.

SiteBundle use the following variables to display information through the template.
You need to set them in your `app/Resources/views/layout.html.twig`. Simply copy/paste them and set the right data. If you don't set them, they will be ignored.
```twig
{% set site = 'YOUR_SITE_NAME' %}
{% set author = 'THE_AUTHOR' %}
{% set firstOnlineDate = 'YYYY-MM-DD' %}
{% set logo = absolute_url(asset('images/og-image.png')) %}
{% set favicon = absolute_url(asset('favicon.ico')) %}
{% set appleTouchIcon = absolute_url(asset('apple-touch-icon.png')) %}
```

SiteBundle also use the following variables which are page-based, meaning that they change for each page. If you want to use them, simply declare them on each page that extend your `app/Resources/views/layout.html.twig`.
```twig
{% set title = 'YOUR_PAGE_TITLE' %}
{% set description = 'YOUR_PAGE_DESCRIPTION' %}
```
Note: If you use [c975L/PageEdit](https://github.com/975L/PageEditBundle) the variables are already passed to `layout.html.twig`.

Override a block
----------------

You can override any block in the template, to do so, simply add the following in your `app/Resources/views/layout.html.twig`, you can still use the `{{ parent() }}` Twig function:
```twig
{% block share %}
    {# YOUR_OWN_TEXT #}
{% endblock %}
```
Have a look at `Resources/views/layout.html.twig`, to see all available blocks.

Disable a block
---------------
To disable a block, simply add the following in your `app/Resources/views/layout.html.twig`:
```twig
{% block share %}
{% endblock %}
```
Have a look at `Resources/views/layout.html.twig`, to see all available blocks.

Use the display variable
------------------------
In your `app/Resources/views/layout.html.twig` you can use the following to include (or not) templates:
```twig
{% if display == 'pdf' %}
    {% include 'header-pdf.html.twig' %}
{% else %}
    {% include 'header.html.twig' %}
{% endif %}
```
if `display` is not defined, hten it's define to `html`.

Matomo javascript
-----------------
You can easily add a call to matomo by adding the following in your `app/Resources/views/layout.html.twig`:
```twig
{%
    set matomo = {
        'id': YOUR_MATOMO_ID,
        'url': 'YOUR_MATOMO_URL'
    }
%}
```

CookieConsent
-------------
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

Error pages
-----------
You can also use the templates for common error pages. For this, you need to follow [How to Customize Error Pages](http://symfony.com/doc/current/controller/error_pages.html) to create the structure `app/Resources/TwigBundle/views/Exception` and files for each type of error. Of course you can still stop at the level of overidding `TwigBundle/Exception`, but if you want to use the pre-defined error templates, do the following:

The types of error covered by SiteBundle are:
- error
- error401
- error403
- error404
- error410
- error500

In each file copy/paste the following code:
**Take care to modify the error code in the included template name, i.e. "404" given here**

```twig
{% extends 'layout.html.twig' %}

{% block content %}
    {% include('@c975LSite/Exception/error404.html.twig') %}
{% endblock %}

{% block meta %}
{% endblock %}

{% block logoPrintOnly %}
{% endblock %}

{% block flashes %}
{% endblock %}

{% block share %}
{% endblock %}

{% block javascripts %}
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

Add stylesheets
---------------
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
Add javascripts
---------------
To add javascripts, simply add the following  in your `app/Resources/views/layout.html.twig`:
```twig
{% block javascripts %}
    {{ parent() }}
    {# Using c975L/IncludeLibraryBundle #}
    {# With url #}
    {{ inc_lib('SUPPORTED_LIBRARY', 'js', 'SUPPORTED_VERSION_SELECTOF') }}
    {# Local file #}
    {{ inc_lib(absolute_url(asset('YOUR_JAVASCRIPT_FILE.js')), 'local') }}
    
   {# Of course you can provide the full "script" html data #}
{% endblock %}
```
Use pre-defined models
======================
There are two ways to use the pre-defined models for Terms of use, Terms of sales, etc.:

Use whole file
--------------

You want to use the whole file, place this code in your template:
```twig
{% extends 'YOUR_LAYOUT.html.twig' %}

{% trans_default_domain 'site' %}
{# Title value is made of 'label.' + name of page, replacing "-" by "_" #}
{# i.e. page ''terms-of-sales' gives title = 'label.terms_of_sales' #}
{% set title = 'label.terms_of_sales'|trans %}

{% block content %}
    {# set the defined data (indicated at the top of the template file) before including #}
    {% set latestUpdate = '2018-03-08' %}

    {% include '@c975LSite/models/terms-of-sales.html.twig' %}

    {# You can your own data at the end #}
    <h2>Achat de crédits</h2>
    <p class="text-justify">
        L’achat de crédits ...
    </p>
{% endblock %}
```
Select blocks
-------------

You want to select the displayed blocks, place this code in your template. **Note** that you have to specify the language in the `embed` function:
```twig
{% extends 'YOUR_LAYOUT.html.twig' %}

{% trans_default_domain 'site' %}
{% set title = 'label.terms_of_sales'|trans %}

{% block content %}
    {# set the defined data (indicated at the top of the template file) before including #}
    {% set latestUpdate = '2018-03-08' %}

    {# As you embed the file, you have to specify the language #}
    {% embed '@c975LSite/models/fr/terms-of-sales.html.twig' %}
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

Check `Resources/models` for models available.

**Feel free to update them and/or add translations**, simply create the corresponding language folder if not existing.

By convention files are named using "-" and the english name.
