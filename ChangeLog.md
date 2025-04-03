# Changelog

## v6.17.9

- Added translations (03/04/2025)

## v6.17.8.1

- Corrected css error (02/04/2025)

## v6.17.8

- Added .btn-grey (02/04/2025)

## v6.17.7.1

- Corrected background color for .btn-small (31/03/2025)

## v6.17.7

- Added .btn-large (27/03/2025)

## v6.17.6.1

- Codacy corrections (26/03/2025)

## v6.17.6

- Corrected Slider (26/03/2025)

## v6.17.5.1

- Removed background color for btn-small class (22/03/2025)

## v6.17.5

- Added missing translations from fusion of c975L/ServicesBundle (22/03/2025)

## v6.17.4

- Corrected template call of inc_content (09/03/2025)

## v6.17.3

- Removed s from Service (09/03/2025)

## v6.17.2

- Corrected autowire (09/03/2025)

## v6.17.1

- Corrected namespace (09/03/2025)

## v6.17

- Removed use of `c975L/ServicesBundle` and include its service inside this bundle (09/03/2025)
- Removed use of `c975L/IncludeLibraryBundle` (09/03/2025)

## v6.16.6

- Made use of absolute_url for Images Components (22/02/2025)

## v6.16.5

- Modified button secondary colors for better contrast (27/01/2025)

## v6.16.4.1

- Corrected button to avoid leading _ when inline button (26/01/2025)

## v6.16.4

- Corrected button (26/01/2025)

## v6.16.3

- Added css for blockquote (26/01/2025)

## v6.16.2

- Added condition length > 0 for Slider (12/01/2025)

## v6.16.1

- Modified styles for slider (12/01/2025)

## v6.16

- Modified Slider to authorize credits per image (12/01/2025)

## v6.15.2

- Added parameter c975LCommon.url (10/01/2025)

## v6.15.1

- Added style for c975L/ContactFormBundle honeypot (26/11/2024)

## v6.15

- Added an Audio component (27/10/2024)

## v6.14.4

- Added Video:Iframe component (24/10/2024)
- Added attributes loop and muted to Video component (24/10/2024)

## v6.14.3.1

- Corrected example of Video component (24/10/2024)

## v6.14.3

- Modified Video component (22/10/2024)

## v6.14.2

- Version due to conflict (16/10/2024)

## v6.14.1

- Added default value for aria-label in componenet Image:Link (16/10/2024)

## v6.14

- Removed text-center from Card component (15/10/2024)

## v6.13.1

- Added a default value for aria-label in Componenet Image:Link (16/10/2024)

## v6.13

- Added span to Image component to be able to select label (15/10/2024)
- Added Video componenet (15/10/2024)

## v6.12.10

- Corrected components to make them re-usable (15/10/2024)

## v6.12.9

- Corrected slider that was not initialized on first load (10/10/2024)

## v6.12.8

- Added aria-label="{{ label }}" to Image:Link (07/10/2024)

## v6.12.7

- Corrected Button component (30/09/2024)

## v6.12.6

- Suppressed first slash of fileif present (30/09/2024)

## v6.12.5

- Modified requirement for AssetController file (30/09/2024)

## v6.12.4

- Corrections from Codacy (29/09/2024)

## v6.12.3

- Converted Matomo and CookieConsent to components (29/09/2024)

## v6.12.2

- Corrections from Codacy (29/09/2024)

## v6.12.1

- Converted some fragments to Componenets (29/09/2024)
- Added width and height on images as optional (29/09/2024)

## v6.12

- Added {{ importmap('app') }} for asset-mapper (29/09/2024)
- Moved block javascript to head (29/09/2024)
- Converted javascripts to Stimulus controllers [BC-Break] (29/09/2024)
- Added Component Stimulus:Controller (29/09/2024)
- Added confetti animation (29/09/2024)

## v6.11

- Removed lock from button and added icon [BC Break] (26/09/2024)
- Corrected slider style (26/09/2024)
- Modified Slider to display arrows and dots only for more than one item (26/09/2024)
- Added --button-secondary-color (26/09/2024)
- Added _tables.scss (26/09/2024)

## v6.10.1

- Corrected example of use for components (26/09/2024)

## v6.10

- Removed abbreviation for components [BC Break] (26/09/2024)
- Added class to Card component (26/09/2024)
- Added animations (26/09/2024)
- Used SASS for animations (26/09/2024)
- Put in place the use of prefers-reduced-motion (26/09/2024)
- Added dataAnimation for Card component (26/09/2024)

