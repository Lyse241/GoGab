import FormErrors, { focusFirstError } from '@/Components/FormErrors';
import NeighborhoodSelect from '@/Components/NeighborhoodSelect';
import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import PasswordInput from '@/Components/UI/PasswordInput';
import Textarea from '@/Components/UI/Textarea';
import { useNeighborhood } from '@/Contexts/NeighborhoodContext';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Mail, Phone, UserRound } from 'lucide-react';

const LANDMARKS_MAX = 500;

export default function Client({ neighborhoods }) {
    // Quartier déjà choisi dans le header : proposé par défaut.
    const { neighborhoodId } = useNeighborhood();
    const preselected = neighborhoods.some((n) => n.id === neighborhoodId) ? String(neighborhoodId) : '';

    const { data, setData, post, processing, errors, clearErrors, reset } = useForm({
        name: '',
        phone: '',
        email: '',
        password: '',
        password_confirmation: '',
        neighborhood_id: preselected,
        address_landmarks: '',
    });

    const update = (field, value) => {
        setData(field, value);
        clearErrors(field);
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('register.client.store'), {
            onError: focusFirstError,
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout
            width="md"
            title="Créer mon compte client"
            subtitle="Commandez auprès des commerces de votre quartier, livrés chez vous."
            footer={
                <>
                    Déjà inscrit ?{' '}
                    <Link href={route('login')} className="font-semibold text-primary-700 hover:underline">
                        Se connecter
                    </Link>
                </>
            }
        >
            <Head title="Inscription client" />

            <Link
                href={route('register')}
                className="-mt-2 mb-4 inline-flex min-h-tap items-center gap-1.5 text-sm font-medium text-secondary hover:underline"
            >
                <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                Changer de profil
            </Link>

            <form onSubmit={submit} className="space-y-5" noValidate>
                <FormErrors errors={errors} />

                <fieldset className="space-y-4">
                    <legend className="text-sm font-semibold uppercase tracking-wide text-gray-500">Vos informations</legend>
                    <Input
                        id="name"
                        label="Nom complet"
                        icon={UserRound}
                        value={data.name}
                        onChange={(e) => update('name', e.target.value)}
                        error={errors.name}
                        autoComplete="name"
                        required
                        isFocused
                    />
                    <Input
                        id="phone"
                        type="tel"
                        label="Téléphone"
                        icon={Phone}
                        value={data.phone}
                        onChange={(e) => update('phone', e.target.value)}
                        error={errors.phone}
                        hint="Le livreur vous appellera sur ce numéro."
                        placeholder="077 12 34 56"
                        autoComplete="tel"
                        inputMode="tel"
                        required
                    />
                    <Input
                        id="email"
                        type="email"
                        label="E-mail"
                        icon={Mail}
                        value={data.email}
                        onChange={(e) => update('email', e.target.value)}
                        error={errors.email}
                        autoComplete="email"
                        inputMode="email"
                        required
                    />
                    <PasswordInput
                        id="password"
                        label="Mot de passe"
                        value={data.password}
                        onChange={(e) => update('password', e.target.value)}
                        error={errors.password}
                        hint="8 caractères minimum."
                        autoComplete="new-password"
                        required
                    />
                    <PasswordInput
                        id="password_confirmation"
                        label="Confirmer le mot de passe"
                        value={data.password_confirmation}
                        onChange={(e) => update('password_confirmation', e.target.value)}
                        error={errors.password_confirmation}
                        autoComplete="new-password"
                        required
                    />
                </fieldset>

                <fieldset className="space-y-4">
                    <legend className="text-sm font-semibold uppercase tracking-wide text-gray-500">Adresse de livraison</legend>
                    <NeighborhoodSelect
                        id="neighborhood_id"
                        label="Quartier"
                        placeholder="Choisissez votre quartier"
                        neighborhoods={neighborhoods}
                        value={data.neighborhood_id}
                        onChange={(e) => update('neighborhood_id', e.target.value)}
                        error={errors.neighborhood_id}
                        required
                    />
                    <Textarea
                        id="address_landmarks"
                        label="Adresse et repères"
                        placeholder="Ex. : près de la pharmacie X, portail bleu, 2e maison à gauche"
                        rows={3}
                        maxLength={LANDMARKS_MAX}
                        value={data.address_landmarks}
                        onChange={(e) => update('address_landmarks', e.target.value)}
                        error={errors.address_landmarks}
                        required
                    />
                </fieldset>

                <Button type="submit" size="lg" fullWidth loading={processing}>
                    Créer mon compte
                </Button>

                <p className="text-center text-xs text-gray-500">
                    Votre compte sera vérifié par l’équipe Gogab. Vous pourrez parcourir les commerces en attendant.
                </p>
            </form>
        </GuestLayout>
    );
}
