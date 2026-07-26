import { startStimulusApp } from '@symfony/stimulus-bundle';
import BasicController from './js/basic.js';
import CookieConsentController from './js/cookie-consent.js';
import MatomoController from './js/matomo.js';

// Loaded as its own <script type="module"> tag (see importmap.php), starts its own Stimulus app
const app = startStimulusApp();
app.register('basic', BasicController);
// Kebab-case identifier on purpose - Stimulus derives value/target attribute names from the identifier as registered, so a camelCase one silently breaks every "data-<identifier>-*-value" binding
app.register('cookie-consent', CookieConsentController);
app.register('matomo', MatomoController);
