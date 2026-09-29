import Spinner from '@/Components/UI/Spinner';
import { cn } from '@/utils/cn';
import { Field, Label, Description, Switch as HeadlessSwitch } from '@headlessui/react';

const sizes = {
    md: { track: 'h-7 w-12', thumb: 'h-5 w-5', on: 'translate-x-6' },
    lg: { track: 'h-9 w-16', thumb: 'h-7 w-7', on: 'translate-x-8' },
};

/**
 * Interrupteur on / off accessible (rôle switch, clavier).
 *
 * - checked / onChange(bool) : état contrôlé
 * - label, description : texte associé (cliquable)
 * - loading : spinner dans le bouton, changement bloqué
 * - size : md | lg (grand interrupteur bien visible)
 */
export default function Switch({ checked, onChange, label, description, loading = false, disabled = false, size = 'md', className }) {
    const s = sizes[size];

    return (
        <Field disabled={disabled || loading} className={cn('flex items-center justify-between gap-4', className)}>
            {(label || description) && (
                <span className="min-w-0">
                    {label && <Label className="block cursor-pointer font-semibold text-secondary-900">{label}</Label>}
                    {description && <Description className="mt-0.5 block text-sm text-gray-600">{description}</Description>}
                </span>
            )}
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
                    {loading && <Spinner className="h-3.5 w-3.5 text-gray-500" />}
                </span>
            </HeadlessSwitch>
        </Field>
    );
}
