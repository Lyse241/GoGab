/**
 * Ancien composant Breeze conservé pour les pages d’authentification et de profil :
 * il délègue au design system. Préférer le prop `label` des champs UI pour tout nouveau code.
 */
import { Label } from '@/Components/UI/Field';

export default function InputLabel({ value, className, children, ...props }) {
    return (
        <Label className={className} {...props}>
            {value ?? children}
        </Label>
    );
}
