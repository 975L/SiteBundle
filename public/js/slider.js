/*
 * (c) 2024: 975L <contact@975l.com>
 * (c) 2024: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    connect() {
        const sliderId = this.element.dataset.sliderId;
        if (sliderId) {
            this.slideIndex = 1;
            this.isPlaying = false;
            this.createLiveRegion(sliderId);
            this.preloadSliderImages(sliderId);
            this.initializeSlider(sliderId);
            this.resizeSlider(sliderId);
            this.setupAccessibility(sliderId);
            this.startAutoPlay(sliderId);
        }
    }

    disconnect() {
        if (this.autoPlayInterval) {
            clearInterval(this.autoPlayInterval);
        }
    }

    // Create ARIA live region for announcements
    createLiveRegion(sliderId) {
        const carousel = document.querySelector(`#${sliderId}`);
        let liveRegion = carousel.querySelector(".slider-liveregion");

        if (!liveRegion) {
            liveRegion = document.createElement("div");
            liveRegion.setAttribute("aria-live", "polite");
            liveRegion.setAttribute("aria-atomic", "true");
            liveRegion.className = "slider-liveregion visuallyhidden";
            carousel.appendChild(liveRegion);
        }
    }

    // Announce slide change to screen readers
    announceSlide(sliderId, current, total) {
        const liveRegion = document.querySelector(`#${sliderId} .slider-liveregion`);
        if (liveRegion) {
            liveRegion.textContent = `Item ${current} of ${total}`;
        }
    }

    // preloadSliderImages
    preloadSliderImages(sliderId) {
        const slides = document.querySelectorAll(`#${sliderId} .slider-item img`);

        slides.forEach((img, index) => {
            if (index === 0) {
                img.loading = "eager";
            } else {
                const preloadImg = new Image();
                preloadImg.src = img.src;
                preloadImg.onload = () => {
                    img.loading = "eager";
                    img.classList.add("preloaded");
                };
            }
        });
    }

    // setupAccessibility
    setupAccessibility(sliderId) {
        const carousel = document.querySelector(`#${sliderId}`);

        // Pause on mouse hover
        carousel.addEventListener("mouseenter", () => this.suspendAnimation());
        carousel.addEventListener("mouseleave", () => this.resumeAnimation(sliderId));

        // Pause on keyboard focus
        carousel.addEventListener("focusin", (e) => {
            if (!e.target.classList.contains("slider-item")) {
                this.suspendAnimation();
            }
        });
        carousel.addEventListener("focusout", (e) => {
            if (!e.target.classList.contains("slider-item")) {
                this.resumeAnimation(sliderId);
            }
        });

        // Play/Pause button
        const playPauseBtn = carousel.querySelector(".slider-play-pause");
        if (playPauseBtn) {
            playPauseBtn.addEventListener("click", () => this.togglePlayPause(sliderId, playPauseBtn));
        }
    }

    suspendAnimation() {
        if (this.autoPlayInterval) {
            clearInterval(this.autoPlayInterval);
            this.autoPlayInterval = null;
        }
    }

    resumeAnimation(sliderId) {
        if (this.isPlaying && !this.autoPlayInterval) {
            this.startAutoPlay(sliderId);
        }
    }

    togglePlayPause(sliderId, button) {
        const action = button.getAttribute("data-action");

        if (action === "stop") {
            this.suspendAnimation();
            this.isPlaying = false;
            button.setAttribute("data-action", "start");
            button.setAttribute("aria-label", button.getAttribute("aria-label").replace("Stop", "Start").replace("Arrêter", "Démarrer").replace("Detener", "Iniciar"));
            button.innerHTML = '<span aria-hidden="true">▶</span>';
        } else {
            this.isPlaying = true;
            this.startAutoPlay(sliderId);
            button.setAttribute("data-action", "stop");
            button.setAttribute("aria-label", button.getAttribute("aria-label").replace("Start", "Stop").replace("Démarrer", "Arrêter").replace("Iniciar", "Detener"));
            button.innerHTML = '<span aria-hidden="true">⏸</span>';
        }
    }

    // initializeSlider
    initializeSlider(sliderId) {
        const prevBtn = document.querySelector(`#${sliderId} .slider-prev`);
        const nextBtn = document.querySelector(`#${sliderId} .slider-next`);

        if (!prevBtn || !nextBtn) {
            return;
        }

        // Display the first slide
        this.displaySlide(sliderId, this.slideIndex, "none");

        // Previous slide
        prevBtn.addEventListener("click", () => {
            this.displaySlide(sliderId, --this.slideIndex, "prev");
        });

        // Next slide
        nextBtn.addEventListener("click", () => {
            this.displaySlide(sliderId, ++this.slideIndex, "next");
        });

        // Click on slide to go next
        const slides = document.querySelectorAll(`#${sliderId} .slider-item`);
        slides.forEach((slide) => {
            slide.addEventListener("click", () => {
                this.displaySlide(sliderId, ++this.slideIndex, "next");
            });
        });

        // Navigation dots
        const dots = document.querySelectorAll(`#${sliderId} .slider-dot`);
        dots.forEach((dot) => {
            dot.addEventListener("click", () => {
                const targetSlide = parseInt(dot.getAttribute("data-slide"), 10) + 1;
                const direction = targetSlide > this.slideIndex ? "next" : "prev";
                this.displaySlide(sliderId, targetSlide, direction);
            });
        });
    }

    // resizeSlider
    resizeSlider(sliderId) {
        const slider = document.querySelector(`#${sliderId}`);
        const slides = document.querySelectorAll(`#${sliderId} .slider-item`);

        if (slides.length === 0) {
            return;
        }

        // Gets img height and width to set slider height
        slides.forEach((slide) => {
            const img = slide.querySelector("img");
            if (img && img.clientHeight > slider.clientHeight) {
                slider.style.height = `${img.clientHeight}px`;
            }
        });

        // Recalculates height in case of resizing the window, waits that resize is finished to avoid multiple calculations
        let resizeTimeout = 1000;
        window.addEventListener("resize", () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                const slide = Array.from(slides).find((slide) => slide.style.display === "block");
                const img = slide.querySelector("img");
                if (img) {
                    if (img.clientHeight > slider.clientHeight) {
                        slider.style.height = `${img.clientHeight}px`;
                    } else {
                        slider.style.height = "";
                    }
                }
            }, resizeTimeout);
        });
    }

    // Display slide with ARIA support
    displaySlide(sliderId, number, direction = "next", announceChange = true) {
        const slides = document.querySelectorAll(`#${sliderId} .slider-item`);
        const dots = document.querySelectorAll(`#${sliderId} .slider-dot`);

        if (slides.length === 0) {
            return;
        }

        // Calculate correct index
        const index = this.calculateIndex(number, slides.length);

        // Find current active slide
        const currentSlide = Array.from(slides).find((slide) => slide.style.display === "block");
        const newSlide = slides[index - 1];

        // Remove animation classes
        slides.forEach((slide) => {
            slide.classList.remove("slide-in-right", "slide-in-left", "slide-out-right", "slide-out-left");
        });

        // Manage ARIA attributes
        slides.forEach((slide, idx) => {
            if (idx === index - 1) {
                slide.removeAttribute("aria-hidden");
            } else {
                slide.setAttribute("aria-hidden", "true");
            }
        });

        // Add animations if not initial display
        if (currentSlide && currentSlide !== newSlide && direction !== "none") {
            if (direction === "next") {
                currentSlide.classList.add("slide-out-left");
                newSlide.classList.add("slide-in-right");
            } else {
                currentSlide.classList.add("slide-out-right");
                newSlide.classList.add("slide-in-left");
            }

            setTimeout(() => {
                currentSlide.style.display = "none";
            }, 500);
        } else if (currentSlide && currentSlide !== newSlide) {
            currentSlide.style.display = "none";
        }

        // Update dots
        dots.forEach((dot, idx) => {
            if (idx === index - 1) {
                dot.classList.add("current", "active");
                dot.setAttribute("aria-label", dot.getAttribute("aria-label").replace(/\(.*?\)/, "") + " (current)");
            } else {
                dot.classList.remove("current", "active");
                dot.setAttribute("aria-label", dot.getAttribute("aria-label").replace(/\s*\(.*?\)/, ""));
            }
        });

        // Display new slide
        newSlide.style.display = "block";

        // Announce change
        if (announceChange && direction !== "none") {
            this.announceSlide(sliderId, index, slides.length);
        }

        // Update state
        this.slideIndex = index;
    }

    // Helper method to calculate valid index
    calculateIndex(number, length) {
        if (number > length) return 1;
        if (number < 1) return length;
        return number;
    }

    // Auto-play
    startAutoPlay(sliderId) {
        const duration = parseInt(this.element.dataset.sliderDuration, 10);

        if (!duration || duration <= 0) {
            return;
        }

        this.isPlaying = true;

        this.autoPlayInterval = setInterval(() => {
            this.slideIndex++;
            this.displaySlide(sliderId, this.slideIndex, "next", true);
        }, duration);
    }
}
