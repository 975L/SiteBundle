/*
 * (c) 2018: 975L <contact@975l.com>
 * (c) 2018: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

//h5bp - Avoids console$s errors
function htmlBoilerPlate() {
    if (!(window.console && console.log)) {
        (function() {
            var noop = function(){};
            var methods = ["assert", "clear", "count", "debug", "dir", "dirxml", "error", "exception", "group", "groupCollapsed", "groupEnd", "info", "log", "markTimeline", "profile", "profileEnd", "markTimeline", "table", "time", "timeEnd", "timeStamp", "trace", "warn"];
            var length = methods.length;
            var console = window.console = {};
            while(length--) {
                console[methods[length]] = noop;
            }
        }());
    }
}

//Replaces attributes rel="external" by target="_blank" in the links to avoid W3C validation problems - http://articles.sitepoint.com/article/standards-compliant-world/3
function externalLinks() {
    if (!document.getElementsByTagName) {
        return;
    }
    var anchors = document.getElementsByTagName("a");
    var cptAnchors = anchors.length;
    for(var i = 0; i < cptAnchors; i++) {
        var anchor = anchors[i];
        if (anchor.getAttribute("href") && anchor.getAttribute("rel") === "external") {
            anchor.target = "_blank";
        }
    }
}

//Replaces carriage returns by <br>
function nl2br(str) {
    return str.replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, "$1" + "<br>" + "$2");
}

//Document.ready
$(document).ready(function() {
//Adds padding to window with anchors - https://github.com/twbs/bootstrap/issues/1768
    var shiftWindow = function() { scrollBy(0, -120); };
    if (location.hash) {
        shiftWindow();
    }
    window.addEventListener("hashchange", shiftWindow);

//Creates the backTop & pullDown buttons - http://html-tuts.com/back-to-top-button-jquery/
    var amountScrolled = 300;
    $(window).scroll(function() {
        if ($(window).scrollTop() > amountScrolled) {
            $("a.backTop")
                .fadeIn("slow");
        } else {
            $("a.backTop")
                .fadeOut("slow");
            $("a.pullDown")
                .fadeIn("slow");
        }
    });
//backTop
    $("a.backTop").click(function() {
        $("html, body").animate({
            scrollTop: 0
        }, "slow");
        return false;
    });
//pullDown
    $("a.pullDown").click(function() {
        $("html, body").animate({
            scrollTop: $(document).height()
        }, "slow");
        return false;
    });
});