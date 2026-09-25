import PublicLayout from '@/Layouts/PublicLayout';
import { Head, Link } from '@inertiajs/react';

// Page provisoire : le tunnel de commande complet arrive à l'étape 4.
export default function Index() {
    return (
        <PublicLayout>
            <Head title="Commande" />

            <div className="mx-auto mt-16 max-w-md text-center">
                <h1 className="text-xl font-bold text-gray-900">
                    Finaliser la commande
                </h1>
                <p className="mt-2 text-gray-600">
                    Le choix du quartier, des repères de livraison et du
                    paiement arrive bientôt.
                </p>
                <Link
                    href={route('cart')}
                    className="mt-6 inline-block text-sm font-medium text-emerald-700 hover:underline"
                >
                    ← Retour au panier
                </Link>
            </div>
        </PublicLayout>
    );
}
