import Field, { controlClasses } from '@/Components/UI/Field';
import { cn } from '@/utils/cn';
import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react';

/**
 * Champ texte. Avec `label`, `hint` ou `error`, il s'entoure de son libellé et de ses messages.
 *
 * - icon : icône lucide affichée à gauche (ex. Search, Phone)
 * - suffix : texte à droite (ex. "FCFA")
 * - isFocused : focus automatique au montage
 */
const Input = forwardRef(function Input(
    {
        id,
        type = 'text',
        label,
        hint,
        error,
        required,
        icon: Icon,
        suffix,
        isFocused = false,
        className,
        wrapperClassName,
        ...props
    },
    ref,
) {
    const localRef = useRef(null);

    useImperativeHandle(ref, () => localRef.current);

    useEffect(() => {
        if (isFocused) {
            localRef.current?.focus();
        }
    }, [isFocused]);

    const control = (describedBy) => (
        <div className="relative">
            {Icon && (
                <Icon
                    className="pointer-events-none absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400"
                    aria-hidden="true"
                />
            )}
            <input
                ref={localRef}
                id={id}
                type={type}
                required={required}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy}
                className={controlClasses(
                    error,
                    cn('h-11 px-3.5', Icon && 'pl-11', suffix && 'pr-16', className),
                )}
                {...props}
            />
            {suffix && (
                <span className="pointer-events-none absolute right-3.5 top-1/2 -translate-y-1/2 text-sm font-medium text-gray-500">
                    {suffix}
                </span>
            )}
        </div>
    );

    if (!label && !hint && !error) {
        return <div className={wrapperClassName}>{control(undefined)}</div>;
    }

    return (
        <Field id={id} label={label} hint={hint} error={error} required={required} className={wrapperClassName}>
            {control}
        </Field>
    );
});

export default Input;
