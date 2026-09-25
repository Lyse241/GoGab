const priceFormatter = new Intl.NumberFormat('fr-FR', {
    maximumFractionDigits: 0,
});

/**
 * Formate un prix en FCFA : 4500 ou "4500.00" → "4 500 FCFA".
 */
export function formatPrice(value) {
    return `${priceFormatter.format(Number(value))} FCFA`;
}

/**
 * URL d'une image : les URLs complètes (placeholders) sont gardées telles quelles,
 * les chemins locaux (fichiers envoyés plus tard) passent par /storage.
 */
export function imageUrl(path) {
    if (!path) {
        return null;
    }

    return /^https?:\/\//.test(path) ? path : `/storage/${path}`;
}
