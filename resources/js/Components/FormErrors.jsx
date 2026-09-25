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
 * Place le curseur sur le premier champ en erreur (à passer en onError d'un useForm).
 */
export function focusFirstError(errors) {
    const first = Object.keys(errors)[0];

    if (first) {
        const field = document.getElementById(first);
        field?.focus();
        field?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}
