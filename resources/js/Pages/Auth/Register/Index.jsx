import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link } from '@inertiajs/react';
import { Bike, ChevronRight, ShoppingBag, Store } from 'lucide-react';

export const PROFILES = [
    {
        key: 'client',
        route: 'register.client',
        icon: ShoppingBag,
        title: 'Client',
        tagline: 'Je commande',
        description: 'Repas, courses, médicaments : faites-vous livrer par les commerces de votre quartier.',
        classes: 'bg-primary-50 text-primary-700',
    },
    {
        key: 'delivery',
        route: 'register.delivery',
        icon: Bike,
        title: 'Livreur',
        tagline: 'Je livre',
        description: 'À moto, à vélo ou en voiture, gagnez de l’argent en livrant dans votre zone.',
        classes: 'bg-secondary-50 text-secondary',
    },
    {
        key: 'business',
        route: 'register.business',
        icon: Store,
        title: 'Entreprise',
        tagline: 'Je vends',
        description: 'Restaurant, pharmacie, épicerie, boutique : recevez des commandes en ligne.',
        classes: 'bg-accent-100 text-accent-800',
    },
];

/**
 * « Choisissez votre profil » : première étape de l'inscription.
 */
export default function Index() {
    return (
        <GuestLayout
            width="lg"
            title="Choisissez votre profil"
            subtitle="Créez votre compte Gogab en quelques minutes."
            footer={
                <>
                    Déjà inscrit ?{' '}
                    <Link href={route('login')} className="tap-area font-semibold text-primary-700 hover:underline">
                        Se connecter
                    </Link>
                </>
            }
        >
            <Head title="Inscription" />

            <ul className="grid gap-3 sm:grid-cols-3 sm:gap-4">
                {PROFILES.map((profile) => {
                    const Icon = profile.icon;

                    return (
                        <li key={profile.key}>
                            <Link
                                href={route(profile.route)}
                                className="group flex h-full items-center gap-4 rounded-2xl bg-white p-4 shadow-card ring-1 ring-gray-100 transition hover:-translate-y-0.5 hover:shadow-card-hover hover:ring-primary-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary sm:flex-col sm:items-start sm:p-5"
                            >
                                <span className={`flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl ${profile.classes}`}>
                                    <Icon className="h-7 w-7" aria-hidden="true" />
                                </span>
                                <span className="min-w-0 flex-1">
                                    <span className="block text-lg font-bold text-secondary-900">{profile.title}</span>
                                    <span className="block text-sm font-semibold text-primary-700">« {profile.tagline} »</span>
                                    <span className="mt-1 block text-sm text-gray-600">{profile.description}</span>
                                </span>
                                <ChevronRight
                                    className="h-5 w-5 shrink-0 text-gray-400 transition group-hover:translate-x-0.5 group-hover:text-primary-600 sm:hidden"
                                    aria-hidden="true"
                                />
                            </Link>
                        </li>
                    );
                })}
            </ul>

            <p className="mt-6 text-center text-xs text-gray-500">
                Chaque compte est vérifié par l’équipe Gogab avant d’être activé.
            </p>
        </GuestLayout>
    );
}