## v6.9.6.1

- Forgot the block... (24/09/2024)

## v6.9.6

- Added a {% block preconnect %} in `layout.html.twig` (24/09/2024)

## v6.9.5.2

- Added missing loading="lazy" (24/09/2024)

## v6.9.5.1

- Corrected use example of Card component (24/09/2024)

## v6.9.5

- Added styles (18/09/2024)
- Added possibility of inline buttons (18/09/2024)
- Modified examples of component to indicate optional between [] (18/09/2024)

## v6.9.4

- Suppressed functions-old.js (17/09/2024)
- Corrections from Codacy (17/09/2024)

## v6.9.3

- Re-factorisation of javascript functions (17/09/2024)

## v6.9.2

- Corrections identified by Codacy (16/09/2024)

## v6.9.1

- Added examples of use for Twig components intemplates (15/09/2024)
- Modified some components (15/09/2024)
- Added Slider component (15/09/2024)
- Added Image:Link componenet (16/09/2024)

## v6.9

- Added ->setMaxAge(3600) to controllers (15/09/2024)

## v6.8

- Added AssetController (15/09/2024)
- Added DownloadController (15/09/2024)
- Modified README (15/09/2024)
- Added require `symfony/ux-twig-component": "*"` (15/09/2024)
- Added styles (15/09/2024)
- Added Twig components (15/09/2024)

## v6.7.1

- Added style (13/09/2024)

## v6.7

- Suppressed spaceless filter as it's deprecated (12/09/2024)

## v6.6.13

- Suppressed title attributes that were not accessibility compliant (12/09/2024)

## v6.6.12

- Added style (01/09/2024)

## v6.6.11

- Added aria label for Top/Bottom buttons (21/08/2024)

## v6.6.10

- Added margin for footer elements (21/08/2024)

## v6.6.9

- Added  loading="lazy" (16/08/2024)

## v6.6.8

- Added styles (07/06/2024)

## v6.6.7

- Added styles (01/04/2024)

## v6.6.6

- Updated Command file (31/03/2024)

## v6.6.5.2

- Added class box-shadow (12/03/2024)

## v6.6.5.1

- Changed class img-rounded (11/03/2024)

## v6.6.5

- Added classes (09/03/2024)

## v6.6.4

- Added card styles (19/02/2024)

## v6.6.3

- Changed lead css (19/02/2024)

## v6.6.2

- Re-ordered css (19/02/2024)

## v6.6.1

- Codacy corrections (18/02/2024)

## v6.6

- Added card styles (18/02/2024)
- Moved css to mobile first (18/02/2024)
- Moved css to sass (18/02/2024)

## v6.5.3

- Corrected backTop and pullDown butons (16/02/2024)

## v6.5.2

- Removed use of errorImages array (15/02/2024)

## v6.5.1

- Added missing svg (15/02/2024)

## v6.5

- Changed error images and templates (15/02/2024)

## v6.4.4

- Added img/container styles (13/02/2024)

## v6.4.3

- Added images sizes in frgaments (12/02/2024)
- Added error images by default (12/02/2024)
- Added cookieconsent message by default (12/02/2024)

## v6.4.2

- Codacy corrections (11/02/2024)

## v6.4.1

- Added styles for forms (11/02/2024)
- Removed flash messages dismiss (11/02/2024)

## v6.4

- Removed use of jQuery (10/02/2024)

## v6.3.3

- Changed flash messages (30/01/2024)

## v6.3.2

- Changed input focus color to be less "agressive" (29/01/2024)

## v6.3.1

- Removed movement (due to border) on input focus and changed its color (29/01/2024)

## v6.3

- Added some animations (26/01/2024)

## v6.2.1

- Suppressed little things (25/01/2024)

## v6.2

- Suppressed trailing slashes (25/01/2024)

## v6.1.1

- Added possibility to have only site as page title in case title is set to '' (25/01/2024)

## v6.1

- Supressed load of libraries by default (24/01/2024)

## v6.0.1

- Cosemtic changes (22/01/2024)

## v6.0

- Changed to new recomended bundle SF 7 structure (16/01/2024)

Upgrading from v5.x? **Check UPGRADE.md**

## v5.0.1

- Changed to AbstractBundle (04/12/2023)

## v5.0

- Changed routes to attribute (04/12/2023)

Upgrading from v4.x? **Check UPGRADE.md**

