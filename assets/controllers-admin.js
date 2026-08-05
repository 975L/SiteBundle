import { startStimulusApp } from '@symfony/stimulus-bundle';
import CollectionItemSortController from './js/collection-item-sort.js';
import SitemapFieldsController from './js/sitemap-fields.js';
import PublicationSwitchController from './js/publication-switch.js';

// Back-office controllers, used only in EasyAdmin. Front-end controllers live in controllers.js Loaded as its own <script type="module"> tag (see importmap.php), starts its own Stimulus app
// The "title-confirm" controller PageCrudController mounts on its title field is registered by UiBundle, whose own admin app is loaded on every back-office page
const app = startStimulusApp();
app.register('collection-item-sort', CollectionItemSortController);
app.register('sitemap-fields', SitemapFieldsController);
app.register('publication-switch', PublicationSwitchController);

// Mounted on <body> automatically, same reasoning as UiBundle's eaSortable: CollectionItemCrudController's index is a plain EasyAdmin CRUD list, its template can't set data-controller without overriding far more of the layout than collection_item_crud_index.html.twig already does.
document.body.setAttribute(
    'data-controller',
    [document.body.dataset.controller, 'collection-item-sort'].filter(Boolean).join(' ')
);
