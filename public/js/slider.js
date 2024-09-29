import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    connect() {
        document.addEventListener("DOMContentLoaded", this.onDomContentLoaded.bind(this));
    }

    onDomContentLoaded() {
        const sliderId = this.element.dataset.sliderId;
        if (sliderId) {
            this.slider(sliderId);
        }
    }

    slider(slider) {
        let slideIndex = 1;

        const arrowLeft = document.querySelector(`#${slider} .arrow-left`);
        const arrowRight = document.querySelector(`#${slider} .arrow-right`);

        function displaySlide(slider, number) {
            const slides = document.querySelectorAll(`#${slider} .slider-img`);
            const dots = document.querySelectorAll(`#${slider} .slider-dot`);
            let index = number;

            // Suppress slides ands dots classes
            slides.forEach((slide) => (slide.style.display = "none"));
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
                displaySlide(slider, (slideIndex -= 1));
            });
            // EventListener for next slide, >
            arrowRight.addEventListener("click", () => {
                displaySlide(slider, (slideIndex += 1));
            });
            // EventListener for next slide, click on slide
            const slides = document.querySelectorAll(`#${slider} .slider-img`);
            slides.forEach((slide) => {
                slide.addEventListener("click", () => {
                    displaySlide(slider, (slideIndex += 1));
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
}