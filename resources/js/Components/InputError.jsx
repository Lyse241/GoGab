/**
 * Ancien composant Breeze conservé pour les pages d’authentification et de profil :
 * il délègue au design system. Préférer le prop `error` des champs UI pour tout nouveau code.
 */
import { FieldError } from '@/Components/UI/Field';

export default function InputError({ message, className }) {
    return <FieldError message={message} className={className} />;
}
