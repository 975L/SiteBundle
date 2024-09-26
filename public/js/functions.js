/*
 * (c) 2018: 975L <contact@975l.com>
 * (c) 2018: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

document.addEventListener("DOMContentLoaded", () => {
    htmlBoilerPlate();
    externalLinks();
    animateOnScroll();
});

window.addEventListener("scroll", () => {
    backTopButton();
    pullDownButton();
});

// h5bp - Avoids console errors
function htmlBoilerPlate() {
    if (!(window.console && console.log)) {
        (function () {
            const noop = () => {};
            const methods = [
                "assert", "clear", "count", "debug", "dir", "dirxml", "error", "exception", "group",
                "groupCollapsed", "groupEnd", "info", "log", "markTimeline", "profile", "profileEnd",
                "markTimeline", "table", "time", "timeEnd", "timeStamp", "trace", "warn"
            ];
            const console = window.console = {};
            methods.forEach((method) => {
                console[method] = noop;
            });
        }());
    }
}

// Replaces attributes rel="external" by target="_blank" in the links to avoid W3C validation problems
function externalLinks() {
    if (!document.getElementsByTagName) {
        return;
    }
    const anchors = document.getElementsByTagName("a");
    Array.from(anchors).forEach((anchor) => {
        if (anchor.getAttribute("href") && anchor.getAttribute("rel") === "external") {
            anchor.target = "_blank";
        }
    });
}

// Replaces carriage returns by <br>
function nl2br(str) {
    return str.replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, "$1<br>$2");
}

// Displays/Hides the backTop button
function backTopButton() {
    const amountScrolled = 300;
    const backTop = document.querySelector("a.backTop");

    if (backTop) {
        // Displays the backTop button
        if (window.scrollY > amountScrolled) {
            backTop.style.display = "block";
            backTop.classList.remove("fade-out");
            backTop.classList.add("fade-in");
        // Hides the backTop button
        } else {
            backTop.classList.remove("fade-in");
            backTop.classList.add("fade-out");
        }
    }
}

// Displays/Hides the pullDown button
function pullDownButton() {
    const amountScrolled = 300;
    const pullDown = document.querySelector("a.pullDown");

    if (pullDown) {
        // Displays the pullDown button
        if (window.scrollY + window.innerHeight + amountScrolled < document.body.scrollHeight) {
            pullDown.style.display = "block";
            pullDown.classList.remove("fade-out");
            pullDown.classList.add("fade-in");
        // Hides the pullDown button
        } else {
            pullDown.classList.remove("fade-in");
            pullDown.classList.add("fade-out");
        }
    }
}

// Slider function
function slider(slider) {
    let slideIndex = 1;

    const arrowLeft = document.querySelector(`#${slider} .arrow-left`);
    const arrowRight = document.querySelector(`#${slider} .arrow-right`);

    function displaySlide(slider, number) {
        const slides = document.querySelectorAll(`#${slider} .slider-img`);
        const dots = document.querySelectorAll(`#${slider} .slider-dot`);
        let index = number;

        // Suppress slides ands dots classes
        slides.forEach((slide) => slide.style.display = "none");
        dots.forEach((dot) => dot.classList.remove("active"));

        // Gets back to first slide
        if (number > slides.length) {
            index = 1;
        }
        // Gets back to last slide
        if (number < 1) {
            index = slides.length;
        }

        slides[index - 1].style.display = "block";
        dots[index - 1].classList.add("active");

        slideIndex = index;
    }

    if (arrowLeft && arrowRight) {
        displaySlide(slider, slideIndex);

        // EventListener for previous slide, <
        arrowLeft.addEventListener("click", () => {
            displaySlide(slider, slideIndex -= 1);
        });
        // EventListener for next slide, >
        arrowRight.addEventListener("click", () => {
            displaySlide(slider, slideIndex += 1);
        });
        // EventListener for next slide, click on slide
        const slides = document.querySelectorAll(`#${slider} .slider-img`);
        slides.forEach((slide) => {
            slide.addEventListener("click", () => {
                displaySlide(slider, slideIndex += 1);
            });
        });
        // EventListener for click on dot
        const dots = document.querySelectorAll(`#${slider} .slider-dot`);
        dots.forEach((dot) => {
            dot.addEventListener("click", () => {
                displaySlide(slider, Number(dot.getAttribute("dataset-number")));
            });
        });
    }
}

// Checks if element is in viewport
function isElementInViewport(element, offset) {
    if (null !== element) {
        const rect = element.getBoundingClientRect();
        return (
            rect.top < (window.innerHeight || document.documentElement.clientHeight) - offset &&
            rect.bottom >= 0
        );
    }
}

// Animates on scroll
function animateOnScroll() {
    var elements = document.querySelectorAll('.scroll');

    function onScroll() {
        elements.forEach(element => {
            if (isElementInViewport(element, 200)) {
                const animationClass = element.getAttribute('data-animation');
                element.classList.remove('hidden');
                element.classList.add(animationClass);
            }
        });
    }

    window.addEventListener('scroll', onScroll);
    onScroll();
};