import ApplicationLogo from '@/Components/ApplicationLogo';
import CartButton from '@/Components/Layout/CartButton';
import NotificationBell from '@/Components/Layout/NotificationBell';
import UserMenu, { Avatar } from '@/Components/Layout/UserMenu';
import WarningNotice from '@/Components/Layout/WarningNotice';
import Modal from '@/Components/UI/Modal';
import { isActive, linksFor } from '@/Layouts/navigation';
import { cn } from '@/utils/cn';
import { Link, usePage } from '@inertiajs/react';
import { LogOut, Menu } from 'lucide-react';
import { useState } from 'react';

// Au-delà de 5 liens, la barre du bas en montre 4 et regroupe le reste derrière « Plus ».
const MOBILE_MAX = 5;

/**
 * Compteur d'un lien (ex. comptes en attente), lu dans la prop partagée `badges`.
 */
function CountBadge({ count, className }) {
    if (!count) {
        return null;
    }

    return (
        <span
            className={cn(
                'inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-accent px-1.5 text-[11px] font-bold leading-none text-secondary-900',
                className,
            )}
        >
            {count > 99 ? '99+' : count}
        </span>
    );
}

/**
 * Layout des espaces connectés (admin, entreprise, livreur, client).
 *
 * Desktop (lg) : barre latérale bleu profond avec le menu du rôle.
 * Mobile : barre du haut (logo, notifications, compte) + barre de navigation en bas
 * (4 liens + « Plus » quand le menu est long).
 *
 * - header : titre / contenu de l'en-tête de page (bandeau blanc sous la barre du haut)
 * - actions : boutons alignés à droite de l'en-tête (ex. « Nouveau produit »)
 */
