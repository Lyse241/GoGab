/**
 * Badge de statut de commande, identique sur toutes les pages.
 * Jaune = en attente (à traiter), bleu = pris en charge, vert = livrée.
 */
const styles = {
    en_attente: 'bg-accent-100 text-accent-900 ring-accent-300',
    acceptee: 'bg-secondary-50 text-secondary-700 ring-secondary-200',
    en_livraison: 'bg-secondary-700 text-white ring-secondary-700',
    livree: 'bg-primary-50 text-primary-700 ring-primary-200',
};

export default function StatusBadge({ status, label, className = '' }) {
    return (
        <span
            className={`inline-block whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset ${
                styles[status] ?? 'bg-gray-100 text-gray-700 ring-gray-200'
            } ${className}`}
        >
            {label}
        </span>
    );
}
