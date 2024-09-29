import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    connect() {
        this.animateOnScroll = this.animateOnScroll.bind(this);
        window.addEventListener("scroll", this.animateOnScroll);
        this.animateOnScroll();
    }

    disconnect() {
        window.removeEventListener("scroll", this.animateOnScroll);
    }

    // Checks if element is in viewport
    isElementInViewport(element, offset) {
        if (null !== element) {
            const rect = element.getBoundingClientRect();
            return (
                rect.top < (window.innerHeight || document.documentElement.clientHeight) - offset &&
                rect.bottom >= 0
            );
        }
        return false;
    }

    // Animates on scroll
    animateOnScroll() {
        var elements = document.querySelectorAll(".scroll");
        elements.forEach((element) => {
            if (this.isElementInViewport(element, 200)) {
                const animationClass = element.getAttribute("data-animation");
                element.classList.remove("hidden");
                element.classList.add(animationClass);
            }
        })
    }
}