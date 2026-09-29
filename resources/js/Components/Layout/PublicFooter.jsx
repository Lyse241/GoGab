import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link, usePage } from '@inertiajs/react';
import { Wallet } from 'lucide-react';

function Column({ title, children }) {
    return (
        <nav aria-label={title}>
            <h2 className="text-sm font-semibold uppercase tracking-wide text-white">{title}</h2>
            <ul className="mt-4 space-y-2.5 text-sm">{children}</ul>
        </nav>
    );
}

function FooterLink({ href, children }) {
    return (
        <li>
            <Link href={href} className="text-secondary-100 transition hover:text-white hover:underline">
                {children}
            </Link>
        </li>
    );
}

/**
 * Footer des pages publiques (bleu profond) : opportunités, liens utiles, moyens de paiement
 * et catégories populaires. Données : prop partagée `footer` (HandleInertiaRequests).
 * Pas de badges App Store / Google Play : Gogab n'a pas d'application native.
 */
export default function PublicFooter() {
    const { auth, footer } = usePage().props;
    const user = auth.user;

    return (
        <footer className="bg-secondary text-secondary-100">
            <div className="mx-auto grid max-w-6xl grid-cols-2 gap-x-6 gap-y-10 px-4 py-12 sm:px-6 lg:grid-cols-5">
                <div className="col-span-2 lg:col-span-1">
                    <ApplicationLogo light className="text-2xl" />
                    <p className="mt-3 max-w-xs text-sm text-secondary-200">
                        Vos commerces de quartier livrés chez vous, partout à Libreville.
                    </p>
                </div>

                <Column title="Opportunités">
                    <FooterLink href={route('register.delivery')}>Devenir livreur</FooterLink>
                    <FooterLink href={route('register.business')}>Enregistrer votre commerce</FooterLink>
                </Column>

                <Column title="Liens utiles">
                    <FooterLink href={route('home')}>Tous les commerces</FooterLink>
                    <FooterLink href={route('cart')}>Mon panier</FooterLink>
                    {user ? (
                        <>
                            <FooterLink href={route('notifications.index')}>Mes notifications</FooterLink>
                            <FooterLink href={route('profile.edit')}>Mon compte</FooterLink>
                        </>
                    ) : (
                        <>
                            <FooterLink href={route('login')}>Connexion</FooterLink>
                            <FooterLink href={route('register')}>Créer un compte</FooterLink>
                        </>
                    )}
                </Column>

                <Column title="Moyens de paiement">
                    {(footer?.payment_methods ?? []).map((method) => (
                        <li key={method} className="flex items-center gap-2">
                            <Wallet className="h-4 w-4 shrink-0 text-accent" aria-hidden="true" />
                            {method}
                        </li>
                    ))}
                </Column>

                {footer?.popular_categories?.length > 0 && (
                    <Column title="Catégories populaires">
                        {footer.popular_categories.map((category) => (
                            <FooterLink key={category.slug} href={route('home', { category: category.slug })}>
                                {category.name}
                            </FooterLink>
                        ))}
                    </Column>
                )}
            </div>

            <div className="border-t border-white/10">
                <p className="mx-auto max-w-6xl px-4 py-5 text-xs text-secondary-300 sm:px-6">
                    © {new Date().getFullYear()} Gogab · Libreville, Gabon
                </p>
            </div>
        </footer>
    );
}
