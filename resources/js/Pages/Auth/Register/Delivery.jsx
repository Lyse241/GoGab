import DocumentUploader from '@/Components/DocumentUploader';
import FormErrors from '@/Components/FormErrors';
import NeighborhoodSelect from '@/Components/NeighborhoodSelect';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import PasswordInput from '@/Components/UI/PasswordInput';
import Stepper from '@/Components/UI/Stepper';
import Textarea from '@/Components/UI/Textarea';
import { useNeighborhood } from '@/Contexts/NeighborhoodContext';
import useRegistrationSteps from '@/Hooks/useRegistrationSteps';
import GuestLayout from '@/Layouts/GuestLayout';
import { cn } from '@/utils/cn';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Bike, Car, CircleAlert, Hash, IdCard, Mail, Motorbike, Phone, Send, UserRound } from 'lucide-react';

const STEPS = [
    { label: 'Vos informations', description: 'Identité, contact' },
    { label: 'Votre véhicule', description: 'Type, plaque, zone' },
    { label: 'Vos documents', description: 'CIN, permis, photos' },
];

// Champs de chaque étape : sert à revenir à la bonne étape quand le serveur signale une erreur.
const STEP_FIELDS = [
    ['name', 'phone', 'email', 'password', 'password_confirmation', 'neighborhood_id', 'address_landmarks'],
    ['vehicle_type', 'vehicle_brand', 'plate_number', 'license_number', 'base_neighborhood_id'],
];

const VEHICLE_ICONS = { moto: Motorbike, bicycle: Bike, car: Car };
const VEHICLE_PHRASES = { moto: 'à moto', bicycle: 'à vélo', car: 'en voiture' };

/**
 * Contrôles immédiats (le serveur revalide tout, y compris l'unicité).
 */
function validateStep(step, data, vehicle) {
    const errors = {};
    const required = (field, message) => {
        if (!String(data[field] ?? '').trim()) {
            errors[field] = message;
        }
    };

    if (step === 0) {
        required('name', 'Indiquez votre nom complet.');
        required('phone', 'Indiquez votre numéro de téléphone.');
        required('email', 'Indiquez votre adresse e-mail.');
        required('password', 'Choisissez un mot de passe.');
        required('neighborhood_id', 'Choisissez votre quartier.');
        required('address_landmarks', 'Indiquez votre adresse et des repères.');
        if (data.password && data.password !== data.password_confirmation) {
            errors.password = 'Les deux mots de passe ne correspondent pas.';
        }
    }

    if (step === 1) {
        required('vehicle_type', 'Choisissez votre type de véhicule.');
        if (vehicle?.requires_license) {
            required('vehicle_brand', 'Indiquez la marque et le modèle du véhicule.');
            required('plate_number', 'Indiquez le numéro de plaque d’immatriculation.');
            required('license_number', 'Indiquez le numéro de votre permis de conduire.');
        }
        required('base_neighborhood_id', 'Choisissez le quartier où vous comptez travailler.');
    }

    return errors;
}

