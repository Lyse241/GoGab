import {
    Bike,
    Building2,
    ClipboardList,
    History,
    House,
    LayoutDashboard,
    MapPin,
    Megaphone,
    Package,
    ReceiptText,
    ShieldAlert,
    ShoppingBag,
    Siren,
    Store,
    Tags,
    UserCheck,
    UserRound,
    Users,
} from 'lucide-react';

/**
 * Menus de l'espace connecté (DashboardLayout), par rôle.
 *
 * - route : nom de route Laravel (Ziggy). Un lien dont la route n'existe pas encore
 *   est masqué : le menu se complète tout seul au fil des prompts.
 * - active : motifs de routes qui surlignent le lien (défaut : la route elle-même)
 * - badge : clé d'un compteur de la prop partagée `badges` (ex. comptes en attente)
 * Sur mobile, la barre du bas affiche les 4 premiers liens + « Plus » s'il y en a davantage.
 */
export const navigation = {
    admin: [
        { route: 'admin.dashboard', label: 'Tableau de bord', icon: LayoutDashboard },
        {
            route: 'admin.accounts.index',
            label: 'Comptes à valider',
            shortLabel: 'À valider',
            icon: UserCheck,
            active: ['admin.accounts.*'],
            badge: 'pending_accounts',
        },
        { route: 'admin.clients.index', label: 'Clients', icon: Users, active: ['admin.clients.*'] },
        { route: 'admin.deliveries.index', label: 'Livreurs', icon: Bike, active: ['admin.deliveries.*'] },
        { route: 'admin.businesses.index', label: 'Entreprises', icon: Building2, active: ['admin.businesses.*'] },
        {
            route: 'admin.stores.index',
            label: 'Boutiques',
            icon: Store,
            active: ['admin.stores.*', 'admin.products.*'],
        },
        { route: 'admin.categories.index', label: 'Catégories', icon: Tags, active: ['admin.categories.*'] },
        { route: 'admin.neighborhoods.index', label: 'Quartiers', icon: MapPin, active: ['admin.neighborhoods.*'] },
        { route: 'admin.orders.index', label: 'Commandes', icon: ReceiptText, active: ['admin.orders.*'] },
        { route: 'admin.reports.index', label: 'Signalements', icon: Siren, active: ['admin.reports.*'], badge: 'open_reports' },
        { route: 'admin.moderation.index', label: 'Modération', icon: ShieldAlert, active: ['admin.moderation.*'] },
        { route: 'profile.edit', label: 'Mon profil', icon: UserRound },
    ],
    business: [
        { route: 'business.dashboard', label: 'Tableau de bord', icon: LayoutDashboard },
        { route: 'business.orders.index', label: 'Commandes', icon: ReceiptText, active: ['business.orders.*'], badge: 'new_orders' },
        { route: 'business.products.index', label: 'Produits', icon: Package, active: ['business.products.*'] },
        { route: 'business.store.edit', label: 'Mon commerce', shortLabel: 'Commerce', icon: Store, active: ['business.store.*'] },
        { route: 'profile.edit', label: 'Mon profil', icon: UserRound },
    ],
    // Espace livreur (Mobile-First) : 5 onglets dans la barre du bas, sans « Plus ».
    delivery: [
        { route: 'delivery.dashboard', label: 'Accueil', icon: House },
        { route: 'delivery.offers', label: 'Offres', icon: Megaphone, badge: 'offers' },
        { route: 'delivery.current', label: 'En cours', icon: Bike, badge: 'active_orders' },
        { route: 'delivery.history', label: 'Historique', icon: History },
        { route: 'delivery.profile', label: 'Profil', icon: UserRound, active: ['delivery.profile', 'delivery.profile.*', 'profile.edit'] },
    ],
    client: [
        { route: 'home', label: 'Accueil', icon: House },
        { route: 'client.orders.index', label: 'Commandes', icon: ClipboardList, active: ['client.orders.*', 'orders.show'] },
        { route: 'cart', label: 'Panier', icon: ShoppingBag },
        { route: 'profile.edit', label: 'Mon profil', icon: UserRound },
    ],
};

/**
 * Liens disponibles pour un rôle (routes existantes uniquement).
 */
export function linksFor(role) {
    return (navigation[role] ?? navigation.client).filter((link) => route().has(link.route));
}

export function isActive(link) {
    return (link.active ?? [link.route]).some((pattern) => route().current(pattern));
}
