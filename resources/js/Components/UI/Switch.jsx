import Spinner from '@/Components/UI/Spinner';
import { cn } from '@/utils/cn';
import { Field, Label, Description, Switch as HeadlessSwitch } from '@headlessui/react';

const sizes = {
    sm: { track: 'h-6 w-10', thumb: 'h-4 w-4', on: 'translate-x-4', spinner: 'h-3 w-3' },
    md: { track: 'h-7 w-12', thumb: 'h-5 w-5', on: 'translate-x-5', spinner: 'h-3.5 w-3.5' },
    lg: { track: 'h-9 w-16', thumb: 'h-7 w-7', on: 'translate-x-7', spinner: 'h-4 w-4' },
};

/**
 * Interrupteur on / off accessible (rôle switch, clavier).
 *
 * - checked / onChange(bool) : état contrôlé
 * - label, description : texte associé (cliquable)
 * - reverse : interrupteur à gauche du libellé (listes compactes)
 * - loading : spinner dans le bouton, changement bloqué
 * - size : sm | md | lg (grand interrupteur bien visible)
 */
export default function Switch({ checked, onChange, label, description, reverse = false, loading = false, disabled = false, size = 'md', className }) {
    const s = sizes[size];

    const text = (label || description) && (
        <span className="min-w-0">
            {label && <Label className="block cursor-pointer font-semibold text-secondary-900">{label}</Label>}
            {description && <Description className="mt-0.5 block text-sm text-gray-600">{description}</Description>}
        </span>
    );

    return (
        <Field
            disabled={disabled || loading}
            className={cn('flex items-center', reverse ? 'gap-2' : 'justify-between gap-4', className)}
        >
            {!reverse && text}
            <HeadlessSwitch
                checked={checked}
                onChange={onChange}
                aria-busy={loading || undefined}
                className={cn(
                    'relative inline-flex shrink-0 cursor-pointer items-center rounded-full p-1 transition-colors duration-200',
                    'focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2',
                    'data-[disabled]:cursor-not-allowed data-[disabled]:opacity-60',
                    checked ? 'bg-primary-600' : 'bg-gray-300',
                    s.track,
                )}
            >
                <span
                    aria-hidden="true"
                    className={cn(
                        'flex items-center justify-center rounded-full bg-white shadow transition-transform duration-200',
                        s.thumb,
                        checked ? s.on : 'translate-x-0',
                    )}
                >
                    {loading && <Spinner className={cn(s.spinner, 'text-gray-500')} />}
                </span>
            </HeadlessSwitch>
            {reverse && text}
        </Field>
    );
}
