/*
 * (c) 2024: 975L <contact@975l.com>
 * (c) 2024: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

// One file per locale used to be imported separately, costing three requests (and three modulepreloads) for the handful of strings below - see Handlers.translate()
export default {
    "en": {
        "form.registration.password.error": "The password must be at least 8 characters long, including one uppercase letter, one lowercase letter, one number, and one special character.",
        "form.registration.password.confirmation.error": "The passwords do not match."
    },
    "fr": {
        "form.registration.password.error": "Le mot de passe doit comporter au moins 8 caractères, dont une majuscule, une minuscule, un chiffre et un caractère spécial.",
        "form.registration.password.confirmation.error": "Les mots de passe ne correspondent pas."
    },
    "es": {
        "form.registration.password.error": "La contraseña debe tener al menos 8 caracteres, incluyendo una mayúscula, una minúscula, un número y un carácter especial.",
        "form.registration.password.confirmation.error": "Las contraseñas no coinciden."
    }
};
