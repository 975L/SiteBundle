SiteBundle
==========

SiteBundle does the following:
- Defines a layout used to display the web pages,
- Variables are used to display data linked to website, name, etc.,
- Allows to add Matomo javascript by just set url and id,
- Allows to add CookieConsent by just adding the cookies page,

[Site Bundle dedicated web page](https://975l.com/en/pages/site-bundle).

Bundle installation
===================

Step 1: Download the Bundle
---------------------------
Use [Composer](https://getcomposer.org) to install the library
```bash
    composer require c975L/site-bundle
},
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
Install images by running
```bash
php bin/console assets:install --symlink
```
It will copy content of folder `Resources/public/` to your web folder. These files are used in the `layout.html.twig`.

How to use
----------
You need to create a file named `layout.html.twig` in your `app/Resources/views/` that extend `@c975LSite/layout.html.twig`, so simply add this `{% extends '@c975LSite/layout.html.twig' %}` at its top.

SiteBundle use the following variables to display information through the template.
You need to set them in your `app/Resources/views/layout.html.twig`. Simply copy/paste them and set the right data. If you don't set them, they will simply not be used.
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
Note: If you use c975L/PageEdit the variables are already passed to `layout.html.twig`.

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
{# or use the texts define in SiteBundle #}
{%
    set cookieConsent = {
        'message': 'text.cookies_banner'|trans({}, 'site'),
        'dismiss': 'text.cookies_dismiss'|trans({}, 'site'),
        'link': 'text.cookies_policy'|trans({}, 'site'),
        'href': 'YOUR_COOKIES_POLICY_LINK'
    }
%}
```

Error pages
-----------
You can also use the templates for common error pages. For this, you need to follow [How to Customize Error Pages](http://symfony.com/doc/current/controller/error_pages.html) to create the structure `app/Resources/TwigBundle/views/Exception` and files for each type of error. Of course you can still stop at the level of overidding `TwigBundle/Exception`.

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
    <link rel="stylesheet" type="text/css" href="YOUR_STYLESHEET" />
{% endblock %}
```

Add javascripts
---------------
To add javascripts, simply add the following  in your `app/Resources/views/layout.html.twig`:
```twig
{% block javascripts %}
    {{ parent() }}
    <script defer type="text/javascript" src="YOUR_JAVASCRIPT_FILE"></script>
{% endblock %}
```

Disable a block
---------------
To disable a block, simply add the following  in your `app/Resources/views/layout.html.twig`:
```twig
{% block share %}
{% endblock %}
```
Have a look at `/Resources/views/layout.html.twig`, to see all available blocks.
