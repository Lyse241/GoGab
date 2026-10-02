import DashboardLayout from '@/Layouts/DashboardLayout';
import DeliveryLayout from '@/Layouts/DeliveryLayout';
import PublicLayout from '@/Layouts/PublicLayout';
import { Head, usePage } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import DocumentsSection from './Partials/DocumentsSection';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

/**
 * Layout de l'espace du rôle : le client reste dans le catalogue, le livreur dans son espace
 * mobile, l'entreprise et l'admin dans leur tableau de bord.
 */
function RoleLayout({ role, children }) {
    if (role === 'client') {
        return (
            <PublicLayout search={false}>
                <div className="mx-auto max-w-2xl space-y-4 px-4 py-6 sm:px-6">
                    <h1 className="text-2xl font-bold text-secondary-900">Mon profil</h1>
                    {children}
                </div>
            </PublicLayout>
        );
    }

    if (role === 'delivery') {
        return (
            <DeliveryLayout title="Mon compte" availability={false}>
                {children}
            </DeliveryLayout>
        );
    }

    return (
        <DashboardLayout header={<h1 className="text-xl font-bold text-secondary-900">Mon profil</h1>}>
            <div className="mx-auto max-w-2xl space-y-4 px-4 py-6 sm:px-6 lg:px-8">{children}</div>
        </DashboardLayout>
    );
}

/**
 * « Mon profil » (tous les rôles) : informations, mot de passe, documents (livreur,
 * entreprise), suppression du compte.
 */
export default function Edit({ mustVerifyEmail, status, profile, neighborhoods, documents }) {
    const { auth } = usePage().props;

    return (
        <RoleLayout role={auth.role}>
            <Head title="Mon profil" />

            <UpdateProfileInformationForm
                profile={profile}
                neighborhoods={neighborhoods}
                mustVerifyEmail={mustVerifyEmail}
                status={status}
                emailVerified={Boolean(auth.user.email_verified_at)}
            />
            {documents && <DocumentsSection documents={documents} />}
            <UpdatePasswordForm />
            <DeleteUserForm />
        </RoleLayout>
    );
}
