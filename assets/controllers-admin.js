import { startStimulusApp } from '@symfony/stimulus-bundle';
import SitemapFieldsController from './js/sitemap-fields.js';
import PublicationSwitchController from './js/publication-switch.js';

// Back-office controllers, used only in EasyAdmin (front-end ones live in controllers.js), loaded as its own <script type="module"> tag (see importmap.php) and joining the one Stimulus application of the page shared with the other bundles - "title-confirm" and "eaIndexSort" are registered by UiBundle, whose own admin barrel is loaded on every back-office page
globalThis.c975lStimulusApp ??= startStimulusApp();
const app = globalThis.c975lStimulusApp;
app.register('sitemap-fields', SitemapFieldsController);
app.register('publication-switch', PublicationSwitchController);
