import Badge from '@/Components/UI/Badge';
import { usePage } from '@inertiajs/react';

/**
 * Badge de statut : valeur → libellé + couleur, identique sur toutes les pages.
 *
 * Les libellés et couleurs viennent du serveur (prop partagée `statuses`, construite
 * depuis les enums OrderStatus et AccountStatus) : aucune copie à maintenir côté React.
 *
 * - type : "order" (défaut) ou "account"
 * - status : valeur enregistrée (ex. "en_attente", "pending")
 */
export default function StatusBadge({ type = 'order', status, size = 'md', className }) {
    const { statuses } = usePage().props;
    const meta = statuses?.[type]?.[status];

    return (
        <Badge color={meta?.color ?? 'gray'} size={size} dot className={className}>
            {meta?.label ?? status}
        </Badge>
    );
}
