import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import Card from '@/Components/UI/Card';
import Input from '@/Components/UI/Input';
import Modal from '@/Components/UI/Modal';
import Select from '@/Components/UI/Select';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Head, useForm } from '@inertiajs/react';
import { MapPin, Pencil, Plus, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

const plural = (count, word) => `${count} ${word}${count > 1 ? 's' : ''}`;

/**
 * Quartiers de Libreville regroupés par zone. La zone définit « les livreurs autour » d'un commerce
 * (pas de GPS) : changer la zone d'un quartier change les livreurs sollicités pour ses commandes.
 */
export default function Index({ neighborhoods, zones }) {
    const [editing, setEditing] = useState(null);
    const [search, setSearch] = useState('');

    const groups = useMemo(() => {
        const term = search.trim().toLocaleLowerCase('fr');
        const visible = term
            ? neighborhoods.filter((n) => n.name.toLocaleLowerCase('fr').includes(term))
            : neighborhoods;

        return zones.map((zone) => ({ zone, items: visible.filter((n) => n.zone === zone) }));
    }, [neighborhoods, zones, search]);

    return (
        <DashboardLayout
            header={
                <div className="flex items-center justify-between gap-2">
                    <h1 className="text-xl font-bold text-secondary-900">Quartiers et zones</h1>
                    <Button size="sm" icon={Plus} onClick={() => setEditing({})}>
                        Nouveau
                    </Button>
                </div>
            }
        >
            <Head title="Quartiers et zones" />

            <div className="mx-auto max-w-5xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
                <Input
                    id="neighborhood-search"
                    type="search"
                    icon={Search}
                    placeholder="Rechercher un quartier"
                    aria-label="Rechercher un quartier"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                />

                <p className="text-sm text-gray-600">
                    Les livreurs d’une zone reçoivent les commandes des commerces situés dans les quartiers de
                    cette zone.
                </p>

                <div className="grid gap-4 md:grid-cols-2">
                    {groups.map(({ zone, items }) => (
                        <Card key={zone} padding="none">
                            <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                                <h2 className="font-semibold text-secondary-900">Zone {zone}</h2>
                                <Badge size="sm">{plural(items.length, 'quartier')}</Badge>
                            </div>
                            {items.length === 0 ? (
                                <p className="px-4 py-6 text-center text-sm text-gray-500">
                                    {search ? 'Aucun quartier trouvé.' : 'Aucun quartier dans cette zone.'}
                                </p>
                            ) : (
                                <ul className="divide-y divide-gray-100">
                                    {items.map((neighborhood) => (
                                        <li key={neighborhood.id} className="flex items-center gap-3 px-4 py-3">
                                            <MapPin className="h-5 w-5 shrink-0 text-primary-600" aria-hidden="true" />
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate font-medium text-gray-900">{neighborhood.name}</p>
                                                <p className="text-xs text-gray-500">
                                                    {plural(neighborhood.stores_count, 'commerce')} ·{' '}
                                                    {plural(neighborhood.delivery_profiles_count, 'livreur')} ·{' '}
                                                    {plural(neighborhood.users_count, 'compte')}
                                                </p>
                                            </div>
                                            <Button
                                                variant="ghost"
                                                size="icon-sm"
                                                icon={Pencil}
                                                onClick={() => setEditing(neighborhood)}
                                                aria-label={`Modifier ${neighborhood.name}`}
                                            />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Card>
                    ))}
                </div>
            </div>

            <NeighborhoodForm neighborhood={editing} zones={zones} onClose={() => setEditing(null)} />
        </DashboardLayout>
    );
}

function NeighborhoodForm({ neighborhood, zones, onClose }) {
    const isEdit = Boolean(neighborhood?.id);
    const form = useForm({ name: '', zone: '' });

    // Réinitialise le formulaire à chaque ouverture (ajout ou autre quartier).
    useEffect(() => {
        if (neighborhood) {
            form.setData({ name: neighborhood.name ?? '', zone: neighborhood.zone ?? '' });
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [neighborhood]);

    const submit = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => onClose() };

        if (isEdit) {
            form.put(route('admin.neighborhoods.update', neighborhood.id), options);
        } else {
            form.post(route('admin.neighborhoods.store'), options);
        }
    };

    return (
        <Modal
            open={neighborhood !== null}
            onClose={onClose}
            closeable={!form.processing}
            title={isEdit ? 'Modifier le quartier' : 'Nouveau quartier'}
            footer={
                <>
                    <Button variant="outline" onClick={onClose} disabled={form.processing}>
                        Annuler
                    </Button>
                    <Button type="submit" form="neighborhood-form" loading={form.processing}>
                        {isEdit ? 'Enregistrer' : 'Ajouter'}
                    </Button>
                </>
            }
        >
            <form id="neighborhood-form" onSubmit={submit} className="space-y-4" noValidate>
                <Input
                    id="neighborhood-name"
                    label="Nom du quartier"
                    required
                    maxLength={80}
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    error={form.errors.name}
                    isFocused
                />
                <Select
                    id="neighborhood-zone"
                    label="Zone"
                    required
                    placeholder="Choisir une zone"
                    options={zones.map((zone) => ({ value: zone, label: zone }))}
                    value={form.data.zone}
                    onChange={(e) => form.setData('zone', e.target.value)}
                    error={form.errors.zone}
                    hint={
                        isEdit && form.data.zone !== neighborhood.zone
                            ? 'Les nouvelles commandes de ce quartier iront aux livreurs de la nouvelle zone.'
                            : undefined
                    }
                />
            </form>
        </Modal>
    );
}
