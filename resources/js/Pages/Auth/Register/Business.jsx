import DocumentUploader from '@/Components/DocumentUploader';
import FormErrors from '@/Components/FormErrors';
import NeighborhoodSelect from '@/Components/NeighborhoodSelect';
import OpeningHoursEditor, { validateOpeningHours } from '@/Components/OpeningHoursEditor';
import Button from '@/Components/UI/Button';
import FileUpload from '@/Components/UI/FileUpload';
import Input from '@/Components/UI/Input';
import PasswordInput from '@/Components/UI/PasswordInput';
import Select from '@/Components/UI/Select';
import Stepper from '@/Components/UI/Stepper';
import Textarea from '@/Components/UI/Textarea';
import { useNeighborhood } from '@/Contexts/NeighborhoodContext';
import useRegistrationSteps from '@/Hooks/useRegistrationSteps';
import GuestLayout from '@/Layouts/GuestLayout';
import { compressImage } from '@/utils/compressImage';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, CircleAlert, Mail, Phone, Send, Store, UserRound } from 'lucide-react';

const STEPS = [
    { label: 'Le gérant', description: 'Identité, contact' },
    { label: 'Le commerce', description: 'Adresse, horaires' },
    { label: 'Les documents', description: 'RCCM, NIF, pièce d’identité' },
];

const STEP_FIELDS = [
    ['name', 'phone', 'email', 'password', 'password_confirmation'],
    ['store_name', 'category_id', 'description', 'store_phone', 'neighborhood_id', 'address_landmarks', 'opening_hours', 'logo'],
];

/**
 * Contrôles immédiats (le serveur revalide tout, y compris l'unicité de l'e-mail et du téléphone).
 */
function validateStep(step, data) {
    const errors = {};
    const required = (field, message) => {
        if (!String(data[field] ?? '').trim()) {
            errors[field] = message;
        }
    };

    if (step === 0) {
        required('name', 'Indiquez le nom complet du gérant.');
        required('phone', 'Indiquez votre numéro de téléphone.');
        required('email', 'Indiquez votre adresse e-mail.');
        required('password', 'Choisissez un mot de passe.');
        if (data.password && data.password !== data.password_confirmation) {
            errors.password = 'Les deux mots de passe ne correspondent pas.';
        }
    }

    if (step === 1) {
        required('store_name', 'Indiquez le nom commercial.');
        required('category_id', 'Choisissez la catégorie de votre commerce.');
        required('store_phone', 'Indiquez le téléphone du commerce.');
        required('neighborhood_id', 'Choisissez le quartier du commerce.');
        required('address_landmarks', 'Indiquez l’adresse et des repères pour trouver le commerce.');
        Object.assign(errors, validateOpeningHours(data.opening_hours));
    }

    return errors;
}

