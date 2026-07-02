import { startStimulusApp } from '@symfony/stimulus-bundle';
import BasicController from './js/basic.js';
import CookieConsentController from './js/cookie-consent.js';
import MatomoController from './js/matomo.js';

// Loaded as its own <script type="module"> tag (see importmap.php), starts its own Stimulus app
const app = startStimulusApp();
app.register('basic', BasicController);
app.register('cookieConsent', CookieConsentController);
app.register('matomo', MatomoController);
