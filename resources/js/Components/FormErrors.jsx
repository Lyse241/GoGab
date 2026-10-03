/**
 * Résumé affiché en haut d'un formulaire quand la validation échoue
 * (les messages détaillés restent sous chaque champ).
 */
export default function FormErrors({ errors, className = '' }) {
    const count = Object.keys(errors).length;

    if (count === 0) {
        return null;
    }

    return (
        <div
            role="alert"
            className={`rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 ${className}`}
        >
            {count === 1
                ? 'Le formulaire contient une erreur : corrigez le champ indiqué en rouge.'
                : `Le formulaire contient ${count} erreurs : corrigez les champs indiqués en rouge.`}
        </div>
    );
}

/**
 * Place le curseur sur le premier champ en erreur, dans l'ordre de la page, une fois les messages
 * affichés. Appelé pour toute erreur de validation Inertia (app.jsx) ; peut aussi être passé en
 * onError d'un useForm ou après une validation côté navigateur.
 */
export function focusFirstError(errors = {}) {
    requestAnimationFrame(() => {
        const byKey = Object.keys(errors)
            .map((key) => document.getElementById(key) ?? document.getElementById(key.replaceAll('.', '-')))
            .filter(Boolean);
        const fields = [...byKey, ...document.querySelectorAll('[aria-invalid="true"]')].filter((field) => field.getClientRects().length > 0);

        // Le plus haut dans la page.
        const first = fields.sort((a, b) => (a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1))[0];
        if (!first) {
            return;
        }

        const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
        first.focus({ preventScroll: true });
        first.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' });
    });
}
