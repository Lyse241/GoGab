import FormErrors, { focusFirstError } from '@/Components/FormErrors';
import Button from '@/Components/UI/Button';
import Card from '@/Components/UI/Card';
import FileUpload from '@/Components/UI/FileUpload';
import Input from '@/Components/UI/Input';
import Switch from '@/Components/UI/Switch';
import Textarea from '@/Components/UI/Textarea';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { cn } from '@/utils/cn';
import { compressImage } from '@/utils/compressImage';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';

/**
 * Ajout ou modification d'un produit : nom, description, prix (FCFA), section, photo, disponibilité.
 */
export default function Form({ product, sections, maxImageSize }) {
    const isEdit = product !== null;

    const { data, setData, post, processing, errors, transform } = useForm({
        name: product?.name ?? '',
        description: product?.description ?? '',
        price: product?.price ?? '',
        menu_section: product?.menu_section ?? '',
        is_available: product?.is_available ?? true,
        image: null,
        remove_image: false,
    });

    // Les booléens voyagent en 1 / 0 dans un envoi multipart.
    transform((values) => ({
        ...values,
        is_available: values.is_available ? 1 : 0,
        remove_image: values.remove_image ? 1 : 0,
    }));

    const submit = (event) => {
        event.preventDefault();
        post(isEdit ? route('business.products.update', product.id) : route('business.products.store'), {
            forceFormData: true,
            preserveScroll: true,
            onError: focusFirstError,
        });
    };

    const title = isEdit ? `Modifier « ${product.name} »` : 'Nouveau produit';
    const typedSection = data.menu_section.trim().toLocaleLowerCase('fr');

    return (
        <DashboardLayout
            header={<h1 className="truncate text-xl font-bold text-secondary-900">{title}</h1>}
            actions={
                <Button href={route('business.products.index')} variant="ghost" size="sm" icon={ArrowLeft}>
                    Produits
                </Button>
            }
        >
            <Head title={title} />

            <form onSubmit={submit} noValidate className="mx-auto max-w-2xl space-y-5 px-4 py-6 pb-28 sm:px-6 lg:px-8">
                <FormErrors errors={errors} />

                <Card className="space-y-4">
                    <Input
                        id="name"
                        label="Nom du produit"
                        required
                        maxLength={120}
                        placeholder="Ex. : Poulet nyembwe"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        error={errors.name}
                        isFocused={!isEdit}
                    />
                    <Textarea
                        id="description"
                        label="Description (facultatif)"
                        rows={3}
                        maxLength={500}
                        placeholder="Ingrédients, portion, accompagnement…"
                        value={data.description}
                        onChange={(e) => setData('description', e.target.value)}
                        error={errors.description}
                    />
                    <Input
                        id="price"
                        label="Prix"
                        required
                        inputMode="numeric"
                        suffix="FCFA"
                        placeholder="4500"
                        value={data.price}
                        onChange={(e) => setData('price', e.target.value.replace(/[^\d\s]/g, ''))}
                        error={errors.price}
                    />

                    <div>
                        <Input
                            id="menu_section"
                            label="Section du menu (facultatif)"
                            list="menu-sections"
                            maxLength={60}
                            placeholder="Ex. : Plats, Boissons, Desserts"
                            hint="Les produits sont regroupés par section sur votre page."
                            value={data.menu_section}
                            onChange={(e) => setData('menu_section', e.target.value)}
                            error={errors.menu_section}
                            autoComplete="off"
                        />
                        <datalist id="menu-sections">
                            {sections.map((section) => (
                                <option key={section} value={section} />
                            ))}
                        </datalist>
                        {sections.length > 0 && (
                            <div className="mt-2 flex flex-wrap gap-2" aria-label="Sections existantes">
                                {sections.map((section) => (
                                    <button
                                        key={section}
                                        type="button"
                                        onClick={() => setData('menu_section', section)}
                                        className={cn(
                                            'h-8 rounded-full px-3 text-xs font-semibold ring-1 ring-inset transition',
                                            typedSection === section.toLocaleLowerCase('fr')
                                                ? 'bg-secondary text-white ring-secondary'
                                                : 'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50',
                                        )}
                                    >
                                        {section}
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                </Card>

                <Card className="space-y-5">
                    <FileUpload
                        id="image"
                        label="Photo (facultatif)"
                        hint="Carrée de préférence. JPG, PNG ou WebP, 2 Mo maximum."
                        accept="image/jpeg,image/png,image/webp"
                        formats="JPG, PNG ou WebP"
                        maxSize={maxImageSize}
                        prepare={(file) => compressImage(file, { maxDimension: 1000 })}
                        camera
                        value={data.image}
                        current={data.remove_image ? null : product?.image}
                        onRemoveCurrent={product?.image ? () => setData('remove_image', true) : undefined}
                        onChange={(file) => setData((values) => ({ ...values, image: file, remove_image: false }))}
                        error={errors.image}
                    />
                    <Switch
                        checked={data.is_available}
                        onChange={(value) => setData('is_available', value)}
                        label={data.is_available ? 'Disponible' : 'Indisponible'}
                        description={
                            data.is_available
                                ? 'Les clients peuvent commander ce produit.'
                                : 'Visible sur votre page mais impossible à commander (rupture de stock…).'
                        }
                    />
                </Card>

                <div className="fixed inset-x-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-20 border-t border-gray-100 bg-white/95 px-4 py-3 backdrop-blur sm:static sm:border-0 sm:bg-transparent sm:p-0">
                    <div className="mx-auto flex max-w-2xl justify-end gap-2">
                        <Button href={route('business.products.index')} variant="outline" className="hidden sm:inline-flex">
                            Annuler
                        </Button>
                        <Button type="submit" icon={Save} loading={processing} className="w-full sm:w-auto">
                            {isEdit ? 'Enregistrer' : 'Ajouter le produit'}
                        </Button>
                    </div>
                </div>
            </form>
        </DashboardLayout>
    );
}