## v4.0.2

- Added TreeBuilder return type (29/05/2023)

## v4.0.1

- Version not tagged (29/05/2023)

## v4.0

- Changed compatibility to PHP 8(25/07/2022)

Upgrading from v3.x? **Check UPGRADE.md**

## v3.2

- Added return type for Voter (24/07/2022)
- Changed composer versions constraints (24/07/2022)

## v3.1

- Added semantic balises (03/06/2022)

## v3.0.5

- Added meta data (15/04/2022)

## v3.0.4

- Modified fragments (02/03/2022)

## v3.0.3

- Added fragment hostedBy (02/03/2022)

## v3.0.2

- Corrected Command return for SF 4 (14/10/2021)

## v3.0.1

- Added return for console Command (08/10/2021)

## v3.0

- Changed `localizeddate` to `format_datetime` (20/09/2021)

Upgrading from v2.x? **Check UPGRADE.md**

## v2.x

## v2.5

- Removed versions constraints in composer (03/09/2021)

## v2.4.2

- Updated Matomo script (22/07/2020)

## v2.4.1

- Cosmetic changes due to Codacy review (05/03/2020)

## v2.4

- Added A4 print sizes (sorry for letter format users) (19/02/2020)

## v2.3

- Removed use of symplify/easy-coding-standard as abandonned (19/02/2020)

## v2.2.4

- Suppressed transform on form field hover as quite annoying (19/02/2020)

## v2.2.3

- Removed composer.lock from Git (19/02/2020)

## v2.2.2.1

- Added attributs title (19/01/2020)

## v2.2.2

- Resized images to decrease downloaded size (28/11/2019)

## v2.2.1

- Added animations for inputs (18/11/2019)

## v2.2

- Made use of apply spaceless (05/08/2019)

## v2.1.1.1

- Forgotten to save layout.html.twig ;-) (03/06/2019)

## v2.1.1

- Removed forgotten call for bootstrap js (03/06/2019)

## v2.1

- Suppressed inclusion of bootstrap 3 by default in `layout.html.twig` (03/06/2019)

## v2.0.4.1

- Changed Github's author reference url (08/04/2019)

## v2.0.4

- Corrected README.md (19/03/2019)
- Made use of Twig filter spaceless instead of spaceless tag (22/03/2019)

## v2.0.3

- Removed deprecations for @Method (13/02/2019)
- Implemented AstractController instead of Controller (13/02/2019)
- Modified Dependencyinjection rootNode to be not empty (13/02/2019)

## v2.0.2

- Modified required versions in `composer.json` (25/12/2018)

## v2.0.1

- Corrected `UPGRADE.md` for `php bin/console config:create` (03/12/2018)
- Added rector to composer dev part (23/12/2018)
- Modified required versions in composer (23/12/2018)

## v2.0

- Created branch 1.x (02/09/2018)
- Updated composer.json (01/09/2018)
- Removed common data from layout that will be set via c975L/ConfigBundle (02/09/2018)
- Updated `README.md` (02/09/2018)
- Added `bundle.yaml` (02/09/2018)
- Made use of c975L/ConfigBundle (02/09/2018)
- Added `UPGRADE.md` (02/09/2018)
- Added Controller + Voter for Routes `site_config` + `dashboard_config` (02/09/2018)
- Cleaned Configuration class (02/09/2018)

Upgrading from v1.x? **Check UPGRADE.md**

## v1.x

## v1.6.7.3

- Added meta "og:site_name" (19/08/2018)
- Added link to BuyMeCoffee (22/08/2018)
- Added link to apidoc (22/08/2018)
- Added documentation (22/08/2018)

## v1.6.7.2

- Removed chrome value for "X-UA-Compatible" (03/07/2018)
- Added href value for alternate language when only one (03/07/2018)
- Suppressed 'type="text/javascript"' as unneeded (03/07/2018)

## v1.6.7.1

- Removed viewport values that prevent users from resizing documents (10/06/2018)

## v1.6.7

- Removed old IE versions warnings (27/05/2018)
- Corrected meta copyright (27/05/2018)
- Re-ordered css form largest to smallest screen size and removed `!important` (06/06/2018)
- Added language declaration in openinng html (10/06/2018)
- Corrected base balise (10/06/2018)

## v1.6.6

- Updated privacy-policy linked to GDPR (25/05/2018)

## v1.6.5.5

- Removed required in composer.json (22/05/2018)

## v1.6.5.4

