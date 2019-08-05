# UPGRADE

v1.x > v2.x
-----------
When upgrading from v1.x to v2.x you should(must) do the following if they apply to your case:

- The following parameters used to be defined in the template `layout.html.twig` are not used anymore, as set via c975L/ConfigBundle, so you can delete them:
    - {% set site = 'YOUR_SITE_NAME' %}
    - {% set author = 'THE_AUTHOR' %}
    - {% set firstOnlineDate = 'YYYY-MM-DD' %}
    - {% set logo = absolute_url(asset('images/og-image.png')) %}
    - {% set favicon = absolute_url(asset('favicon.ico')) %}
    - {% set appleTouchIcon = absolute_url(asset('apple-touch-icon.png')) %}
- Before the first use of parameters, you **MUST** use the console command `php bin/console config:create` to create the config files with default data.
- You have to enable the Routes in `app/config/routing.yml`, see README.md
