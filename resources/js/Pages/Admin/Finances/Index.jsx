import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import Card from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import Input from '@/Components/UI/Input';
import Modal from '@/Components/UI/Modal';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { formatFCFA } from '@/utils/format';
import { Head, useForm } from '@inertiajs/react';
import { HandCoins, Wallet } from 'lucide-react';
import { useEffect, useState } from 'react';

/**
 * Finances : ce que Gogab doit aux entreprises et livreurs, ce que les livreurs lui doivent
 * (espèces encaissées auprès des clients) et la commission perçue. Chaque règlement est un
 * mouvement du grand livre (WalletService), jamais une modification de solde.
 */
export default function Index({ accounts, totals, commission_rate, recent }) {
    const [settling, setSettling] = useState(null);

    return (
        <DashboardLayout header={<h1 className="text-xl font-bold text-secondary-900">Finances</h1>}>
            <Head title="Finances" />

            <div className="mx-auto max-w-5xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
                <div className="grid gap-3 sm:grid-cols-3">
                    <Stat label="À verser (entreprises, livreurs)" value={totals.owed_to_users} />
                    <Stat label="À récupérer (espèces des livreurs)" value={totals.owed_to_gogab} />
                    <Stat label={`Commission perçue (${commission_rate} %)`} value={totals.commission} />
                </div>

                <section aria-labelledby="balances-title" className="space-y-3">
                    <h2 id="balances-title" className="font-semibold text-secondary-900">
                        Soldes par compte
                    </h2>
                    {accounts.length === 0 ? (
                        <EmptyState
                            icon={Wallet}
                            title="Aucun mouvement pour l’instant"
                            description="Les soldes apparaissent dès la première commande livrée."
                        />
                    ) : (
                        <Card padding="none">
                            <ul className="divide-y divide-gray-100">
                                {accounts.map((account) => (
                                    <li key={account.id} className="flex items-center gap-3 px-4 py-3">
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-medium text-gray-900">{account.name}</p>
                                            <p className="text-xs text-gray-500">{account.role_label}</p>
                                        </div>
                                        <div className="text-right">
                                            <p className="font-semibold text-gray-900">{formatFCFA(Math.abs(account.balance))}</p>
                                            <BalanceBadge balance={account.balance} />
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            icon={HandCoins}
                                            disabled={account.balance === 0}
                                            onClick={() => setSettling(account)}
                                        >
                                            Régler
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    )}
                </section>

                {recent.length > 0 && (
                    <section aria-labelledby="recent-title" className="space-y-3">
                        <h2 id="recent-title" className="font-semibold text-secondary-900">
                            Derniers mouvements
                        </h2>
                        <Card padding="none">
                            <ul className="divide-y divide-gray-100">
                                {recent.map((entry) => (
                                    <li key={entry.id} className="flex items-center gap-3 px-4 py-3 text-sm">
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-gray-900">{entry.description}</p>
                                            <p className="text-xs text-gray-500">
                                                {entry.user} · {entry.type}
                                            </p>
                                        </div>
                                        <span className={entry.amount >= 0 ? 'font-medium text-success-700' : 'font-medium text-danger-700'}>
                                            {entry.amount >= 0 ? '+' : '−'}
                                            {formatFCFA(Math.abs(entry.amount))}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </Card>
                    </section>
                )}
            </div>

            <SettleForm account={settling} onClose={() => setSettling(null)} />
        </DashboardLayout>
    );
}

function Stat({ label, value }) {
    return (
        <Card>
            <p className="text-sm text-gray-600">{label}</p>
            <p className="mt-1 text-2xl font-bold text-secondary-900">{formatFCFA(value)}</p>
        </Card>
    );
}

function BalanceBadge({ balance }) {
    if (balance === 0) return <Badge size="sm">À jour</Badge>;

    return balance > 0 ? (
        <Badge size="sm" variant="info">
            Gogab doit
        </Badge>
    ) : (
        <Badge size="sm" variant="warning">
            Doit à Gogab
        </Badge>
    );
}

function SettleForm({ account, onClose }) {
    const form = useForm({ amount: '', note: '' });
    const owed = account ? Math.abs(account.balance) : 0;
    const gogabPays = account?.balance > 0;

    useEffect(() => {
        if (account) {
            form.setData({ amount: String(owed), note: '' });
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [account]);

    const submit = (event) => {
        event.preventDefault();
        form.post(route('admin.finances.settle', account.id), {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <Modal
            open={account !== null}
            onClose={onClose}
            closeable={!form.processing}
            title={account ? `Régler ${account.name}` : 'Régler'}
            footer={
                <>
                    <Button variant="outline" onClick={onClose} disabled={form.processing}>
                        Annuler
                    </Button>
                    <Button type="submit" form="settle-form" loading={form.processing}>
                        Enregistrer le règlement
                    </Button>
                </>
            }
        >
            <form id="settle-form" onSubmit={submit} className="space-y-4" noValidate>
                <p className="text-sm text-gray-600">
                    {gogabPays
                        ? `Gogab doit ${formatFCFA(owed)} à ${account?.name}. Enregistrez ici le montant que vous venez de lui verser.`
                        : `${account?.name} doit ${formatFCFA(owed)} à Gogab. Enregistrez ici les espèces qu’il vient de remettre.`}
                </p>
                <Input
                    id="settle-amount"
                    label="Montant réglé (FCFA)"
                    type="number"
                    inputMode="numeric"
                    min={1}
                    max={owed}
                    required
                    value={form.data.amount}
                    onChange={(e) => form.setData('amount', e.target.value)}
                    error={form.errors.amount}
                    isFocused
                />
                <Input
                    id="settle-note"
                    label="Référence ou note (facultatif)"
                    maxLength={200}
                    placeholder="Ex. virement Airtel Money du 12/10"
                    value={form.data.note}
                    onChange={(e) => form.setData('note', e.target.value)}
                    error={form.errors.note}
                />
            </form>
        </Modal>
    );
}
