/**
 * Slider Controller
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2024 975L <contact@975l.com>
 */
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    connect() {
        // Initialize directly when the controller connects
        const sliderId = this.element.dataset.sliderId;
        if (sliderId) {
            this.initializeSlider(sliderId);
        }
    }

    initializeSlider(sliderId) {
        // Define slideIndex in the controller instance for better scope
        this.slideIndex = 1;

        const arrowLeft = document.querySelector(`#${sliderId} .arrow-left`);
        const arrowRight = document.querySelector(`#${sliderId} .arrow-right`);

        // Check if arrows exist
        if (!arrowLeft || !arrowRight) return;

        // Define displaySlide as a method bound to the instance
        const displaySlide = (id, number) => {
            const slides = document.querySelectorAll(`#${id} .slider-item`);
            const dots = document.querySelectorAll(`#${id} .slider-dot`);

            if (slides.length === 0 || dots.length === 0) return;

            let index = number;

            // Handle index boundaries
            if (number > slides.length) {
                index = 1;
            }
            if (number < 1) {
                index = slides.length;
            }

            // Hide all slides and deactivate all dots
            slides.forEach(slide => slide.style.display = "none");
            dots.forEach(dot => dot.classList.remove("active"));

            // Display the active slide and activate the corresponding dot
            slides[index - 1].style.display = "block";
            dots[index - 1].classList.add("active");

            this.slideIndex = index;
        };

        // Display the first slide
        displaySlide(sliderId, this.slideIndex);

        // EventListener for previous slide, <
        arrowLeft.addEventListener("click", () => {
            displaySlide(sliderId, --this.slideIndex);
        });

        // EventListener for next slide, >
        arrowRight.addEventListener("click", () => {
            displaySlide(sliderId, ++this.slideIndex);
        });

        // EventListener for next slide, click on slide
        const slidesImg = document.querySelectorAll(`#${sliderId} .slider-img`);
        slidesImg.forEach((slideImg) => {
            slideImg.addEventListener("click", () => {
                displaySlide(sliderId, ++this.slideIndex);
            });
        });

        // EventListener for click on dot
        const dots = document.querySelectorAll(`#${sliderId} .slider-dot`);
        dots.forEach((dot) => {
            dot.addEventListener("click", () => {
                displaySlide(sliderId, Number(dot.getAttribute("data-number")));
            });
        });
    }
}