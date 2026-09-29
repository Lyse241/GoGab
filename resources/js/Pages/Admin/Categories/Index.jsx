import Button from '@/Components/UI/Button';
import Card from '@/Components/UI/Card';
import ConfirmDialog from '@/Components/UI/ConfirmDialog';
import EmptyState from '@/Components/UI/EmptyState';
import Input from '@/Components/UI/Input';
import Modal from '@/Components/UI/Modal';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { cn } from '@/utils/cn';
import { categoryIcon } from '@/utils/categoryIcons';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Tags, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

/**
 * Catégories de commerces : nom, icône, ordre d'affichage (croissant).
 * Elles alimentent le formulaire d'inscription entreprise et les filtres du catalogue.
 */
export default function Index({ categories, icons }) {
    const [editing, setEditing] = useState(null); // null = fermé, {} = création, catégorie = édition
    const [deleting, setDeleting] = useState(null);
    // La catégorie reste affiché dans la fenêtre pendant son animation de fermeture.
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [deletingInProgress, setDeletingInProgress] = useState(false);

    const destroy = () => {
        router.delete(route('admin.categories.destroy', deleting.id), {
            preserveScroll: true,
            onStart: () => setDeletingInProgress(true),
            onFinish: () => {
                setDeletingInProgress(false);
                setConfirmOpen(false);
            },
        });
    };

    return (
        <DashboardLayout
            header={
                <div className="flex items-center justify-between gap-2">
                    <h1 className="text-xl font-bold text-secondary-900">Catégories</h1>
                    <Button size="sm" icon={Plus} onClick={() => setEditing({})}>
                        Nouvelle
                    </Button>
                </div>
            }
        >
            <Head title="Catégories" />

            <div className="mx-auto max-w-3xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">
                {categories.length === 0 ? (
                    <EmptyState
                        icon={Tags}
                        title="Aucune catégorie"
                        description="Créez les catégories proposées aux entreprises à l’inscription."
                        action={<Button icon={Plus} onClick={() => setEditing({})}>Nouvelle catégorie</Button>}
                    />
                ) : (
                    <Card padding="none">
                        <ul className="divide-y divide-gray-100">
                            {categories.map((category) => {
                                const Icon = categoryIcon(category.icon);
                                const used = category.stores_count > 0;

                                return (
                                    <li key={category.id} className="flex items-center gap-3 p-4">
                                        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-50 text-primary-700">
                                            <Icon className="h-5 w-5" aria-hidden="true" />
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-semibold text-gray-900">{category.name}</p>
                                            <p className="text-sm text-gray-500">
                                                Ordre {category.sort_order} ·{' '}
                                                {used
                                                    ? `${category.stores_count} commerce${category.stores_count > 1 ? 's' : ''}`
                                                    : 'aucun commerce'}
                                            </p>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            icon={Pencil}
                                            onClick={() => setEditing(category)}
                                            aria-label={`Modifier ${category.name}`}
                                        />
                                        <Button
                                            variant="ghost"
                                            size="icon-sm"
                                            icon={Trash2}
                                            disabled={used}
                                            onClick={() => {
                                                setDeleting(category);
                                                setConfirmOpen(true);
                                            }}
                                            className="text-danger-600 hover:bg-danger-50"
                                            aria-label={`Supprimer ${category.name}`}
                                            title={used ? 'Utilisée par au moins un commerce : suppression impossible' : undefined}
                                        />
                                    </li>
                                );
                            })}
                        </ul>
                    </Card>
                )}
                <p className="text-xs text-gray-500">
                    Une catégorie utilisée par un commerce ne peut pas être supprimée : changez d’abord la catégorie
                    de ces commerces.
                </p>
            </div>

            <CategoryForm category={editing} icons={icons} onClose={() => setEditing(null)} />

            <ConfirmDialog
                open={confirmOpen}
                onClose={() => setConfirmOpen(false)}
                onConfirm={destroy}
                loading={deletingInProgress}
                title={deleting ? `Supprimer « ${deleting.name} » ?` : ''}
                message="Elle ne sera plus proposée aux entreprises."
                confirmLabel="Supprimer"
            />
        </DashboardLayout>
    );
}

function CategoryForm({ category, icons, onClose }) {
    const isEdit = Boolean(category?.id);
    const form = useForm({ name: '', icon: icons[0], sort_order: '' });

    // Réinitialise le formulaire à chaque ouverture (création ou autre catégorie).
    useEffect(() => {
        if (category) {
            form.setData({
                name: category.name ?? '',
                icon: category.icon ?? icons[0],
                sort_order: category.sort_order ?? '',
            });
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [category]);

    const submit = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => onClose() };

        if (isEdit) {
            form.put(route('admin.categories.update', category.id), options);
        } else {
            form.post(route('admin.categories.store'), options);
        }
    };

    return (
        <Modal
            open={category !== null}
            onClose={onClose}
            closeable={!form.processing}
            title={isEdit ? 'Modifier la catégorie' : 'Nouvelle catégorie'}
            footer={
                <>
                    <Button variant="outline" onClick={onClose} disabled={form.processing}>
                        Annuler
                    </Button>
                    <Button type="submit" form="category-form" loading={form.processing}>
                        {isEdit ? 'Enregistrer' : 'Ajouter'}
                    </Button>
                </>
            }
        >
            <form id="category-form" onSubmit={submit} className="space-y-4" noValidate>
                <Input
                    id="category-name"
                    label="Nom"
                    required
                    maxLength={60}
                    value={form.data.name}
                    onChange={(e) => form.setData('name', e.target.value)}
                    error={form.errors.name}
                    isFocused
                />

                <fieldset>
                    <legend className="text-sm font-medium text-gray-800">
                        Icône <span className="text-danger-600">*</span>
                    </legend>
                    <div className="mt-2 grid grid-cols-6 gap-2 sm:grid-cols-8">
                        {icons.map((name) => {
                            const Icon = categoryIcon(name);
                            const selected = form.data.icon === name;

                            return (
                                <label
                                    key={name}
                                    className={cn(
                                        'flex h-11 cursor-pointer items-center justify-center rounded-xl ring-1 transition',
                                        'has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-secondary',
                                        selected
                                            ? 'bg-primary-50 text-primary-700 ring-2 ring-primary-500'
                                            : 'text-gray-600 ring-gray-200 hover:bg-gray-50',
                                    )}
                                    title={name}
                                >
                                    <input
                                        type="radio"
                                        name="icon"
                                        value={name}
                                        checked={selected}
                                        onChange={() => form.setData('icon', name)}
                                        className="sr-only"
                                        aria-label={name}
                                    />
                                    <Icon className="h-5 w-5" aria-hidden="true" />
                                </label>
                            );
                        })}
                    </div>
                    {form.errors.icon && <p className="mt-1.5 text-sm text-danger-600">{form.errors.icon}</p>}
                </fieldset>

                <Input
                    id="category-sort"
                    type="number"
                    inputMode="numeric"
                    min={0}
                    label="Ordre d’affichage"
                    hint={isEdit ? 'Les plus petits nombres apparaissent en premier.' : 'Laissez vide pour la placer en dernier.'}
                    value={form.data.sort_order}
                    onChange={(e) => form.setData('sort_order', e.target.value)}
                    error={form.errors.sort_order}
                />
            </form>
        </Modal>
    );
}
