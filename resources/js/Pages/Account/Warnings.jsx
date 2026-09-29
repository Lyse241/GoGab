import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Head } from '@inertiajs/react';
import { ShieldCheck, TriangleAlert } from 'lucide-react';

/**
 * « Mes avertissements » : avertissements reçus de l'équipe Gogab.
 */
export default function Warnings({ warnings }) {
    return (
        <DashboardLayout header={<h1 className="text-xl font-bold text-secondary-900">Mes avertissements</h1>}>
            <Head title="Mes avertissements" />

            <div className="mx-auto max-w-3xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
                {warnings.length === 0 ? (
                    <EmptyState icon={ShieldCheck} title="Aucun avertissement" description="Merci de respecter les règles de la communauté Gogab." />
                ) : (
                    <>
                        <p className="text-sm text-gray-600">
                            {warnings.length} avertissement{warnings.length > 1 ? 's' : ''} reçu{warnings.length > 1 ? 's' : ''}.
                            En cas de nouveaux manquements, votre compte pourra être bloqué.
                        </p>
                        <ul className="space-y-3">
                            {warnings.map((warning) => (
                                <li key={warning.id}>
                                    <Card>
                                        <div className="flex items-start gap-3">
                                            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-warning-50 text-warning-600">
                                                <TriangleAlert className="h-5 w-5" aria-hidden="true" />
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <p className="font-semibold text-gray-900">{warning.reason_label}</p>
                                                    <Badge color={warning.acknowledged ? 'neutral' : 'warning'} size="sm">
                                                        {warning.acknowledged ? 'Lu' : 'Non lu'}
                                                    </Badge>
                                                </div>
                                                <p className="mt-1 whitespace-pre-line text-sm text-gray-700">{warning.message}</p>
                                                <p className="mt-2 text-xs text-gray-500">{warning.at}</p>
                                            </div>
                                        </div>
                                    </Card>
                                </li>
                            ))}
                        </ul>
                    </>
                )}
            </div>
        </DashboardLayout>
    );
}
