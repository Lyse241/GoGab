/**
 * Ancien composant Breeze conservé pour les pages d’authentification et de profil :
 * il délègue au design system. Préférer @/Components/UI/Button pour tout nouveau code.
 */
import Button from '@/Components/UI/Button';

export default function PrimaryButton({ processing = false, type = 'submit', ...props }) {
    return <Button type={type} loading={processing} {...props} />;
}
