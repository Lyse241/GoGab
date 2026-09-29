import FormErrors, { focusFirstError } from '@/Components/FormErrors';
import NeighborhoodSelect from '@/Components/NeighborhoodSelect';
import OpeningHoursEditor, { validateOpeningHours } from '@/Components/OpeningHoursEditor';
import Button from '@/Components/UI/Button';
import Card, { CardHeader } from '@/Components/UI/Card';
import FileUpload from '@/Components/UI/FileUpload';
import Input from '@/Components/UI/Input';
import Select from '@/Components/UI/Select';
import Textarea from '@/Components/UI/Textarea';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { compressImage } from '@/utils/compressImage';
import { Head, useForm } from '@inertiajs/react';
import { Clock, FileLock2, ImageIcon, Phone, Save, Store } from 'lucide-react';

/**
 * « Mon commerce » : fiche publique du commerce (nom, catégorie, contact, quartier, repères),
 * horaires, logo et image de couverture. Les documents ne se modifient pas ici.
 */
export default function Edit({ store, openingHours, categories, neighborhoods, limits }) {
    const { data, setData, post, processing, errors, setError, clearErrors, transform } = useForm({
        name: store.name ?? '',
        category_id: store.category_id ?? '',
        description: store.description ?? '',
        phone: store.phone ?? '',
        neighborhood_id: store.neighborhood_id ?? '',
        address_landmarks: store.address_landmarks ?? '',
        opening_hours: openingHours,
        logo: null,
        cover_image: null,
        remove_logo: false,
        remove_cover_image: false,
    });

    // Les booléens voyagent en 1 / 0 dans un envoi multipart.
    transform((values) => ({
        ...values,
        remove_logo: values.remove_logo ? 1 : 0,
        remove_cover_image: values.remove_cover_image ? 1 : 0,
    }));

    const submit = (event) => {
        event.preventDefault();

        const hoursErrors = validateOpeningHours(data.opening_hours);
        if (Object.keys(hoursErrors).length > 0) {
            setError(hoursErrors);
            focusFirstError(hoursErrors);
            return;
        }

        clearErrors();
        post(route('business.store.update'), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => setData((values) => ({ ...values, logo: null, cover_image: null, remove_logo: false, remove_cover_image: false })),
            onError: focusFirstError,
        });
    };

    const setImage = (field, file) =>
        setData((values) => ({ ...values, [field]: file, [`remove_${field}`]: false }));

    return (
        <DashboardLayout header={<h1 className="text-xl font-bold text-secondary-900">Mon commerce</h1>}>
            <Head title="Mon commerce" />

            <form onSubmit={submit} noValidate className="mx-auto max-w-3xl space-y-5 px-4 py-6 pb-28 sm:px-6 lg:px-8">
                <FormErrors errors={errors} />

                <Card>
                    <CardHeader title="Informations" description="Ce que voient les clients sur votre page." action={<Store className="h-5 w-5 text-primary-600" aria-hidden="true" />} />
                    <div className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Input id="name" label="Nom commercial" required maxLength={120} value={data.name} onChange={(e) => setData('name', e.target.value)} error={errors.name} />
                            <Select
                                id="category_id"
                                label="Catégorie"
                                required
                                placeholder="Choisissez une catégorie"
                                options={categories.map((category) => ({ value: category.id, label: category.name }))}
                                value={data.category_id}
                                onChange={(e) => setData('category_id', e.target.value)}
                                error={errors.category_id}
                            />
                        </div>
                        <Textarea
                            id="description"
                            label="Description courte (facultatif)"
                            rows={2}
                            maxLength={300}
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            error={errors.description}
                        />
                        <Input
                            id="phone"
                            type="tel"
                            inputMode="tel"
                            label="Téléphone du commerce"
                            icon={Phone}
                            placeholder="074 12 34 56"
                            required
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                            error={errors.phone}
                            hint="Les clients et les livreurs vous joindront sur ce numéro."
                        />
                        <NeighborhoodSelect
                            id="neighborhood_id"
                            label="Quartier"
                            required
                            hint="Les livreurs de cette zone recevront vos commandes en priorité."
                            neighborhoods={neighborhoods}
                            value={data.neighborhood_id}
                            onChange={(e) => setData('neighborhood_id', e.target.value)}
                            error={errors.neighborhood_id}
                        />
                        <Textarea
                            id="address_landmarks"
                            label="Adresse et repères"
                            required
                            rows={3}
                            maxLength={500}
                            placeholder="Ex. : face à la pharmacie du Bon Secours, bâtiment bleu au rez-de-chaussée"
                            value={data.address_landmarks}
                            onChange={(e) => setData('address_landmarks', e.target.value)}
                            error={errors.address_landmarks}
                        />
                    </div>
                </Card>

                <Card>
                    <CardHeader title="Horaires d’ouverture" description="Heure de Libreville. Hors horaires, les clients ne peuvent pas commander." action={<Clock className="h-5 w-5 text-primary-600" aria-hidden="true" />} />
                    <OpeningHoursEditor value={data.opening_hours} onChange={(days) => setData('opening_hours', days)} errors={errors} />
                </Card>

                <Card>
                    <CardHeader title="Images" action={<ImageIcon className="h-5 w-5 text-primary-600" aria-hidden="true" />} />
                    <div className="space-y-5">
                        <FileUpload
                            id="logo"
                            label="Logo"
                            hint="Carré de préférence. JPG, PNG ou WebP, 2 Mo maximum."
                            accept="image/jpeg,image/png,image/webp"
                            formats="JPG, PNG ou WebP"
                            maxSize={limits.logo}
                            prepare={(file) => compressImage(file, { maxDimension: 600 })}
                            value={data.logo}
                            current={data.remove_logo ? null : store.logo}
                            onRemoveCurrent={store.logo ? () => setData('remove_logo', true) : undefined}
                            onChange={(file) => setImage('logo', file)}
                            error={errors.logo}
                        />
                        <FileUpload
                            id="cover_image"
                            label="Image de couverture"
                            hint="Format paysage (au moins 400 × 200 px), affichée en haut de votre page. JPG, PNG ou WebP, 4 Mo maximum."
                            accept="image/jpeg,image/png,image/webp"
                            formats="JPG, PNG ou WebP"
                            maxSize={limits.cover_image}
                            prepare={compressImage}
                            camera
                            value={data.cover_image}
                            current={data.remove_cover_image ? null : store.cover_image}
                            onRemoveCurrent={store.cover_image ? () => setData('remove_cover_image', true) : undefined}
                            onChange={(file) => setImage('cover_image', file)}
                            error={errors.cover_image}
                        />
                        {(data.remove_logo || data.remove_cover_image) && (
                            <p className="text-sm text-warning-800">L’image retirée sera supprimée à l’enregistrement.</p>
                        )}
                    </div>
                </Card>

                <p className="flex items-start gap-2 rounded-2xl bg-gray-50 p-4 text-sm text-gray-600 ring-1 ring-gray-100">
                    <FileLock2 className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                    Vos documents (RCCM, NIF, pièce d’identité) ne se modifient pas ici : contactez l’équipe Gogab en cas de changement.
                </p>

                {/* Barre d'enregistrement collée en bas sur mobile */}
                <div className="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-20 border-t border-gray-100 bg-white/95 px-4 py-3 backdrop-blur sm:static sm:border-0 sm:bg-transparent sm:p-0">
                    <div className="mx-auto flex max-w-3xl justify-end">
                        <Button type="submit" icon={Save} loading={processing} className="w-full sm:w-auto">
                            Enregistrer les modifications
                        </Button>
                    </div>
                </div>
            </form>
        </DashboardLayout>
    );
}
