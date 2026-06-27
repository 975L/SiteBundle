import BasicController from './js/basic.js';
import CookieConsentController from './js/cookie-consent.js';
import MatomoController from './js/matomo.js';

export function register(c975lSite) {
    c975lSite.register('basic', BasicController);
    c975lSite.register('cookieConsent', CookieConsentController);
    c975lSite.register('matomo', MatomoController);
}