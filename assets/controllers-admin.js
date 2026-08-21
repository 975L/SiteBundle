import { startStimulusApp } from '@symfony/stimulus-bundle';
import SitemapFieldsController from './js/sitemap-fields.js';
import PublicationSwitchController from './js/publication-switch.js';

// Back-office controllers, used only in EasyAdmin. Front-end controllers live in controllers.js Loaded as its own <script type="module"> tag (see importmap.php), starts its own Stimulus app
// The "title-confirm" controller PageCrudController mounts on its title field, and the "eaIndexSort" one reordering CollectionItemCrudController's index rows, are both registered by UiBundle, whose own admin app is loaded on every back-office page
const app = startStimulusApp();
app.register('sitemap-fields', SitemapFieldsController);
app.register('publication-switch', PublicationSwitchController);
