/*
 * (c) 2024: 975L <contact@975l.com>
 * (c) 2024: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import translationsEn from "./translations.en.js";
import translationsFr from "./translations.fr.js";
import translationsEs from "./translations.es.js";

export default {
    translations: {
        "en": translationsEn,
        "fr": translationsFr,
        "es": translationsEs
    },

    // Gets the language from the HTML document or browser
    getLanguage() {
        // Gets the language from the HTML document
        const langAttribute = document.documentElement.getAttribute("lang") || document.body.getAttribute("data-language");
        if (langAttribute) {
            return langAttribute.substring(0, 2).toLowerCase();
        }

        // Or uses the browser language
        const browserLang = navigator.language || navigator.userLanguage;
        return browserLang ? browserLang.substring(0, 2).toLowerCase() : "en";
    },

    // Translates messages
    translate(key) {
        if (typeof key !== "string") {
            return "";
        }

        const language = this.getLanguage();
        const translations = this.translations[language] || this.translations["en"];

        if (!translations) {
            return key;
        }

        return translations[key] || key;
    },
};