/**
 * Après une erreur de validation : place le focus sur le premier champ en erreur (ordre de la
 * page), une fois le rendu des messages fait. À passer en `onError` d'un envoi useForm.
 *
 * @param {Record<string, string>} errors erreurs renvoyées par le serveur
 */
export function focusFirstError(errors = {}) {
    requestAnimationFrame(() => {
        const invalid = document.querySelector('[aria-invalid="true"]');
        const fallback = Object.keys(errors)
            .map((key) => document.getElementById(key))
            .find(Boolean);

        (invalid ?? fallback)?.focus();
    });
}
