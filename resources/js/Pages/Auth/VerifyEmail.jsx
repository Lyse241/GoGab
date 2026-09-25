import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Vérification de l'e-mail" />

            <div className="mb-4 text-sm text-gray-600">
                Merci pour votre inscription ! Confirmez votre adresse e-mail en cliquant sur le lien que nous venons de vous envoyer. Vous ne l'avez pas reçu ? Nous pouvons vous en renvoyer un.
            </div>

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-sm font-medium text-primary-700">
                    Un nouveau lien de vérification a été envoyé à l'adresse e-mail indiquée lors de l'inscription.
                </div>
            )}

            <form onSubmit={submit}>
                <div className="mt-4 flex items-center justify-between">
                    <PrimaryButton processing={processing}>
                        Renvoyer l'e-mail
                    </PrimaryButton>

                    <Link
                        href={route('logout')}
                        method="post"
                        as="button"
                        className="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
                    >
                        Déconnexion
                    </Link>
                </div>
            </form>
        </GuestLayout>
    );
}
