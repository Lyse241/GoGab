/**
 * Ancien composant Breeze conservé pour les pages d’authentification et de profil :
 * il délègue au design system. Préférer @/Components/UI/Input pour tout nouveau code.
 */
import Input from '@/Components/UI/Input';
import { forwardRef } from 'react';

export default forwardRef(function TextInput(props, ref) {
    return <Input ref={ref} {...props} />;
});
