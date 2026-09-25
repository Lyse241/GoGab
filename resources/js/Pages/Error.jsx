import ApplicationLogo from '@/Components/ApplicationLogo';
import { Head, Link } from '@inertiajs/react';

const messages = {
    403: {
        title: 'Accès refusé',
        description: "Vous n'avez pas l'autorisation d'accéder à cette page.",
    },
    404: {
        title: 'Page introuvable',
        description:
            "Cette page n'existe pas ou n'est plus disponible : la boutique ou la commande a peut-être été supprimée.",
    },
    500: {
        title: 'Erreur du serveur',
        description:
            'Un problème est survenu de notre côté. Réessayez dans quelques instants.',
    },
    503: {
        title: 'Service en maintenance',
        description: 'Gogab revient très vite. Merci de votre patience.',
    },
};

/**
 * Page d'erreur autonome : elle ne dépend pas des props partagées (auth…),
 * car une URL inexistante n'est pas passée par les middlewares web.
 */
export default function Error({ status }) {
    const { title, description } = messages[status] ?? messages[500];

    return (
        <div className="flex min-h-screen flex-col bg-gray-50">
            <Head title={title} />

            <header className="bg-secondary shadow-md">
                <div className="mx-auto flex h-14 max-w-6xl items-center px-4 sm:px-6">
                    <Link href="/" className="text-2xl" aria-label="Gogab, accueil">
                        <ApplicationLogo light />
                    </Link>
                </div>
            </header>

            <main className="flex flex-1 items-center justify-center px-4 py-12">
                <div className="max-w-md text-center">
                    <p className="text-7xl font-extrabold text-primary">{status}</p>
                    <h1 className="mt-4 text-2xl font-bold text-secondary">{title}</h1>
                    <p className="mt-2 text-gray-600">{description}</p>
                    <Link
                        href="/"
                        className="mt-8 inline-block rounded-full bg-primary-600 px-6 py-3 font-semibold text-white hover:bg-primary-700"
                    >
                        Retour aux boutiques
                    </Link>
                </div>
            </main>
        </div>
    );
}
