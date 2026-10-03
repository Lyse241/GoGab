import Input from '@/Components/UI/Input';
import { Eye, EyeOff, Lock } from 'lucide-react';
import { forwardRef, useState } from 'react';

/**
 * Champ mot de passe avec bouton « Afficher / Masquer » (évite les fautes de frappe sur mobile).
 * Accepte les mêmes props que Input.
 */
const PasswordInput = forwardRef(function PasswordInput({ className, ...props }, ref) {
    const [visible, setVisible] = useState(false);

    return (
        <div className="relative">
            <Input
                ref={ref}
                type={visible ? 'text' : 'password'}
                icon={Lock}
                className={`pr-12 ${className ?? ''}`}
                {...props}
            />
            <button
                type="button"
                onClick={() => setVisible((value) => !value)}
                aria-label={visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
                aria-pressed={visible}
                className={`tap-area absolute right-1 inline-flex h-9 w-9 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-gray-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary ${
                    // Centré sur le champ (h-11) : libellé text-sm + mb-1.5 = 26 px au-dessus.
                    props.label ? 'top-[1.875rem]' : 'top-1'
                }`}
            >
                {visible ? <EyeOff className="h-5 w-5" aria-hidden="true" /> : <Eye className="h-5 w-5" aria-hidden="true" />}
            </button>
        </div>
    );
});

export default PasswordInput;
