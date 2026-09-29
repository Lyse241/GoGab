import ApplicationLogo from '@/Components/ApplicationLogo';
import CartButton from '@/Components/Layout/CartButton';
import NeighborhoodSelector from '@/Components/Layout/NeighborhoodSelector';
import NotificationBell from '@/Components/Layout/NotificationBell';
import PublicFooter from '@/Components/Layout/PublicFooter';
import SearchBar from '@/Components/Layout/SearchBar';
import UserMenu from '@/Components/Layout/UserMenu';
import WarningNotice from '@/Components/Layout/WarningNotice';
import Button from '@/Components/UI/Button';
import { useNeighborhood } from '@/Contexts/NeighborhoodContext';
import { cn } from '@/utils/cn';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

/**
 * Layout des pages publiques (catalogue, panier, commande), visiteurs et connectés.
 *
 * Header sticky : logo, quartier de livraison, recherche, notifications, panier, compte.
 * Mobile : quartier et recherche passent sur une seconde ligne.
 *
 * - search=false : masque la recherche (ex. tunnel de commande)
 * - searchOnMobile=false : pas de seconde barre de recherche sur mobile (la page en a déjà une)
 * - hero : bandeau pleine largeur affiché entre le header et le contenu (page d'accueil)
 * - tone : fond de la page, "gray" (défaut) ou "white"
 * - cart / notifications : remplacent les emplacements par défaut (null pour les masquer)
 */
export default function PublicLayout({
    search = true,
    searchOnMobile = true,
    hero = null,
    tone = 'gray',
    cart = <CartButton />,
    notifications = <NotificationBell />,
    children,
}) {
    const { user } = usePage().props.auth;
    const { neighborhoodId, setNeighborhoodId } = useNeighborhood();
    const headerRef = useRef(null);

    // Hauteur du header sticky en variable CSS (--header-h) : les barres collantes des pages
    // (sections du menu…) se placent juste dessous, sur mobile comme sur desktop.
    useEffect(() => {
        const header = headerRef.current;
        if (!header || typeof ResizeObserver === 'undefined') {
            return undefined;
        }

        const update = () => document.documentElement.style.setProperty('--header-h', `${header.offsetHeight}px`);
        const observer = new ResizeObserver(update);
        observer.observe(header);
        update();

        return () => observer.disconnect();
    }, []);

    // Utilisateur connecté sans quartier choisi sur cet appareil : on part de son quartier.
    useEffect(() => {
        if (!neighborhoodId && user?.neighborhood_id) {
            setNeighborhoodId(user.neighborhood_id);
        }
    }, [neighborhoodId, user?.neighborhood_id, setNeighborhoodId]);

    return (
        <div className={cn('flex min-h-screen flex-col overflow-x-clip', tone === 'white' ? 'bg-white' : 'bg-gray-50')}>
            <header ref={headerRef} className="sticky top-0 z-30 border-b border-gray-100 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/85">
                <div className="mx-auto flex h-16 max-w-6xl items-center gap-2 px-4 sm:gap-4 sm:px-6">
                    <Link
                        href={route('home')}
                        className="shrink-0 rounded-lg text-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                        aria-label="Gogab, accueil"
                    >
                        <ApplicationLogo />
                    </Link>

                    <NeighborhoodSelector className="ml-1 hidden sm:inline-flex" />

                    {search && <SearchBar className="hidden flex-1 md:block md:max-w-md lg:max-w-lg" />}

                    <div className="ml-auto flex items-center gap-0.5 sm:gap-1">
                        {notifications}
                        {cart}
                        {user ? (
                            <UserMenu />
                        ) : (
                            <>
                                <Button href={route('login')} variant="ghost" size="sm">
                                    Connexion
                                </Button>
                                <Button href={route('register')} size="sm" className="hidden sm:inline-flex">
                                    S’inscrire
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                {/* Mobile : quartier (+ recherche) sous la barre principale */}
                <div className="mx-auto flex max-w-6xl flex-col gap-2 px-4 pb-3 sm:hidden">
                    <NeighborhoodSelector className="-ml-2 self-start" />
                    {search && searchOnMobile && <SearchBar />}
                </div>
                {search && searchOnMobile && (
                    <div className="mx-auto hidden max-w-6xl px-6 pb-3 sm:block md:hidden">
                        <SearchBar />
                    </div>
                )}
            </header>

            {hero}

            <main className="mx-auto w-full max-w-6xl flex-1 px-4 pb-12 sm:px-6">{children}</main>

            <WarningNotice />

            <PublicFooter />
        </div>
    );
}
