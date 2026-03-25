/**
 * Menu Controller
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2026 975L <contact@975l.com>
 */
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["items"];

    connect() {
        // Close menu when clicking outside
        this.boundHandleClickOutside = this.handleClickOutside.bind(this);
        document.addEventListener("click", this.boundHandleClickOutside);

        // Close menu on escape key
        this.boundHandleEscape = this.handleEscape.bind(this);
        document.addEventListener("keydown", this.boundHandleEscape);

        // Close menu when clicking on a menu link
        this.boundHandleMenuLinkClick = this.handleMenuLinkClick.bind(this);
        this.menuLinkElements = this.element.querySelectorAll(".menu-link");
        this.menuLinkElements.forEach(link => {
            link.addEventListener("click", this.boundHandleMenuLinkClick);
        });
    }

    disconnect() {
        document.removeEventListener("click", this.boundHandleClickOutside);
        document.removeEventListener("keydown", this.boundHandleEscape);
        // Remove menu link click listeners
        if (this.menuLinkElements) {
            this.menuLinkElements.forEach(link => {
                link.removeEventListener("click", this.boundHandleMenuLinkClick);
            });
        }
    }

    toggle(event) {
        event.stopPropagation();
        const button = event.currentTarget;
        const isExpanded = button.getAttribute("aria-expanded") === "true";

        if (isExpanded) {
            this.close(button);
        } else {
            this.open(button);
        }
    }

    open(button) {
        this.itemsTarget.classList.add("open");
        button.setAttribute("aria-expanded", "true");
        button.classList.add("active");
    }

    close(button) {
        this.itemsTarget.classList.remove("open");
        button.setAttribute("aria-expanded", "false");
        button.classList.remove("active");
    }

    handleClickOutside(event) {
        if (!this.element.contains(event.target)) {
            const button = this.element.querySelector(".menu-toggle");
            if (button.getAttribute("aria-expanded") === "true") {
                this.close(button);
            }
        }
    }

    handleEscape(event) {
        if (event.key === "Escape") {
            const button = this.element.querySelector(".menu-toggle");
            if (button.getAttribute("aria-expanded") === "true") {
                this.close(button);
            }
        }
    }

    handleMenuLinkClick(event) {
        const button = this.element.querySelector(".menu-toggle");
        if (button.getAttribute("aria-expanded") === "true") {
            this.close(button);
        }
    }
}