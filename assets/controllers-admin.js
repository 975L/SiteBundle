import { startStimulusApp } from '@symfony/stimulus-bundle';
import TitleConfirmController from './js/title-confirm.js';
import CollectionEntrySortController from './js/collection-entry-sort.js';

// Back-office controllers, used only in EasyAdmin. Front-end controllers live in controllers.js
// Loaded as its own <script type="module"> tag (see importmap.php), starts its own Stimulus app
const app = startStimulusApp();
app.register('titleConfirm', TitleConfirmController);
app.register('collectionEntrySort', CollectionEntrySortController);

// Mounted on <body> automatically, same reasoning as UiBundle's eaSortable: CollectionEntryCrudController's
// index is a plain EasyAdmin CRUD list, its template can't set data-controller without overriding far more
// of the layout than collection_entry_crud_index.html.twig already does.
document.body.setAttribute(
    'data-controller',
    [document.body.dataset.controller, 'collectionEntrySort'].filter(Boolean).join(' ')
);
