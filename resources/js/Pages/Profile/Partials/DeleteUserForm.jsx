import Button from '@/Components/UI/Button';
import Card, { CardHeader } from '@/Components/UI/Card';
import Modal from '@/Components/UI/Modal';
import PasswordInput from '@/Components/UI/PasswordInput';
import { useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';

/**
 * Suppression du compte, confirmée par le mot de passe. Les données personnelles sont effacées ;
 * les commandes passées restent dans l'historique des autres parties (sans votre nom).
 */
export default function DeleteUserForm() {
    const [open, setOpen] = useState(false);
    const { data, setData, delete: destroy, processing, reset, errors, clearErrors } = useForm({ password: '' });

    const close = () => {
        if (processing) {
            return;
        }
        setOpen(false);
        clearErrors();
        reset();
    };

    const submit = (event) => {
        event.preventDefault();
        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onError: () => document.getElementById('delete_password')?.focus(),
        });
    };

    return (
        <Card>
            <CardHeader title="Supprimer mon compte" description="Vos informations personnelles et vos documents seront effacés. C’est définitif." />
            <Button variant="danger" icon={Trash2} onClick={() => setOpen(true)}>
                Supprimer mon compte
            </Button>

            <Modal
                open={open}
                onClose={close}
                closeable={!processing}
                size="sm"
                title="Supprimer votre compte ?"
                description="Vos commandes passées restent dans l’historique des commerces et des livreurs, sans votre nom ni votre téléphone. Impossible si une commande est en cours."
                footer={
                    <>
                        <Button variant="outline" onClick={close} disabled={processing}>
                            Annuler
                        </Button>
                        <Button type="submit" form="delete-account" variant="danger" icon={Trash2} loading={processing}>
                            Supprimer définitivement
                        </Button>
                    </>
                }
            >
                <form id="delete-account" onSubmit={submit} noValidate>
                    <PasswordInput
                        id="delete_password"
                        label="Mot de passe"
                        required
                        autoComplete="current-password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        error={errors.password}
                    />
                </form>
            </Modal>
        </Card>
    );
}
