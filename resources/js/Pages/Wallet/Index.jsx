import Card from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import Pagination from '@/Components/UI/Pagination';
import DashboardLayout from '@/Layouts/DashboardLayout';
import DeliveryLayout from '@/Layouts/DeliveryLayout';
import { formatFCFA } from '@/utils/format';
import { Head, usePage } from '@inertiajs/react';
import { Wallet } from 'lucide-react';

/**
 * « Mon solde » (entreprise et livreur). Solde > 0 : Gogab doit cette somme ; < 0 : le livreur
 * doit remettre ces espèces à Gogab. Les mouvements viennent du grand livre (WalletService).
 */
export default function Index({ balance, entries, commission_rate }) {
    const role = usePage().props.auth.role;
    const isCourier = role === 'delivery';

    const content = (
        <>
            <Head title="Mon solde" />

            <Card className={balance < 0 ? 'bg-warning-50 ring-warning-200' : 'bg-primary-600 text-white ring-primary-600'}>
                <p className={balance < 0 ? 'text-sm font-medium text-warning-800' : 'text-sm font-medium text-primary-50'}>
                    {balance < 0 ? 'Espèces à remettre à Gogab' : balance === 0 ? 'Solde à jour' : 'Gogab vous doit'}
                </p>
                <p className={balance < 0 ? 'mt-1 text-3xl font-bold text-warning-900' : 'mt-1 text-3xl font-bold'}>
                    {formatFCFA(Math.abs(balance))}
                </p>
                <p className={balance < 0 ? 'mt-2 text-sm text-warning-800' : 'mt-2 text-sm text-primary-50'}>
                    {balance < 0
                        ? 'Remettez ces espèces à l’équipe Gogab : le règlement sera enregistré et votre solde mis à jour.'
                        : isCourier
                          ? 'Vos frais de livraison, déduction faite des espèces que vous avez encaissées.'
                          : `Vos ventes livrées, moins la commission Gogab de ${commission_rate} %.`}
                </p>
            </Card>

            <section aria-labelledby="entries-title" className="space-y-3">
                <h2 id="entries-title" className="font-semibold text-secondary-900">
                    Mouvements
                </h2>
                {entries.data.length === 0 ? (
                    <EmptyState
                        icon={Wallet}
                        title="Aucun mouvement"
                        description="Vos gains apparaîtront ici après votre première commande livrée."
                    />
                ) : (
                    <Card padding="none">
                        <ul className="divide-y divide-gray-100">
                            {entries.data.map((entry) => (
                                <li key={entry.id} className="flex items-center gap-3 px-4 py-3 text-sm">
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-gray-900">{entry.description}</p>
                                        <p className="text-xs text-gray-500">
                                            {entry.created_at} · {entry.type}
                                        </p>
                                    </div>
                                    <span className={entry.amount >= 0 ? 'font-semibold text-success-700' : 'font-semibold text-danger-700'}>
                                        {entry.amount >= 0 ? '+' : '−'}
                                        {formatFCFA(Math.abs(entry.amount))}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}
                <Pagination paginator={entries} />
            </section>
        </>
    );

    if (isCourier) {
        return (
            <DeliveryLayout title="Mon solde" subtitle="Gains et espèces à remettre" availability={false}>
                {content}
            </DeliveryLayout>
        );
    }

    return (
        <DashboardLayout header={<h1 className="text-xl font-bold text-secondary-900">Mon solde</h1>}>
            <div className="mx-auto max-w-3xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">{content}</div>
        </DashboardLayout>
    );
}
