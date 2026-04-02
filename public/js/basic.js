/**
 * Basic Controller
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2024 975L <contact@975l.com>
 */
import { Controller } from "@hotwired/stimulus";
import Handlers from "./handlers.js";

export default class extends Controller {
    connect() {
        document.addEventListener("DOMContentLoaded", this.onDomContentLoaded.bind(this));
        window.addEventListener("scroll", () => {
            this.backTopButton();
            this.pullDownButton();
        });
    }

    onDomContentLoaded() {
        this.htmlBoilerPlate();
        this.externalLinks();
        this.togglePasswordVisibility();
        this.validatePasswordFormat();
        this.validatePassword();
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
            if (!passwordInput) return;

            const parent = passwordInput.parentNode;
            // mark parent so CSS can add input padding and position the toggle
            if (!parent.classList.contains('has-toggle')) {
                parent.classList.add('has-toggle');
                if (getComputedStyle(parent).position === 'static') {
                    parent.style.position = 'relative';
                }

                // Defines toggle
                let toggle = document.createElement('span');
                toggle.classList.add('toggle-password');

                // Adds image
                let image = document.createElement('img');
                image.src = '/bundles/c975lsite/images/eye.svg';
                toggle.appendChild(image);

                // Append toggle to parent so it stays positioned even if an error node is inserted
                parent.appendChild(toggle);

                // Handles the click on the toggle
                toggle.addEventListener('click', function () {
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        passwordInput.setAttribute('autocomplete', 'off');
                        image.src = '/bundles/c975lsite/images/eye-slash.svg';
                    } else {
                        passwordInput.type = 'password';
                        passwordInput.setAttribute('autocomplete', 'current-password');
                        image.src = '/bundles/c975lsite/images/eye.svg';
                    }
                });
            }
        });
    }

    // Checks the password format before submitting the form
    validatePasswordFormat() {
        let passwordInput = document.getElementById("registration_form_plainPassword");
        let pattern = /^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}$/;
        let submitButton = document.querySelector("input[type='submit']") ? document.querySelector("input[type='submit']") : document.querySelector("button[type='submit']");

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
        let submitButton = document.querySelector("input[type='submit']") ? document.querySelector("input[type='submit']") : document.querySelector("button[type='submit']");

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