- Corrected some styles (15/05/2018)
- Added styles for "toolbar" (15/05/2018)

## v1.6.5.3

- Corrrected input outline (13/05/2018)

## v1.6.5.2

- Corrected `services.yml` (13/05/2018)

## v1.6.5.1

- Corrected missing file for auto-discovery of services (12/05/2018)

## v1.6.5

- Added "line" style in place of "box" style for input fields (12/05/2018)

## v1.6.4.1

- Removed in `README.md` blocks to disable for error pages as if they are removed we lose some functionalities (04/05/2018)

## v1.6.4

- Set on one line matomo code (28/04/2018)
- Added condition for ogImage != null for display (02/05/2018)

## v1.6.3

- Corrected text for err410 (14/04/2018)
- Suppressed contact link in error templates as c975L/ContactFormBundle may not be installed (14/04/2018)

## v1.6.2

- Added javascript function `nl2br()` to remove carriage returns (04/04/2018)
- Added ogImage variable to separate from logo (05/04/2018)]

## v1.6.1

- Corrected copyright date display to set only one year if firstOnlineDate == current year (03/04/2018)

## v1.6

- Changed the format of `languagesAlt` to be re-used for `navbarLanguagesDropdownMenu.html.twig` [BC-Break] (23/03/2018)
- Added fragment `navbarLanguagesDropdownMenu.html.twig` (23/03/2018)

## v1.5.4.1

- Added condition `display == pdf` for block `logoPrintOnly` in `layout.html.twig` (21/03/2018)
- Added removing of displaying url in print format in `styles.css` (22/03/2018)

## v1.5.4

- Added condition `display == html` to load jQuery in `layout.html.twig` (21/03/2018)

## v1.5.3.1

- Suppressed second call of jQuery (19/03/2018)

## v1.5.3

- Corrected block `acceptation` in Terms of use (18/03/2018)
- Added empty block `payingServices` to Terms of use to allow override (18/03/2018)
- Added empty block `services` to Terms of sales to allow override (18/03/2018)
- Added full layout example to `README.md` (18/03/2018)

## v1.5.2

- Added `hreflang` meta for multiples languages (15/03/2018)
- Added full example of layout in `README.md` (15/03/2018)
- Added css styles (15/03/2018)
- Added DependencyInjection to discover services (15/03/2018)

## v1.5.1

- Moved jQuery call into its proper block at the top of body, in order that it's loaded before any other jQuery function call (13/03/2018)

## v1.5

- Added `models:twig2md` Command to convert templates to Markdown to make their reading easier on Github (13/03/2018)
- Added Markdown format for pre-defined models (13/03/2018)

## v1.4.3

- Changed scroll value for pullDown (12/03/2018)

## v1.4.2

- Corrected error410 page (12/03/2018)

## v1.4.1

- Re-added the possibility to call default language at country level, as it's useful for multilingual sites (12/03/2018)

## v1.4

- Suppressed "div.container" in error pages (12/03/2018)
- Added country level folder for models (12/03/2018)

## v1.3.1

- Added color named styles (09/03/2018)
- Added default value for copyright (09/03/2018)
- Added model for Privacy policy (09/03/2018)
- Added a test to display the more accurate latest update between the models files and the date provided by the site (09/03/2018)
- Corrected pullDown javascript function (09/03/2018)

## v1.3

- Added print styles for bootsrapt alerts (08/03/2018)
- Changed size for print logo (08/03/2018)
- Updated `README.md` (08/03/2018)
- Corrected translations for error pages (08/03/2018)
- Added models for Terms of use, Terms of sales, etc. (08/03/2018)

## v1.2.2

- Added Twitter cards (07/03/2018)
- Corrected indentation in `layout.html.twig` (07/03/2018)
- Changed `README.md` to use `inc_content()` (07/03/2018)

## v1.2.1

- Corrected `layout.html.twig` for ` if display` to check if it's not pdf instead of checking 'html' as display can take other values (07/03/2018)

## v1.2

- Moved pullDown bookmark after footer (05/03/2018)
- Added block `navigationBottom` (06/03/2018)
- Added block `container` (06/03/2018)
- Added conditions to test if display is for html or pdf (Used by c975L/PageEdit) (06/03/2018)
- Added meta `hreflang` (06/03/2018)
- Added css styles (06/03/2018)

## v1.1

- Added core system files (04/03/2018)

## v1.0

- Creation of bundle (04/03/2018)
