const amountFormatter = new Intl.NumberFormat('fr-FR', {
    maximumFractionDigits: 0,
});

/**
 * Formate un montant en FCFA : 12500 ou "12500.00" → "12 500 FCFA".
 * Espaces insécables (le montant ne se coupe jamais en fin de ligne) ;
 * l'espace fine d'Intl est remplacée car Poppins ne la dessine pas.
 */
export function formatFCFA(value) {
    const number = Number(value);
    const amount = amountFormatter
        .format(Number.isFinite(number) ? number : 0)
        .replace(/[   ]/g, ' ');

    return `${amount} FCFA`;
}

const relativeFormatter = new Intl.RelativeTimeFormat('fr', { numeric: 'auto' });

/**
 * Date relative : "à l’instant", "il y a 5 minutes", "hier", puis la date (12/10/2026).
 */
export function formatRelativeTime(date, now = new Date()) {
    const value = date instanceof Date ? date : new Date(date);
    const seconds = Math.round((value.getTime() - now.getTime()) / 1000);
    const abs = Math.abs(seconds);

    if (abs < 45) {
        return 'à l’instant';
    }
    if (abs < 3600) {
        return relativeFormatter.format(Math.round(seconds / 60), 'minute');
    }
    if (abs < 86400) {
        return relativeFormatter.format(Math.round(seconds / 3600), 'hour');
    }
    if (abs < 7 * 86400) {
        return relativeFormatter.format(Math.round(seconds / 86400), 'day');
    }

    return value.toLocaleDateString('fr-FR');
}

/**
 * Taille de fichier lisible : 1536 → "1,5 Ko".
 */
export function formatFileSize(bytes) {
    if (bytes < 1024) {
        return `${bytes} o`;
    }

    const units = ['Ko', 'Mo', 'Go'];
    let size = bytes / 1024;
    let unit = 0;

    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit += 1;
    }

    return `${size.toLocaleString('fr-FR', { maximumFractionDigits: 1 })} ${units[unit]}`;
}

/**
 * URL d'une image : les URLs complètes (placeholders) sont gardées telles quelles,
 * les chemins locaux (fichiers envoyés) passent par /storage.
 */
export function imageUrl(path) {
    if (!path) {
        return null;
    }

    return /^(https?:|blob:|data:)/.test(path) ? path : `/storage/${path}`;
}
