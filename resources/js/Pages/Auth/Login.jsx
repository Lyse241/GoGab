import Button from '@/Components/UI/Button';
import Checkbox from '@/Components/UI/Checkbox';
import Input from '@/Components/UI/Input';
import PasswordInput from '@/Components/UI/PasswordInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { CircleCheck, Mail } from 'lucide-react';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout
            title="Connexion"
            subtitle="Heureux de vous revoir sur Gogab."
            footer={
                <>
                    Pas encore de compte ?{' '}
                    <Link href={route('register')} className="tap-area font-semibold text-primary-700 hover:underline">
                        Créer un compte
                    </Link>
                </>
            }
        >
            <Head title="Connexion" />

            {status && (
                <div role="status" className="mb-5 flex items-start gap-2 rounded-2xl bg-primary-50 p-3 text-sm text-primary-800">
                    <CircleCheck className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-4" noValidate>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    label="E-mail"
                    icon={Mail}
                    value={data.email}
                    autoComplete="username"
                    inputMode="email"
                    isFocused
                    required
                    onChange={(e) => setData('email', e.target.value)}
                    error={errors.email}
                />

                <PasswordInput
                    id="password"
                    name="password"
                    label="Mot de passe"
                    value={data.password}
                    autoComplete="current-password"
                    required
                    onChange={(e) => setData('password', e.target.value)}
                    error={errors.password}
                />

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <Checkbox
                        id="remember"
                        name="remember"
                        label="Se souvenir de moi"
                        checked={data.remember}
                        onChange={(e) => setData('remember', e.target.checked)}
                    />
                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="inline-flex min-h-tap items-center text-sm font-medium text-secondary hover:underline"
                        >
                            Mot de passe oublié ?
                        </Link>
                    )}
                </div>

                <Button type="submit" size="lg" fullWidth loading={processing}>
                    Se connecter
                </Button>
            </form>
        </GuestLayout>
    );
}
