import Button from '@/Components/UI/Button';
import Card, { CardHeader } from '@/Components/UI/Card';
import PasswordInput from '@/Components/UI/PasswordInput';
import { focusFirstError } from '@/utils/focusFirstError';
import { useForm } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';

/**
 * Changement de mot de passe (mot de passe actuel obligatoire).
 */
export default function UpdatePasswordForm() {
    const { data, setData, errors, put, reset, processing } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (event) => {
        event.preventDefault();
        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                }
                if (errors.current_password) {
                    reset('current_password');
                }
                focusFirstError(errors);
            },
        });
    };

    return (
        <Card>
            <CardHeader title="Mot de passe" description="Utilisez un mot de passe long et difficile à deviner." />
            <form onSubmit={submit} noValidate className="space-y-4">
                <PasswordInput
                    id="current_password"
                    label="Mot de passe actuel"
                    required
                    autoComplete="current-password"
                    value={data.current_password}
                    onChange={(e) => setData('current_password', e.target.value)}
                    error={errors.current_password}
                />
                <div className="grid gap-4 sm:grid-cols-2">
                    <PasswordInput
                        id="password"
                        label="Nouveau mot de passe"
                        required
                        autoComplete="new-password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        error={errors.password}
                    />
                    <PasswordInput
                        id="password_confirmation"
                        label="Confirmation"
                        required
                        autoComplete="new-password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        error={errors.password_confirmation}
                    />
                </div>
                <Button type="submit" icon={KeyRound} loading={processing}>
                    Changer le mot de passe
                </Button>
            </form>
        </Card>
    );
}
