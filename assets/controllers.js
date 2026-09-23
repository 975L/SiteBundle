import { startStimulusApp } from '@symfony/stimulus-bundle';
import BasicController from './js/basic.js';
import LanguagesController from './js/languages.js';
import MenuActiveController from './js/menu-active.js';

// Loaded as its own <script type="module"> tag (see importmap.php), joining the one Stimulus application of the page shared with the other bundles - see UiBundle's controllers.js
globalThis.c975lStimulusApp ??= startStimulusApp();
const app = globalThis.c975lStimulusApp;
app.register('basic', BasicController);
app.register('languages', LanguagesController);
app.register('menu-active', MenuActiveController);
