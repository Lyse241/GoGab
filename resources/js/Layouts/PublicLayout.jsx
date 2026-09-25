import ApplicationLogo from '@/Components/ApplicationLogo';
import CartButton from '@/Components/CartButton';
import FlashMessage from '@/Components/FlashMessage';
import { Link, usePage } from '@inertiajs/react';

const linkClass =
    'rounded-md px-2 py-1 text-sm font-medium text-secondary-100 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-accent';

/**
 * Layout des pages publiques (catalogue), pour visiteurs et utilisateurs connectés.
 */
export default function PublicLayout({ children }) {
    const { user, role } = usePage().props.auth;

    return (
        <div className="min-h-screen bg-gray-50">
            <header className="sticky top-0 z-10 bg-secondary shadow-md">
                <div className="mx-auto flex h-14 max-w-6xl items-center justify-between px-4 sm:px-6">
                    <Link href={route('home')} className="text-2xl" aria-label="Gogab, accueil">
                        <ApplicationLogo light />
                    </Link>

                    <nav className="flex items-center gap-1 sm:gap-3">
                        {user ? (
                            <>
                                {role !== 'client' && (
                                    <Link href={route('dashboard')} className={linkClass}>
                                        Mon espace
                                    </Link>
                                )}
                                <Link href={route('profile.edit')} className={linkClass}>
                                    Mon compte
                                </Link>
                                {/* Sur mobile, la déconnexion se fait depuis « Mon compte » (manque de place). */}
                                <Link
                                    href={route('logout')}
                                    method="post"
                                    as="button"
                                    className={`hidden sm:inline-block ${linkClass}`}
                                >
                                    Déconnexion
                                </Link>
                            </>
                        ) : (
                            <>
                                <Link href={route('login')} className={linkClass}>
                                    Connexion
                                </Link>
                                <Link
                                    href={route('register')}
                                    className="rounded-full bg-accent px-3 py-1.5 text-sm font-semibold text-secondary-900 hover:bg-accent-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-white"
                                >
                                    S'inscrire
                                </Link>
                            </>
                        )}
                        <CartButton />
                    </nav>
                </div>
            </header>

            <main className="mx-auto max-w-6xl px-4 pb-12 sm:px-6">
                <FlashMessage className="pt-4" />
                {children}
            </main>
        </div>
    );
}
