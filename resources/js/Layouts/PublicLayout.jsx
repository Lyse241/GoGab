import ApplicationLogo from '@/Components/ApplicationLogo';
import CartButton from '@/Components/Layout/CartButton';
import NeighborhoodSelector from '@/Components/Layout/NeighborhoodSelector';
import NotificationBell from '@/Components/Layout/NotificationBell';
import SearchBar from '@/Components/Layout/SearchBar';
import UserMenu from '@/Components/Layout/UserMenu';
import WarningNotice from '@/Components/Layout/WarningNotice';
import Button from '@/Components/UI/Button';
import { Link, usePage } from '@inertiajs/react';
import { MapPin, Wallet } from 'lucide-react';

/**
 * Layout des pages publiques (catalogue, panier, commande), visiteurs et connectés.
 *
 * Header sticky : logo, quartier de livraison, recherche, notifications, panier, compte.
 * Mobile : la recherche passe sur une seconde ligne.
 *
 * - search=false : masque la recherche (ex. tunnel de commande)
 * - cart / notifications : remplacent les emplacements par défaut (null pour les masquer)
 */
export default function PublicLayout({
    search = true,
    cart = <CartButton />,
    notifications = <NotificationBell />,
    children,
}) {
    const { user } = usePage().props.auth;

    return (
        <div className="flex min-h-screen flex-col bg-gray-50">
            <header className="sticky top-0 z-30 border-b border-gray-100 bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/85">
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

                {/* Mobile : quartier + recherche sous la barre principale */}
                <div className="mx-auto flex max-w-6xl flex-col gap-2 px-4 pb-3 sm:hidden">
                    <NeighborhoodSelector className="-ml-2 self-start" />
                    {search && <SearchBar />}
                </div>
                {search && (
                    <div className="mx-auto hidden max-w-6xl px-6 pb-3 sm:block md:hidden">
                        <SearchBar />
                    </div>
                )}
            </header>

            <main className="mx-auto w-full max-w-6xl flex-1 px-4 pb-12 sm:px-6">{children}</main>

            <WarningNotice />

            <footer className="bg-secondary text-secondary-100">
                <div className="mx-auto grid max-w-6xl gap-8 px-4 py-10 sm:grid-cols-3 sm:px-6">
                    <div>
                        <ApplicationLogo light className="text-2xl" />
                        <p className="mt-3 max-w-xs text-sm text-secondary-200">
                            Vos commerces de quartier livrés chez vous, partout à Libreville.
                        </p>
                    </div>

                    <nav aria-label="Liens utiles">
                        <h2 className="text-sm font-semibold text-white">Gogab</h2>
                        <ul className="mt-3 space-y-2 text-sm">
                            <li>
                                <Link href={route('home')} className="hover:text-white hover:underline">
                                    Commerces
                                </Link>
                            </li>
                            <li>
                                <Link href={route('cart')} className="hover:text-white hover:underline">
                                    Mon panier
                                </Link>
                            </li>
                            <li>
                                {user ? (
                                    <Link href={route('profile.edit')} className="hover:text-white hover:underline">
                                        Mon compte
                                    </Link>
                                ) : (
                                    <Link href={route('register')} className="hover:text-white hover:underline">
                                        Créer un compte
                                    </Link>
                                )}
                            </li>
                        </ul>
                    </nav>

                    <div>
                        <h2 className="text-sm font-semibold text-white">Livraison</h2>
                        <ul className="mt-3 space-y-2 text-sm">
                            <li className="flex items-center gap-2">
                                <MapPin className="h-4 w-4 text-accent" aria-hidden="true" />
                                Libreville, Akanda et Owendo
                            </li>
                            <li className="flex items-center gap-2">
                                <Wallet className="h-4 w-4 text-accent" aria-hidden="true" />
                                Paiement Airtel Money, Moov Money ou espèces
                            </li>
                        </ul>
                    </div>
                </div>
                <div className="border-t border-white/10">
                    <p className="mx-auto max-w-6xl px-4 py-4 text-xs text-secondary-300 sm:px-6">
                        © {new Date().getFullYear()} Gogab · Libreville, Gabon
                    </p>
                </div>
            </footer>
        </div>
    );
}