export default function DashboardLayout({ header, actions, children }) {
    const { auth, badges = {} } = usePage().props;
    const { user, role, role_label } = auth;
    const links = linksFor(role);
    const countOf = (link) => (link.badge ? badges[link.badge] ?? 0 : 0);

    const overflow = links.length > MOBILE_MAX;
    const mobileLinks = overflow ? links.slice(0, MOBILE_MAX - 1) : links;
    const moreLinks = overflow ? links.slice(MOBILE_MAX - 1) : [];
    const moreActive = moreLinks.some(isActive);
    const moreCount = moreLinks.reduce((total, link) => total + countOf(link), 0);
    const [moreOpen, setMoreOpen] = useState(false);

    const bottomItemClasses = (active) =>
        cn(
            'flex h-16 w-full flex-col items-center justify-center gap-1 text-[11px] font-medium focus:outline-none focus-visible:bg-gray-50',
            active ? 'text-primary-700' : 'text-gray-500',
        );

    const bottomIcon = (Icon, active, count) => (
        <span className={cn('relative flex h-8 w-14 items-center justify-center rounded-full transition', active && 'bg-primary-50')}>
            <Icon className="h-5 w-5" aria-hidden="true" />
            <CountBadge count={count} className="absolute -top-1 right-1.5 ring-2 ring-white" />
        </span>
    );

    return (
        <div className="min-h-screen bg-gray-50">
            {/* Barre latérale (desktop) */}
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col bg-secondary lg:flex">
                <div className="flex h-16 items-center px-6">
                    <Link
                        href={route('dashboard')}
                        className="rounded-lg text-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                        aria-label="Gogab, mon espace"
                    >
                        <ApplicationLogo light />
                    </Link>
                </div>

                {role_label && (
                    <p className="px-6 pb-2 text-xs font-semibold uppercase tracking-wider text-secondary-300">
                        Espace {role_label.toLowerCase()}
                    </p>
                )}

                <nav aria-label="Menu principal" className="flex-1 space-y-1 overflow-y-auto px-3 py-2">
                    {links.map((link) => {
                        const active = isActive(link);
                        const Icon = link.icon;
                        const count = countOf(link);

                        return (
                            <Link
                                key={link.route}
                                href={route(link.route)}
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'group relative flex min-h-tap items-center gap-3 rounded-xl px-3 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-accent',
                                    active
                                        ? 'bg-white/10 text-white'
                                        : 'text-secondary-100 hover:bg-white/5 hover:text-white',
                                )}
                            >
                                {active && (
                                    <span className="absolute left-0 top-2 bottom-2 w-1 rounded-r-full bg-accent" aria-hidden="true" />
                                )}
                                <Icon
                                    className={cn('h-5 w-5', active ? 'text-accent' : 'text-secondary-300 group-hover:text-white')}
                                    aria-hidden="true"
                                />
                                <span className="flex-1">{link.label}</span>
                                <CountBadge count={count} />
                                {count > 0 && <span className="sr-only"> ({count} en attente)</span>}
                            </Link>
                        );
                    })}
                </nav>

                <div className="border-t border-white/10 p-3">
                    <div className="flex items-center gap-3 rounded-xl px-3 py-2">
                        <Avatar name={user.name} initials={user.initials} className="bg-primary-600" />
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-semibold text-white">{user.name}</p>
                            <p className="truncate text-xs text-secondary-300">{user.email}</p>
                        </div>
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-secondary-200 hover:bg-white/10 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-accent"
                            aria-label="Déconnexion"
                        >
                            <LogOut className="h-5 w-5" aria-hidden="true" />
                        </Link>
                    </div>
                </div>
            </aside>

            <div className="lg:pl-64">
                {/* Barre du haut */}
                <div className="sticky top-0 z-20 border-b border-gray-100 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/85">
                    <div className="mx-auto flex h-16 max-w-7xl items-center gap-2 px-4 sm:px-6 lg:px-8">
                        <Link
                            href={route('dashboard')}
                            className="rounded-lg text-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-primary lg:hidden"
                            aria-label="Gogab, mon espace"
                        >
                            <ApplicationLogo />
                        </Link>
                        <div className="ml-auto flex items-center gap-0.5 sm:gap-1">
                            <NotificationBell />
                            <CartButton />
                            <UserMenu showDashboard={false} />
                        </div>
                    </div>
                </div>

                {(header || actions) && (
                    <header className="border-b border-gray-100 bg-white">
                        <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-5 sm:px-6 lg:px-8">
                            <div className="min-w-0">{header}</div>
                            {actions && <div className="flex shrink-0 flex-wrap gap-2">{actions}</div>}
                        </div>
                    </header>
                )}

                {/* Marge basse : la barre de navigation mobile ne cache pas le contenu. */}
                <main className="pb-28 lg:pb-10">{children}</main>
                <WarningNotice />
            </div>

            {/* Barre de navigation du bas (mobile) */}
            {links.length > 0 && (
                <nav
                    aria-label="Menu principal"
                    className="fixed inset-x-0 bottom-0 z-30 border-t border-gray-100 bg-white pb-[env(safe-area-inset-bottom)] shadow-nav lg:hidden"
                >
                    <ul
                        className="grid"
                        style={{ gridTemplateColumns: `repeat(${mobileLinks.length + (overflow ? 1 : 0)}, minmax(0, 1fr))` }}
                    >
                        {mobileLinks.map((link) => {
                            const active = isActive(link);

                            return (
                                <li key={link.route}>
                                    <Link
                                        href={route(link.route)}
                                        aria-current={active ? 'page' : undefined}
                                        className={bottomItemClasses(active)}
                                    >
                                        {bottomIcon(link.icon, active, countOf(link))}
                                        <span className="max-w-full truncate px-1">{link.shortLabel ?? link.label}</span>
                                    </Link>
                                </li>
                            );
                        })}
                        {overflow && (
                            <li>
                                <button
                                    type="button"
                                    onClick={() => setMoreOpen(true)}
                                    aria-haspopup="dialog"
                                    className={bottomItemClasses(moreActive)}
                                >
                                    {bottomIcon(Menu, moreActive, moreCount)}
                                    <span>Plus</span>
                                </button>
                            </li>
                        )}
                    </ul>
                </nav>
            )}

            {overflow && (
                <Modal open={moreOpen} onClose={() => setMoreOpen(false)} title="Menu" size="sm">
                    <ul className="-mx-2 space-y-1 pb-2">
                        {moreLinks.map((link) => {
                            const active = isActive(link);
                            const Icon = link.icon;

                            return (
                                <li key={link.route}>
                                    <Link
                                        href={route(link.route)}
                                        onClick={() => setMoreOpen(false)}
                                        aria-current={active ? 'page' : undefined}
                                        className={cn(
                                            'flex min-h-tap items-center gap-3 rounded-xl px-3 text-sm font-medium',
                                            active ? 'bg-primary-50 text-primary-800' : 'text-gray-700 hover:bg-gray-50',
                                        )}
                                    >
                                        <Icon className="h-5 w-5 text-gray-500" aria-hidden="true" />
                                        <span className="flex-1">{link.label}</span>
                                        <CountBadge count={countOf(link)} />
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                </Modal>
            )}
        </div>
    );
}
