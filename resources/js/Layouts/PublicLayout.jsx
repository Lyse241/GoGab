import CartButton from '@/Components/CartButton';
import FlashMessage from '@/Components/FlashMessage';
import { Link, usePage } from '@inertiajs/react';

const linkClass =
    'rounded-md px-2 py-1 text-sm font-medium text-gray-600 hover:text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500';

/**
 * Layout des pages publiques (catalogue), pour visiteurs et utilisateurs connectés.
 */
export default function PublicLayout({ children }) {
    const { user, role } = usePage().props.auth;

    return (
        <div className="min-h-screen bg-gray-50">
            <header className="sticky top-0 z-10 border-b border-gray-200 bg-white/95 backdrop-blur">
                <div className="mx-auto flex h-14 max-w-6xl items-center justify-between px-4 sm:px-6">
                    <Link
                        href={route('home')}
                        className="text-xl font-extrabold tracking-tight text-emerald-600"
                    >
                        Gogab
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
                                <Link
                                    href={route('logout')}
                                    method="post"
                                    as="button"
                                    className={linkClass}
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
                                    className="rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2"
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
