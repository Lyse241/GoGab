import Button from '@/Components/UI/Button';
import EmptyState from '@/Components/UI/EmptyState';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Head } from '@inertiajs/react';
import { ArrowLeft, Construction } from 'lucide-react';

/**
 * Section d'un espace connecté (admin, entreprise…) pas encore construite.
 *
 * - title : nom de la section
 * - description : texte de l'état vide (facultatif)
 * - back : nom de la route du tableau de bord de l'espace
 */
export default function ComingSoon({ title, description, back }) {
    return (
        <DashboardLayout header={<h1 className="text-xl font-bold text-secondary-900">{title}</h1>}>
            <Head title={title} />

            <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:px-8">
                <EmptyState
                    icon={Construction}
                    title="Bientôt disponible"
                    description={description ?? `La section « ${title} » arrive dans une prochaine version.`}
                    action={
                        back && (
                            <Button href={route(back)} variant="outline" icon={ArrowLeft}>
                                Retour au tableau de bord
                            </Button>
                        )
                    }
                />
            </div>
        </DashboardLayout>
    );
}
