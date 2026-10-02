import AvailabilitySwitch from '@/Components/Delivery/AvailabilitySwitch';
import DashboardLayout from '@/Layouts/DashboardLayout';

/**
 * Layout de l'espace livreur (Mobile-First) : DashboardLayout (barre du bas Accueil, Offres,
 * En cours, Historique, Profil) avec, en tête de chaque page, le titre et le grand interrupteur
 * de disponibilité.
 *
 * - title : titre de la page ; subtitle : ligne d'information facultative
 * - availability : afficher l'interrupteur (défaut : oui)
 */
export default function DeliveryLayout({ title, subtitle, availability = true, actions, children }) {
    return (
        <DashboardLayout
            actions={actions}
            header={
                <div className="min-w-0">
                    <h1 className="truncate text-xl font-bold text-secondary-900">{title}</h1>
                    {subtitle && <p className="text-sm text-gray-500">{subtitle}</p>}
                </div>
            }
        >
            <div className="mx-auto max-w-2xl space-y-4 px-4 py-5 sm:px-6">
                {availability && <AvailabilitySwitch />}
                {children}
            </div>
        </DashboardLayout>
    );
}
