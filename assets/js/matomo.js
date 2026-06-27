/*
 * (c) 2024: 975L <contact@975l.com>
 * (c) 2024: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    // https://matomo.org/
    connect() {
        var _paq = window._paq = window._paq || [];
        _paq.push(["trackPageView"]);
        _paq.push(["enableLinkTracking"]);
        (function() {
            var u = this.element.dataset.matomoUrl + "/";
            _paq.push(["setTrackerUrl", u + "matomo.php"]);
            _paq.push(["setSiteId", this.element.dataset.matomoId]);
            var d = document, g = d.createElement("script"), s = d.getElementsByTagName("script")[0];
            g.type = "text/javascript"; g.async = true; g.src = u + "matomo.js"; s.parentNode.insertBefore(g, s);
        }).bind(this)();
    }
}