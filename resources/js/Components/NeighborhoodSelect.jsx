import Select from '@/Components/UI/Select';
import { useMemo } from 'react';

const ZONE_ORDER = ['Nord', 'Centre', 'Est', 'Sud'];

/**
 * Liste déroulante des quartiers, regroupés par zone de Libreville (Nord, Centre, Est, Sud).
 * Accepte les mêmes props que UI/Select (id, label, hint, error, value, onChange…).
 */
export default function NeighborhoodSelect({ neighborhoods, placeholder = 'Choisissez un quartier', ...props }) {
    const zones = useMemo(() => {
        const groups = {};
        neighborhoods.forEach((neighborhood) => (groups[neighborhood.zone ?? 'Autres'] ??= []).push(neighborhood));

        return Object.entries(groups).sort(
            ([a], [b]) => (ZONE_ORDER.indexOf(a) + 1 || 99) - (ZONE_ORDER.indexOf(b) + 1 || 99),
        );
    }, [neighborhoods]);

    return (
        <Select placeholder={placeholder} {...props}>
            {zones.map(([zone, items]) => (
                <optgroup key={zone} label={`Zone ${zone}`}>
                    {items.map((neighborhood) => (
                        <option key={neighborhood.id} value={neighborhood.id}>
                            {neighborhood.name}
                        </option>
                    ))}
                </optgroup>
            ))}
        </Select>
    );
}
