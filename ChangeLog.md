# Changelog

v2.2.2
------
- Resized images to decrease downloaded size (28/11/2019)

v2.2.1
------
- Added animations for inputs (18/11/2019)

v2.2
----
- Made use of apply spaceless (05/08/2019)

v2.1.1.1
--------
- Forgotten to save layout.html.twig ;-) (03/06/2019)

v2.1.1
------
- Removed forgotten call for bootstrap js (03/06/2019)

v2.1
----
- Suppressed inclusion of bootstrap 3 by default in `layout.html.twig` (03/06/2019)

v2.0.4.1
--------
- Changed Github's author reference url (08/04/2019)

v2.0.4
------
- Corrected README.md (19/03/2019)
- Made use of Twig filter spaceless instead of spaceless tag (22/03/2019)

v2.0.3
------
- Removed deprecations for @Method (13/02/2019)
- Implemented AstractController instead of Controller (13/02/2019)
- Modified Dependencyinjection rootNode to be not empty (13/02/2019)

v2.0.2
------
- Modified required versions in `composer.json` (25/12/2018)

v2.0.1
------
- Corrected `UPGRADE.md` for `php bin/console config:create` (03/12/2018)
- Added rector to composer dev part (23/12/2018)
- Modified required versions in composer (23/12/2018)

v2.0
----
- Created branch 1.x (02/09/2018)
- Updated composer.json (01/09/2018)
- Removed common data from layout that will be set via c975L/ConfigBundle (02/09/2018)
- Updated `README.md` (02/09/2018)
- Added `bundle.yaml` (02/09/2018)
- Made use of c975L/ConfigBundle (02/09/2018)
- Added `UPGRADE.md` (02/09/2018)
- Added Controller + Voter for Routes `site_config` + `dashboard_config` (02/09/2018)
- Cleaned Configuration class (02/09/2018)


v1.x
====

v1.6.7.3
--------
- Added meta "og:site_name" (19/08/2018)
- Added link to BuyMeCoffee (22/08/2018)
- Added link to apidoc (22/08/2018)
- Added documentation (22/08/2018)

v1.6.7.2
--------
- Removed chrome value for "X-UA-Compatible" (03/07/2018)
- Added href value for alternate language when only one (03/07/2018)
- Suppressed 'type="text/javascript"' as unneeded (03/07/2018)

v1.6.7.1
--------
- Removed viewport values that prevent users from resizing documents (10/06/2018)

v1.6.7
------
- Removed old IE versions warnings (27/05/2018)
- Corrected meta copyright (27/05/2018)
- Re-ordered css form largest to smallest screen size and removed `!important` (06/06/2018)
- Added language declaration in openinng html (10/06/2018)
- Corrected base balise (10/06/2018)

v1.6.6
------
- Updated privacy-policy linked to GDPR (25/05/2018)

v1.6.5.5
--------
- Removed required in composer.json (22/05/2018)

v1.6.5.4
--------
- Corrected some styles (15/05/2018)
- Added styles for "toolbar" (15/05/2018)

v1.6.5.3
--------
- Corrrected input outline (13/05/2018)

v1.6.5.2
--------
- Corrected `services.yml` (13/05/2018)

v1.6.5.1
--------
- Corrected missing file for auto-discovery of services (12/05/2018)

v1.6.5
------
- Added "line" style in place of "box" style for input fields (12/05/2018)

v1.6.4.1
--------
- Removed in `README.md` blocks to disable for error pages as if they are removed we lose some functionalities (04/05/2018)

v1.6.4
------
- Set on one line matomo code (28/04/2018)
- Added condition for ogImage != null for display (02/05/2018)

v1.6.3
------
- Corrected text for err410 (14/04/2018)
- Suppressed contact link in error templates as c975L/ContactFormBundle may not be installed (14/04/2018)

v1.6.2
------
- Added javascript function `nl2br()` to remove carriage returns (04/04/2018)
- Added ogImage variable to separate from logo (05/04/2018)]

v1.6.1
------
- Corrected copyright date display to set only one year if firstOnlineDate == current year (03/04/2018)

v1.6
----
- Changed the format of `languagesAlt` to be re-used for `navbarLanguagesDropdownMenu.html.twig` [BC-Break] (23/03/2018)
- Added fragment `navbarLanguagesDropdownMenu.html.twig` (23/03/2018)

v1.5.4.1
--------
- Added condition `display == pdf` for block `logoPrintOnly` in `layout.html.twig` (21/03/2018)
- Added removing of displaying url in print format in `styles.css` (22/03/2018)

v1.5.4
------
- Added condition `display == html` to load jQuery in `layout.html.twig` (21/03/2018)

v1.5.3.1
--------
- Suppressed second call of jQuery (19/03/2018)

v1.5.3
------
- Corrected block `acceptation` in Terms of use (18/03/2018)
- Added empty block `payingServices` to Terms of use to allow override (18/03/2018)
- Added empty block `services` to Terms of sales to allow override (18/03/2018)
- Added full layout example to `README.md` (18/03/2018)

v1.5.2
------
- Added `hreflang` meta for multiples languages (15/03/2018)
- Added full example of layout in `README.md` (15/03/2018)
- Added css styles (15/03/2018)
- Added DependencyInjection to discover services (15/03/2018)

v1.5.1
------
- Moved jQuery call into its proper block at the top of body, in order that it's loaded before any other jQuery function call (13/03/2018)

v1.5
----
- Added `models:twig2md` Command to convert templates to Markdown to make their reading easier on Github (13/03/2018)
- Added Markdown format for pre-defined models (13/03/2018)

v1.4.3
------
- Changed scroll value for pullDown (12/03/2018)

v1.4.2
------
- Corrected error410 page (12/03/2018)

v1.4.1
------
- Re-added the possibility to call default language at country level, as it's useful for multilingual sites (12/03/2018)

v1.4
----
- Suppressed "div.container" in error pages (12/03/2018)
- Added country level folder for models (12/03/2018)

v1.3.1
------
- Added color named styles (09/03/2018)
- Added default value for copyright (09/03/2018)
- Added model for Privacy policy (09/03/2018)
- Added a test to display the more accurate latest update between the models files and the date provided by the site (09/03/2018)
- Corrected pullDown javascript function (09/03/2018)

v1.3
----
- Added print styles for bootsrapt alerts (08/03/2018)
- Changed size for print logo (08/03/2018)
- Updated `README.md` (08/03/2018)
- Corrected translations for error pages (08/03/2018)
- Added models for Terms of use, Terms of sales, etc. (08/03/2018)

v1.2.2
------
- Added Twitter cards (07/03/2018)
- Corrected indentation in `layout.html.twig` (07/03/2018)
- Changed `README.md` to use `inc_content()` (07/03/2018)

v1.2.1
------
- Corrected `layout.html.twig` for ` if display` to check if it's not pdf instead of checking 'html' as display can take other values (07/03/2018)

v1.2
----
- Moved pullDown bookmark after footer (05/03/2018)
- Added block `navigationBottom` (06/03/2018)
- Added block `container` (06/03/2018)
- Added conditions to test if display is for html or pdf (Used by c975L/PageEdit) (06/03/2018)
- Added meta `hreflang` (06/03/2018)
- Added css styles (06/03/2018)

v1.1
----
- Added core system files (04/03/2018)

v1.0
----
- Creation of bundle (04/03/2018)
