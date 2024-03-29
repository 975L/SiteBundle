/*
 * (c) 2018: 975L <contact@975l.com>
 * (c) 2018: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

document.addEventListener("DOMContentLoaded", function () {
    htmlBoilerPlate();
    externalLinks();
});

window.addEventListener('scroll', function () {
    backTopButton();
    pullDownButton();
});

// h5bp - Avoids console errors
function htmlBoilerPlate() {
    if (!(window.console && console.log)) {
        (function () {
            let noop = function () { };
            let methods = ["assert", "clear", "count", "debug", "dir", "dirxml", "error", "exception", "group", "groupCollapsed", "groupEnd", "info", "log", "markTimeline", "profile", "profileEnd", "markTimeline", "table", "time", "timeEnd", "timeStamp", "trace", "warn"];
            let length = methods.length;
            let console = window.console = {};
            while (length--) {
                console[methods[length]] = noop;
            }
        }());
    }
}

// Replaces attributes rel="external" by target="_blank" in the links to avoid W3C validation problems - http://articles.sitepoint.com/article/standards-compliant-world/3
function externalLinks() {
    if (!document.getElementsByTagName) {
        return;
    }
    let anchors = document.getElementsByTagName("a");
    let cptAnchors = anchors.length;
    for (let i = 0; i < cptAnchors; i++) {
        let anchor = anchors[i];
        if (anchor.getAttribute("href") && anchor.getAttribute("rel") === "external") {
            anchor.target = "_blank";
        }
    }
}

// Replaces carriage returns by <br>
function nl2br(str) {
    return str.replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, "$1" + "<br>" + "$2");
}

// Displays/Hides the backTop button
function backTopButton() {
    const amountScrolled = 300;
    let backTop = document.querySelector('a.backTop');

    // Displays the backTop button
    if (backTop && window.scrollY > amountScrolled) {
        backTop.style.display = "block";
        backTop.classList.remove('fade-out');
        backTop.classList.add('fade-in');
    // Hides the backTop button
    } else {
        backTop.classList.remove('fade-in');
        backTop.classList.add('fade-out');
    }
}

// Displays/Hides the pullDown button
function pullDownButton() {
    const amountScrolled = 300;
    let pullDown = document.querySelector('a.pullDown');

    // Displays the pullDown button
    if (pullDown && window.scrollY + window.innerHeight + amountScrolled < document.body.scrollHeight) {
        pullDown.style.display = "block";
        pullDown.classList.remove('fade-out');
        pullDown.classList.add('fade-in');
    // Hides the pullDown button
    } else {
        pullDown.classList.remove('fade-in');
        pullDown.classList.add('fade-out');
    }
}