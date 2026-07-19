/*
 * (c) 2024: 975L <contact@975l.com>
 * (c) 2024: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";
import Handlers from "./handlers.js";

export default class extends Controller {
    connect() {
        // Execute immediately for Turbo compatibility
        this.htmlBoilerPlate();
        this.externalLinks();
        this.smoothAnchorScroll();
        this.togglePasswordVisibility();
        this.validatePasswordFormat();
        this.validatePassword();
        // Also listen for scroll events
        window.addEventListener("scroll", () => {
            this.backTopButton();
            this.pullDownButton();
        });
    }

    // h5bp - Avoids console errors
    htmlBoilerPlate() {
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
            })();
        }
    }

    // Replaces attributes rel="external" by target="_blank" in the links to avoid W3C validation problems
    externalLinks() {
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

    // Scrolls to an in-page anchor (menu_link targets, "backTop"/"pullDown" buttons...) ourselves
    // instead of letting Turbo Drive handle the click: Turbo treats a same-page "#anchor" href as a
    // full page visit, which briefly pushes the hash to the address bar then drops it once the
    // re-rendered page settles. Calling preventDefault() here also stops Turbo's own click handling,
    // since it bubbles from this link up through this "basic"-controlled body to Turbo's document listener
    smoothAnchorScroll() {
        this.element.addEventListener("click", (event) => {
            const link = event.target.closest('a[href*="#"]');
            if (!link) {
                return;
            }

            const url = new URL(link.href, window.location.href);
            if (url.pathname !== window.location.pathname || url.hash === "") {
                return;
            }

            const target = document.getElementById(url.hash.slice(1));
            if (!target) {
                return;
            }

            event.preventDefault();
            history.pushState(null, "", url.hash);
            target.scrollIntoView({ behavior: "smooth", block: "start" });
        });
    }

    // Replaces carriage returns by <br>
    nl2br(str) {
        return str.replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, "$1<br>$2");
    }

    // Displays/Hides the backTop button
    backTopButton() {
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
    pullDownButton() {
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

    // Displays/Hides the password in the password fields
    togglePasswordVisibility() {
        let passwordInputs = document.querySelectorAll('input[type="password"]');
        passwordInputs.forEach((passwordInput) => {
            if (!passwordInput || passwordInput.closest(".has-toggle")) {
                return;
            }

            // Wraps only the input (not its label/help text) so the toggle icon stays vertically centered on the input itself
            let wrapper = document.createElement("div");
            wrapper.classList.add("has-toggle");
            passwordInput.parentNode.insertBefore(wrapper, passwordInput);
            wrapper.appendChild(passwordInput);

            // Defines toggle
            let toggle = document.createElement("span");
            toggle.classList.add("toggle-password");

            // Adds image
            let image = document.createElement("img");
            image.src = "/bundles/c975lsite/icons/eye.svg";
            toggle.appendChild(image);

            // Append toggle right after the input, inside the wrapper
            wrapper.appendChild(toggle);

            // Handles the click on the toggle
            toggle.addEventListener("click", function () {
                if (passwordInput.type === "password") {
                    passwordInput.type = "text";
                    passwordInput.setAttribute("autocomplete", "off");
                    image.src = "/bundles/c975lsite/icons/eye-slash.svg";
                } else {
                    passwordInput.type = "password";
                    passwordInput.setAttribute("autocomplete", "current-password");
                    image.src = "/bundles/c975lsite/icons/eye.svg";
                }
            });
        });
    }

    // Checks the password format before submitting the form
    validatePasswordFormat() {
        let passwordInput = document.getElementById("registration_form_plainPassword");
        let pattern = /^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[^A-Za-z0-9]).{8,}$/;
        let submitButton = document.querySelector('input[type="submit"]') ? document.querySelector('input[type="submit"]') : document.querySelector('button[type="submit"]');

        if (passwordInput) {
            passwordInput.addEventListener("blur", function () {
                if (!pattern.test(passwordInput.value)) {
                    passwordInput.classList.add("error");
                    passwordInput.parentNode.querySelector("#password_format_error")?.remove();

                    let errorMessage = document.createElement("p");
                    errorMessage.id = "password_format_error";
                    errorMessage.classList.add("error-message");
                    errorMessage.textContent = Handlers.translate("form.registration.password.error");
                    passwordInput.parentNode.insertBefore(errorMessage, passwordInput.nextSibling);
                    submitButton.disabled = true;
                } else {
                    passwordInput.classList.remove("error");
                    passwordInput.classList.add("success");
                    passwordInput.parentNode.querySelector("#password_format_error")?.remove();
                    submitButton.disabled = false;
                }
            });
        }
    }

    // Checks the password confirmation before submitting the form
    validatePassword() {
        let passwordInput = document.getElementById("registration_form_plainPassword");
        let confirmPasswordInput = document.getElementById("registration_form_confirmPassword");
        let submitButton = document.querySelector('input[type="submit"]') ? document.querySelector('input[type="submit"]') : document.querySelector('button[type="submit"]');

        if (passwordInput && confirmPasswordInput) {
            confirmPasswordInput.addEventListener("blur", function () {
                if (passwordInput.value !== confirmPasswordInput.value) {
                    confirmPasswordInput.classList.add("error");
                    confirmPasswordInput.parentNode.querySelector("#password_confirmation_error")?.remove();

                    let errorMessage = document.createElement("p");
                    errorMessage.id = "password_confirmation_error";
                    errorMessage.classList.add("error-message");
                    errorMessage.textContent = Handlers.translate("form.registration.password.confirmation.error");
                    confirmPasswordInput.parentNode.insertBefore(errorMessage, confirmPasswordInput.nextSibling);
                    submitButton.disabled = true;
                } else {
                    confirmPasswordInput.classList.remove("error");
                    confirmPasswordInput.classList.add("success");
                    confirmPasswordInput.parentNode.querySelector("#password_confirmation_error")?.remove();
                    submitButton.disabled = false;
                }
            });
        }
    }
}
