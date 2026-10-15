import Select from '@/Components/UI/Select';

/**
 * Rayon de couverture d'un livreur : il voit les courses des commerces situés à moins de X km
 * (à vol d'oiseau) de son quartier de départ.
 */
export default function CoverageSelect({ options = [5, 10, 15, 25], value, onChange, neighborhood, ...props }) {
    return (
        <Select
            label="Rayon de couverture"
            value={String(value ?? '')}
            onChange={(event) => onChange(Number(event.target.value))}
            hint={`Vous recevez les courses des commerces situés à moins de ${value ?? 10} km${neighborhood ? ` de ${neighborhood}` : ' de votre quartier de départ'}. Un rayon plus grand = plus de courses, mais des trajets plus longs.`}
            {...props}
        >
            {options.map((km) => (
                <option key={km} value={km}>
                    {km} km{km === 5 ? ' (quartiers voisins)' : km === 10 ? ' (Libreville jusqu’à Owendo)' : km === 15 ? ' (presque jusqu’à Akanda)' : ' (tout le Grand Libreville)'}
                </option>
            ))}
        </Select>
    );
}