export default function Delivery({ neighborhoods, vehicleTypes, documentTypes, requiredDocuments }) {
    const { neighborhoodId } = useNeighborhood();
    const preselected = neighborhoods.some((n) => n.id === neighborhoodId) ? String(neighborhoodId) : '';

    // Un seul formulaire pour les 3 étapes : revenir en arrière ne perd rien (fichiers compris).
    const form = useForm({
        name: '',
        phone: '',
        email: '',
        password: '',
        password_confirmation: '',
        neighborhood_id: preselected,
        address_landmarks: '',
        vehicle_type: '',
        vehicle_brand: '',
        plate_number: '',
        license_number: '',
        base_neighborhood_id: preselected,
        documents: {},
    });
    const { data, setData, post, processing, progress, errors, setError, clearErrors, transform } = form;

    const findVehicle = (value) => vehicleTypes.find((type) => type.value === value);
    const { step, goTo, next, checking, topRef, goToErrors } = useRegistrationSteps({
        form,
        stepFields: STEP_FIELDS,
        checkUrl: route('register.delivery.check'),
        validate: (current, values) => validateStep(current, values, findVehicle(values.vehicle_type)),
    });

    const vehicle = vehicleTypes.find((type) => type.value === data.vehicle_type);
    const required = data.vehicle_type ? requiredDocuments[data.vehicle_type] : [];
    const missing = required.filter((type) => !data.documents[type]);

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

        // Seuls les documents demandés pour ce véhicule sont envoyés.
        transform((form) => ({
            ...form,
            documents: Object.fromEntries(required.map((type) => [type, form.documents[type]])),
        }));

        post(route('register.delivery.store'), {
            forceFormData: true,
            onError: goToErrors,
        });
    };

    return (
        <GuestLayout
            width="lg"
            title="Devenir livreur Gogab"
            subtitle="Trois étapes, environ 5 minutes. Gardez votre CIN, votre permis et votre véhicule à portée de main."
            footer={
                <>
                    Déjà inscrit ?{' '}
                    <Link href={route('login')} className="font-semibold text-primary-700 hover:underline">
                        Se connecter
                    </Link>
                </>
            }
        >
            <Head title="Inscription livreur" />

            <div ref={topRef} className="scroll-mt-20">
                <Stepper steps={STEPS} current={step} className="mb-6" />
            </div>

            <form onSubmit={submit} noValidate className="space-y-5">
                <FormErrors errors={errors} />

                {step === 0 && (
                    <fieldset className="space-y-4">
                        <legend className="sr-only">Vos informations</legend>
                        <Input id="name" label="Nom complet" icon={UserRound} value={data.name} onChange={(e) => update('name', e.target.value)} error={errors.name} autoComplete="name" required isFocused />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Input id="phone" type="tel" label="Téléphone" icon={Phone} placeholder="077 12 34 56" value={data.phone} onChange={(e) => update('phone', e.target.value)} error={errors.phone} autoComplete="tel" inputMode="tel" required />
                            <Input id="email" type="email" label="E-mail" icon={Mail} value={data.email} onChange={(e) => update('email', e.target.value)} error={errors.email} autoComplete="email" inputMode="email" required />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <PasswordInput id="password" label="Mot de passe" hint="8 caractères minimum." value={data.password} onChange={(e) => update('password', e.target.value)} error={errors.password} autoComplete="new-password" required />
                            <PasswordInput id="password_confirmation" label="Confirmer le mot de passe" value={data.password_confirmation} onChange={(e) => update('password_confirmation', e.target.value)} error={errors.password_confirmation} autoComplete="new-password" required />
                        </div>
                        <NeighborhoodSelect id="neighborhood_id" label="Quartier de résidence" neighborhoods={neighborhoods} value={data.neighborhood_id} onChange={(e) => update('neighborhood_id', e.target.value)} error={errors.neighborhood_id} required />
                        <Textarea id="address_landmarks" label="Repères d’adresse" placeholder="Ex. : derrière la station Total, maison jaune au portail noir" rows={3} maxLength={500} value={data.address_landmarks} onChange={(e) => update('address_landmarks', e.target.value)} error={errors.address_landmarks} required />
                    </fieldset>
                )}

                {step === 1 && (
                    <fieldset className="space-y-5">
                        <legend className="sr-only">Votre véhicule</legend>
                        <div>
                            <p id="vehicle_type-label" className="mb-2 text-sm font-medium text-gray-800">
                                Type de véhicule <span className="text-danger-600" aria-hidden="true">*</span>
                            </p>
                            <div role="radiogroup" aria-labelledby="vehicle_type-label" className="grid grid-cols-3 gap-2 sm:gap-3">
                                {vehicleTypes.map((type, index) => {
                                    const Icon = VEHICLE_ICONS[type.value] ?? Car;
                                    const checked = data.vehicle_type === type.value;

                                    return (
                                        <label
                                            key={type.value}
                                            className={cn(
                                                'flex min-h-[5.5rem] cursor-pointer flex-col items-center justify-center gap-1.5 rounded-2xl p-3 text-center text-sm font-semibold ring-1 transition focus-within:ring-2 focus-within:ring-primary',
                                                checked ? 'bg-primary-50 text-primary-800 ring-2 ring-primary' : 'bg-white text-gray-700 ring-gray-200 hover:bg-gray-50',
                                            )}
                                        >
                                            <input
                                                type="radio"
                                                name="vehicle_type"
                                                id={index === 0 ? 'vehicle_type' : undefined}
                                                value={type.value}
                                                checked={checked}
                                                onChange={() => update('vehicle_type', type.value)}
                                                className="sr-only"
                                            />
                                            <Icon className="h-7 w-7" aria-hidden="true" />
                                            {type.label}
                                        </label>
                                    );
                                })}
                            </div>
                            {errors.vehicle_type && <p className="mt-1.5 text-sm text-danger-700">{errors.vehicle_type}</p>}
                        </div>

                        {vehicle && (
                            <>
                                <Input id="vehicle_brand" label={vehicle.requires_license ? 'Marque et modèle' : 'Marque et modèle (facultatif)'} placeholder={vehicle.value === 'car' ? 'Ex. : Toyota Corolla' : vehicle.value === 'moto' ? 'Ex. : Yamaha Crypton' : 'Ex. : VTT Btwin'} value={data.vehicle_brand} onChange={(e) => update('vehicle_brand', e.target.value)} error={errors.vehicle_brand} required={vehicle.requires_license} />
                                {vehicle.requires_license ? (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Input id="plate_number" label="Plaque d’immatriculation" icon={Hash} placeholder="Ex. : GA-1234-LBV" value={data.plate_number} onChange={(e) => update('plate_number', e.target.value.toUpperCase())} error={errors.plate_number} autoCapitalize="characters" required />
                                        <Input id="license_number" label="Numéro de permis" icon={IdCard} value={data.license_number} onChange={(e) => update('license_number', e.target.value)} error={errors.license_number} required />
                                    </div>
                                ) : (
                                    <p className="rounded-2xl bg-secondary-50 p-3 text-sm text-secondary-800">
                                        À vélo, ni plaque ni permis ne sont demandés.
                                    </p>
                                )}
                            </>
                        )}

                        <NeighborhoodSelect id="base_neighborhood_id" label="Quartier de base (zone d’activité)" hint="Vous recevrez en priorité les courses des commerces de cette zone." neighborhoods={neighborhoods} value={data.base_neighborhood_id} onChange={(e) => update('base_neighborhood_id', e.target.value)} error={errors.base_neighborhood_id} required />
                    </fieldset>
                )}

                {step === 2 && (
                    <fieldset className="space-y-4">
                        <legend className="text-sm text-gray-600">
                            {required.length} documents demandés pour un livreur {VEHICLE_PHRASES[data.vehicle_type]}. Des photos nettes, prises à la lumière du jour, accélèrent la validation.
                        </legend>
                        {required.map((type) => (
                            <DocumentUploader
                                key={type}
                                spec={documentTypes[type]}
                                value={data.documents[type] ?? null}
                                onChange={(file) => setDocument(type, file)}
                                error={errors[`documents.${type}`]}
                            />
                        ))}
                    </fieldset>
                )}

                {step === 2 && missing.length > 0 && (
                    <div role="status" className="flex items-start gap-2 rounded-2xl bg-accent-50 p-3 text-sm text-secondary-900 ring-1 ring-accent-200">
                        <CircleAlert className="mt-0.5 h-4 w-4 shrink-0 text-accent-700" aria-hidden="true" />
                        <p>
                            <span className="font-semibold">
                                {missing.length} document{missing.length > 1 ? 's' : ''} manquant{missing.length > 1 ? 's' : ''} :
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
                            {processing && progress ? `Envoi… ${progress.percentage} %` : 'Envoyer mon dossier'}
                        </Button>
                    )}
                </div>
            </form>
        </GuestLayout>
    );
}
