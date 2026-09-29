import ApplicationLogo from '@/Components/ApplicationLogo';
import { cn } from '@/utils/cn';
import { Link, usePage } from '@inertiajs/react';
import { ArrowLeft, LogOut } from 'lucide-react';

const widths = {
    sm: 'sm:max-w-md',
    md: 'sm:max-w-lg',
    lg: 'sm:max-w-2xl',
    xl: 'sm:max-w-4xl',
};

/**
 * Layout des pages hors espace connecté : connexion, inscription, mot de passe oublié,
 * état d'un compte non validé.
 *
 * Mobile : page blanche plein écran. À partir de sm : carte centrée sur fond gris.
 *
 * - title / subtitle : en-tête de la carte
 * - width : sm (défaut) | md | lg | xl
 * - footer : contenu sous la carte (ex. « Pas encore de compte ? »)
 */
export default function GuestLayout({ title, subtitle, width = 'sm', footer, children }) {
    const { auth } = usePage().props;

    return (
        <div className="flex min-h-screen flex-col bg-white sm:bg-gray-50">
            <header className="border-b border-gray-100 bg-white">
                <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-4 sm:px-6">
                    <Link
                        href={route('home')}
                        className="rounded-lg text-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                        aria-label="Gogab, accueil"
                    >
                        <ApplicationLogo />
                    </Link>
                    {auth?.user ? (
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="inline-flex min-h-tap items-center gap-2 rounded-full px-3 text-sm font-semibold text-gray-700 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                        >
                            <LogOut className="h-4 w-4" aria-hidden="true" />
                            Déconnexion
                        </Link>
                    ) : (
                        <Link
                            href={route('home')}
                            className="inline-flex min-h-tap items-center gap-2 rounded-full px-3 text-sm font-semibold text-secondary hover:bg-secondary-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                        >
                            <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                            <span className="hidden sm:inline">Retour aux commerces</span>
                            <span className="sm:hidden">Accueil</span>
                        </Link>
                    )}
                </div>
            </header>

            <main className="flex flex-1 flex-col items-center px-4 pb-12 pt-6 sm:justify-center sm:px-6 sm:py-10">
                <div
                    className={cn(
                        'w-full sm:rounded-3xl sm:bg-white sm:p-8 sm:shadow-card sm:ring-1 sm:ring-gray-100',
                        widths[width],
                    )}
                >
                    {(title || subtitle) && (
                        <div className="mb-6">
                            {title && <h1 className="text-2xl font-bold text-secondary-900">{title}</h1>}
                            {subtitle && <p className="mt-1.5 text-gray-600">{subtitle}</p>}
                        </div>
                    )}
                    {children}
                </div>

                {footer && <div className={cn('mt-6 w-full text-center text-sm text-gray-600', widths[width])}>{footer}</div>}
            </main>
        </div>
    );
}
