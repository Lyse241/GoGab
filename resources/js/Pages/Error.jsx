import ApplicationLogo from '@/Components/ApplicationLogo';
import Button from '@/Components/UI/Button';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Clock, House, Lock, RotateCw, SearchX, ServerCrash, Wrench } from 'lucide-react';

const MESSAGES = {
    403: {
        icon: Lock,
        title: 'Accès refusé',
        description: 'Cette page est réservée à un autre espace, ou à un autre compte.',
    },
    404: {
        icon: SearchX,
        title: 'Page introuvable',
        description: 'Cette page n’existe pas ou n’est plus disponible : le commerce ou la commande a peut-être été retiré.',
    },
    419: {
        icon: Clock,
        title: 'Page expirée',
        description: 'La page est restée ouverte trop longtemps. Rechargez-la puis recommencez.',
    },
    500: {
        icon: ServerCrash,
        title: 'Erreur du serveur',
        description: 'Un problème est survenu de notre côté. Réessayez dans quelques instants.',
    },
    503: {
        icon: Wrench,
        title: 'Service en maintenance',
        description: 'Gogab revient très vite. Merci de votre patience.',
    },
};

/**
 * Page d'erreur Gogab (403, 404, 419, 500, 503) : message clair, retour vers l'espace du rôle
 * (`home`, calculé par le serveur : App\Support\ErrorPage), page précédente ou rechargement.
 * Elle n'utilise pas les props partagées : une erreur peut survenir avant qu'elles existent.
 */
export default function Error({ status, home }) {
    const { icon: Icon, title, description } = MESSAGES[status] ?? MESSAGES[500];
    const back = home ?? { url: '/', label: 'Retour à l’accueil' };
    const canGoBack = typeof window !== 'undefined' && window.history.length > 1;

    return (
        <div className="flex min-h-screen flex-col bg-gray-50">
            <Head title={title} />

            <header className="bg-secondary shadow-md">
                <div className="mx-auto flex h-14 max-w-6xl items-center px-4 sm:px-6">
                    <Link href="/" className="rounded-lg text-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-accent" aria-label="Gogab, accueil">
                        <ApplicationLogo light />
                    </Link>
                </div>
            </header>

            <main className="flex flex-1 items-center justify-center px-4 py-12">
                <div className="max-w-md text-center">
                    <span className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary-50 text-primary-600">
                        <Icon className="h-8 w-8" aria-hidden="true" />
                    </span>
                    <p className="mt-4 text-6xl font-extrabold text-primary-600">{status}</p>
                    <h1 className="mt-2 text-2xl font-bold text-secondary-900">{title}</h1>
                    <p className="mt-2 text-gray-600">{description}</p>

                    <div className="mt-8 flex flex-col items-center justify-center gap-2 sm:flex-row">
                        {status === 419 ? (
                            <Button icon={RotateCw} onClick={() => window.location.reload()}>
                                Recharger la page
                            </Button>
                        ) : (
                            // Lien classique : repart d'une page complète (props partagées à jour).
                            <a
                                href={back.url}
                                className="inline-flex h-11 items-center gap-2 rounded-full bg-primary-600 px-5 text-sm font-semibold text-white hover:bg-primary-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2"
                            >
                                <House className="h-5 w-5" aria-hidden="true" />
                                {back.label}
                            </a>
                        )}
                        {canGoBack && (
                            <Button variant="ghost" icon={ArrowLeft} onClick={() => window.history.back()}>
                                Page précédente
                            </Button>
                        )}
                    </div>
                </div>
            </main>
        </div>
    );
}