export default function Business({ neighborhoods, categories, openingHours, documentTypes, requiredDocuments, optionalDocuments }) {
    const { neighborhoodId } = useNeighborhood();
    const preselected = neighborhoods.some((n) => n.id === neighborhoodId) ? String(neighborhoodId) : '';

    // Un seul formulaire pour les 3 étapes : revenir en arrière ne perd rien (logo et documents compris).
    const form = useForm({
        name: '',
        phone: '',
        email: '',
        password: '',
        password_confirmation: '',
        store_name: '',
        category_id: '',
        description: '',
        store_phone: '',
        neighborhood_id: preselected,
        address_landmarks: '',
        opening_hours: openingHours,
        logo: null,
        documents: {},
    });
    const { data, setData, post, processing, progress, errors, setError, clearErrors, transform } = form;

    const { step, goTo, next, checking, topRef, goToErrors } = useRegistrationSteps({
        form,
        stepFields: STEP_FIELDS,
        checkUrl: route('register.business.check'),
        validate: validateStep,
    });

    const missing = requiredDocuments.filter((type) => !data.documents[type]);

    const update = (field, value) => {
        setData(field, value);
        clearErrors(field);
    };

    const setDocument = (type, file) => {
        setData('documents', { ...data.documents, [type]: file });
        clearErrors(`documents.${type}`);
    };

    const submit = (event) => {
        event.preventDefault();

        if (step < STEPS.length - 1) {
            next();
            return;
        }

        if (missing.length > 0) {
            setError(Object.fromEntries(missing.map((type) => [`documents.${type}`, `Document manquant : ${documentTypes[type].label}.`])));
            return;
        }

        // Documents obligatoires + facultatifs effectivement ajoutés.
        transform((values) => ({
            ...values,
            documents: Object.fromEntries(
                [...requiredDocuments, ...optionalDocuments]
                    .filter((type) => values.documents[type])
                    .map((type) => [type, values.documents[type]]),
            ),
        }));

        post(route('register.business.store'), {
            forceFormData: true,
            onError: goToErrors,
        });
    };

    return (
        <GuestLayout
            width="lg"
            title="Inscrire mon commerce"
            subtitle="Trois étapes pour recevoir des commandes. Préparez votre RCCM, votre NIF et votre pièce d’identité."
            footer={
                <>
                    Déjà inscrit ?{' '}
                    <Link href={route('login')} className="font-semibold text-primary-700 hover:underline">
                        Se connecter
                    </Link>
                </>
            }
        >
            <Head title="Inscription entreprise" />

            <div ref={topRef} className="scroll-mt-20">
                <Stepper steps={STEPS} current={step} className="mb-6" />
            </div>

            <form onSubmit={submit} noValidate className="space-y-5">
                <FormErrors errors={errors} />

                {step === 0 && (
                    <fieldset className="space-y-4">
                        <legend className="sr-only">Le gérant</legend>
                        <Input id="name" label="Nom complet du gérant" icon={UserRound} value={data.name} onChange={(e) => update('name', e.target.value)} error={errors.name} autoComplete="name" required isFocused />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Input id="phone" type="tel" label="Votre téléphone" icon={Phone} placeholder="077 12 34 56" value={data.phone} onChange={(e) => update('phone', e.target.value)} error={errors.phone} autoComplete="tel" inputMode="tel" required />
                            <Input id="email" type="email" label="E-mail" icon={Mail} value={data.email} onChange={(e) => update('email', e.target.value)} error={errors.email} autoComplete="email" inputMode="email" required />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <PasswordInput id="password" label="Mot de passe" hint="8 caractères minimum." value={data.password} onChange={(e) => update('password', e.target.value)} error={errors.password} autoComplete="new-password" required />
                            <PasswordInput id="password_confirmation" label="Confirmer le mot de passe" value={data.password_confirmation} onChange={(e) => update('password_confirmation', e.target.value)} error={errors.password_confirmation} autoComplete="new-password" required />
                        </div>
                    </fieldset>
                )}

                {step === 1 && (
                    <fieldset className="space-y-4">
                        <legend className="sr-only">Le commerce</legend>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Input id="store_name" label="Nom commercial" icon={Store} placeholder="Ex. : Chez Maman Ngoye" value={data.store_name} onChange={(e) => update('store_name', e.target.value)} error={errors.store_name} required />
                            <Select id="category_id" label="Catégorie" placeholder="Choisissez une catégorie" options={categories.map((category) => ({ value: category.id, label: category.name }))} value={data.category_id} onChange={(e) => update('category_id', e.target.value)} error={errors.category_id} required />
                        </div>
                        <Textarea id="description" label="Description courte (facultatif)" placeholder="Ex. : Cuisine gabonaise maison : poulet nyembwe, poisson braisé, feuilles de manioc." rows={2} maxLength={300} value={data.description} onChange={(e) => update('description', e.target.value)} error={errors.description} />
                        <Input
                            id="store_phone"
                            type="tel"
                            label="Téléphone du commerce"
                            icon={Phone}
                            placeholder="074 12 34 56"
                            value={data.store_phone}
                            onChange={(e) => update('store_phone', e.target.value)}
                            error={errors.store_phone}
                            hint={
                                data.phone && !data.store_phone ? (
                                    <button type="button" onClick={() => update('store_phone', data.phone)} className="font-medium text-secondary underline">
                                        Utiliser mon numéro ({data.phone})
                                    </button>
                                ) : (
                                    'Les clients et les livreurs vous joindront sur ce numéro.'
                                )
                            }
                            inputMode="tel"
                            required
                        />
                        <NeighborhoodSelect id="neighborhood_id" label="Quartier du commerce" hint="Les livreurs de cette zone recevront vos commandes en priorité." neighborhoods={neighborhoods} value={data.neighborhood_id} onChange={(e) => update('neighborhood_id', e.target.value)} error={errors.neighborhood_id} required />
                        <Textarea id="address_landmarks" label="Adresse et repères" placeholder="Ex. : face à la pharmacie du Bon Secours, bâtiment bleu au rez-de-chaussée" rows={3} maxLength={500} value={data.address_landmarks} onChange={(e) => update('address_landmarks', e.target.value)} error={errors.address_landmarks} required />
                        <OpeningHoursEditor value={data.opening_hours} onChange={(days) => update('opening_hours', days)} errors={errors} />
                        <FileUpload
                            id="logo"
                            label="Logo (facultatif)"
                            hint="Carré de préférence. JPG, PNG ou WebP, 2 Mo maximum."
                            accept="image/jpeg,image/png,image/webp"
                            formats="JPG, PNG ou WebP"
                            maxSize={2 * 1024 * 1024}
                            prepare={compressImage}
                            value={data.logo}
                            onChange={(file) => update('logo', file)}
                            error={errors.logo}
                        />
                    </fieldset>
                )}

                {step === 2 && (
                    <>
                        <fieldset className="space-y-4">
                            <legend className="mb-1 text-sm text-gray-600">
                                Ces documents permettent à l’équipe Gogab de vérifier que votre commerce existe bien.
                            </legend>
                            {requiredDocuments.map((type) => (
                                <DocumentUploader
                                    key={type}
                                    spec={documentTypes[type]}
                                    value={data.documents[type] ?? null}
                                    onChange={(file) => setDocument(type, file)}
                                    error={errors[`documents.${type}`]}
                                />
                            ))}
                        </fieldset>

                        <fieldset className="space-y-4">
                            <legend className="mb-1">
                                <span className="block text-sm font-semibold uppercase tracking-wide text-gray-500">Documents facultatifs</span>
                                <span className="mt-0.5 block text-sm text-gray-600">Ils accélèrent la validation, surtout pour un restaurant ou une pharmacie.</span>
                            </legend>
                            {optionalDocuments.map((type) => (
                                <DocumentUploader
                                    key={type}
                                    spec={documentTypes[type]}
                                    required={false}
                                    value={data.documents[type] ?? null}
                                    onChange={(file) => setDocument(type, file)}
                                    error={errors[`documents.${type}`]}
                                />
                            ))}
                        </fieldset>
                    </>
                )}

                {step === 2 && missing.length > 0 && (
                    <div role="status" className="flex items-start gap-2 rounded-2xl bg-accent-50 p-3 text-sm text-secondary-900 ring-1 ring-accent-200">
                        <CircleAlert className="mt-0.5 h-4 w-4 shrink-0 text-accent-700" aria-hidden="true" />
                        <p>
                            <span className="font-semibold">
                                {missing.length} document{missing.length > 1 ? 's' : ''} obligatoire{missing.length > 1 ? 's' : ''} manquant{missing.length > 1 ? 's' : ''} :
                            </span>{' '}
                            {missing.map((type) => documentTypes[type].label).join(', ')}.
                        </p>
                    </div>
                )}

                <div className="flex flex-col-reverse gap-2 border-t border-gray-100 pt-5 sm:flex-row sm:justify-between">
                    {step > 0 ? (
                        <Button variant="outline" icon={ArrowLeft} onClick={() => goTo(step - 1)} disabled={processing}>
                            Précédent
                        </Button>
                    ) : (
                        <Button href={route('register')} variant="ghost" icon={ArrowLeft}>
                            Changer de profil
                        </Button>
                    )}

                    {step < STEPS.length - 1 ? (
                        <Button type="submit" iconRight={ArrowRight} loading={checking}>
                            Continuer
                        </Button>
                    ) : (
                        <Button type="submit" icon={Send} loading={processing} disabled={missing.length > 0}>
                            {processing && progress ? `Envoi… ${progress.percentage} %` : 'Envoyer ma demande'}
                        </Button>
                    )}
                </div>
            </form>
        </GuestLayout>
    );
}
