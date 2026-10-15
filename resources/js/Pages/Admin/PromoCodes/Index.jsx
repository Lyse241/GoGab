import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import Card from '@/Components/UI/Card';
import Checkbox from '@/Components/UI/Checkbox';
import EmptyState from '@/Components/UI/EmptyState';
import Input from '@/Components/UI/Input';
import Modal from '@/Components/UI/Modal';
import Select from '@/Components/UI/Select';
import Switch from '@/Components/UI/Switch';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { formatFCFA } from '@/utils/format';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Ticket } from 'lucide-react';
import { useEffect, useState } from 'react';

/**
 * Codes promo : la réduction est financée par Gogab (le commerce et le livreur sont payés comme
 * sans code). Un code se désactive, il ne se supprime pas.
 */
export default function Index({ codes, types }) {
    const [editing, setEditing] = useState(null);

    const toggle = (code) => router.patch(route('admin.promo-codes.toggle', code.id), {}, { preserveScroll: true });

    return (
        <DashboardLayout
            header={
                <div className="flex items-center justify-between gap-2">
                    <h1 className="text-xl font-bold text-secondary-900">Codes promo</h1>
                    <Button size="sm" icon={Plus} onClick={() => setEditing({})}>
                        Nouveau
                    </Button>
                </div>
            }
        >
            <Head title="Codes promo" />

            <div className="mx-auto max-w-4xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
                {codes.length === 0 ? (
                    <EmptyState
                        icon={Ticket}
                        title="Aucun code promo"
                        description="Créez un code de bienvenue ou une livraison offerte pour attirer vos premiers clients."
                        action={<Button icon={Plus} onClick={() => setEditing({})}>Créer un code</Button>}
                    />
                ) : (
                    <Card padding="none">
                        <ul className="divide-y divide-gray-100">
                            {codes.map((code) => (
                                <li key={code.id} className="flex items-center gap-3 px-4 py-3">
                                    <div className="min-w-0 flex-1">
                                        <p className="flex flex-wrap items-center gap-2">
                                            <span className="font-mono font-semibold text-secondary-900">{code.code}</span>
                                            <span className="text-sm text-gray-700">{code.label}</span>
                                            {code.expired && <Badge size="sm" variant="warning">Expiré</Badge>}
                                            {code.first_order_only && <Badge size="sm">1re commande</Badge>}
                                        </p>
                                        <p className="mt-0.5 text-xs text-gray-500">
                                            {code.uses}
                                            {code.max_uses ? ` / ${code.max_uses}` : ''} utilisation{code.uses > 1 ? 's' : ''} ·{' '}
                                            {formatFCFA(code.total_discount)} offerts
                                            {code.min_subtotal > 0 && ` · dès ${formatFCFA(code.min_subtotal)}`}
                                            {code.ends_at && ` · jusqu’au ${code.ends_at.split('-').reverse().join('/')}`}
                                        </p>
                                    </div>
                                    <Switch
                                        checked={code.is_active}
                                        onChange={() => toggle(code)}
                                        label="Actif"
                                        reverse
                                        size="sm"
                                    />
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        icon={Pencil}
                                        onClick={() => setEditing(code)}
                                        aria-label={`Modifier ${code.code}`}
                                    />
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}
            </div>

            <PromoForm code={editing} types={types} onClose={() => setEditing(null)} />
        </DashboardLayout>
    );
}

const EMPTY = {
    code: '',
    description: '',
    type: 'percent',
    value: '',
    min_subtotal: '',
    max_uses: '',
    max_uses_per_user: '1',
    first_order_only: false,
    starts_at: '',
    ends_at: '',
    is_active: true,
};

function PromoForm({ code, types, onClose }) {
    const isEdit = Boolean(code?.id);
    const form = useForm(EMPTY);

    useEffect(() => {
        if (code) {
            form.setData({
                ...EMPTY,
                ...(isEdit
                    ? {
                          code: code.code,
                          description: code.description ?? '',
                          type: code.type,
                          value: code.value ? String(code.value) : '',
                          min_subtotal: code.min_subtotal ? String(code.min_subtotal) : '',
                          max_uses: code.max_uses ? String(code.max_uses) : '',
                          max_uses_per_user: String(code.max_uses_per_user),
                          first_order_only: code.first_order_only,
                          starts_at: code.starts_at ?? '',
                          ends_at: code.ends_at ?? '',
                          is_active: code.is_active,
                      }
                    : {}),
            });
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [code]);

    const submit = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => onClose() };

        if (isEdit) {
            form.put(route('admin.promo-codes.update', code.id), options);
        } else {
            form.post(route('admin.promo-codes.store'), options);
        }
    };

    const set = (field) => (event) => form.setData(field, event.target.value);
    const isFree = form.data.type === 'free_delivery';

    return (
        <Modal
            open={code !== null}
            onClose={onClose}
            closeable={!form.processing}
            title={isEdit ? `Modifier ${code.code}` : 'Nouveau code promo'}
            footer={
                <>
                    <Button variant="outline" onClick={onClose} disabled={form.processing}>
                        Annuler
                    </Button>
                    <Button type="submit" form="promo-form" loading={form.processing}>
                        {isEdit ? 'Enregistrer' : 'Créer'}
                    </Button>
                </>
            }
        >
            <form id="promo-form" onSubmit={submit} className="space-y-4" noValidate>
                <Input
                    id="promo-code"
                    label="Code"
                    required
                    maxLength={30}
                    placeholder="BIENVENUE10"
                    value={form.data.code}
                    onChange={(e) => form.setData('code', e.target.value.toUpperCase())}
                    error={form.errors.code}
                    isFocused
                />
                <Input id="promo-description" label="Note interne (facultatif)" maxLength={200} value={form.data.description} onChange={set('description')} error={form.errors.description} />
                <Select
                    id="promo-type"
                    label="Type de réduction"
                    options={types}
                    value={form.data.type}
                    onChange={set('type')}
                    error={form.errors.type}
                />
                {!isFree && (
                    <Input
                        id="promo-value"
                        label={form.data.type === 'percent' ? 'Pourcentage (1 à 100)' : 'Montant (FCFA)'}
                        type="number"
                        inputMode="numeric"
                        min={1}
                        required
                        value={form.data.value}
                        onChange={set('value')}
                        error={form.errors.value}
                    />
                )}
                <div className="grid gap-4 sm:grid-cols-2">
                    <Input id="promo-min" label="Achat minimum (FCFA)" type="number" inputMode="numeric" min={0} value={form.data.min_subtotal} onChange={set('min_subtotal')} error={form.errors.min_subtotal} />
                    <Input id="promo-max-uses" label="Utilisations totales max." type="number" inputMode="numeric" min={1} placeholder="Illimité" value={form.data.max_uses} onChange={set('max_uses')} error={form.errors.max_uses} />
                    <Input id="promo-per-user" label="Utilisations par client" type="number" inputMode="numeric" min={1} required value={form.data.max_uses_per_user} onChange={set('max_uses_per_user')} error={form.errors.max_uses_per_user} />
                    <span />
                    <Input id="promo-start" label="Début (facultatif)" type="date" value={form.data.starts_at} onChange={set('starts_at')} error={form.errors.starts_at} />
                    <Input id="promo-end" label="Fin (facultatif)" type="date" value={form.data.ends_at} onChange={set('ends_at')} error={form.errors.ends_at} />
                </div>
                <Checkbox
                    id="promo-first"
                    label="Réservé à la première commande du client"
                    checked={form.data.first_order_only}
                    onChange={(e) => form.setData('first_order_only', e.target.checked)}
                />
            </form>
        </Modal>
    );
}
