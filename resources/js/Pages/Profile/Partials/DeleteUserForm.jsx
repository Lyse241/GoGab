import Button from '@/Components/UI/Button';
import Input from '@/Components/UI/Input';
import Modal from '@/Components/UI/Modal';
import { useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';

export default function DeleteUserForm({ className = '' }) {
    const [confirmingUserDeletion, setConfirmingUserDeletion] = useState(false);
    const passwordInput = useRef();

    const {
        data,
        setData,
        delete: destroy,
        processing,
        reset,
        errors,
        clearErrors,
    } = useForm({
        password: '',
    });

    const confirmUserDeletion = () => {
        setConfirmingUserDeletion(true);
    };

    const deleteUser = (e) => {
        e.preventDefault();

        destroy(route('profile.destroy'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
            onError: () => passwordInput.current.focus(),
            onFinish: () => reset(),
        });
    };

    const closeModal = () => {
        setConfirmingUserDeletion(false);

        clearErrors();
        reset();
    };

    return (
        <section className={`space-y-6 ${className}`}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">
                    Supprimer mon compte
                </h2>

                <p className="mt-1 text-sm text-gray-600">
                    La suppression de votre compte est définitive : toutes vos données seront effacées.
                </p>
            </header>

            <Button variant="danger" onClick={confirmUserDeletion}>
                Supprimer mon compte
            </Button>

            <Modal
                open={confirmingUserDeletion}
                onClose={closeModal}
                closeable={!processing}
                title="Voulez-vous vraiment supprimer votre compte ?"
                description="Toutes vos données seront définitivement effacées. Saisissez votre mot de passe pour confirmer."
                footer={
                    <>
                        <Button variant="outline" onClick={closeModal} disabled={processing}>
                            Annuler
                        </Button>
                        <Button type="submit" form="delete-user-form" variant="danger" loading={processing}>
                            Supprimer mon compte
                        </Button>
                    </>
                }
            >
                <form id="delete-user-form" onSubmit={deleteUser}>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        label="Mot de passe"
                        ref={passwordInput}
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        error={errors.password}
                        autoComplete="current-password"
                        isFocused
                    />
                </form>
            </Modal>
        </section>
    );
}
