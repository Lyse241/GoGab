import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';

export default function Dashboard() {
    const { user } = usePage().props.auth;

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Mes livraisons
                </h2>
            }
        >
            <Head title="Espace livreur" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            Bienvenue {user.name}. Les commandes à livrer
                            s'afficheront ici dans les prochaines étapes.
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
