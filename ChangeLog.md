# Changelog

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