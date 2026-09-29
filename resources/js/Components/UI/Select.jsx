import Field, { controlClasses } from '@/Components/UI/Field';
import { cn } from '@/utils/cn';
import { forwardRef } from 'react';

/**
 * Liste déroulante native (meilleure ergonomie sur mobile).
 *
 * - options : [{ value, label }] ou enfants <option> / <optgroup>
 * - placeholder : première option vide, non sélectionnable une fois un choix fait
 */
const Select = forwardRef(function Select(
    { id, label, hint, error, required, options, placeholder, className, wrapperClassName, children, ...props },
    ref,
) {
    return (
        <Field id={id} label={label} hint={hint} error={error} required={required} className={wrapperClassName}>
            {(describedBy) => (
                <select
                    ref={ref}
                    id={id}
                    required={required}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={describedBy}
                    className={controlClasses(error, cn('h-11 pl-3.5 pr-10', className))}
                    {...props}
                >
                    {placeholder !== undefined && <option value="">{placeholder}</option>}
                    {options?.map((option) => (
                        <option key={option.value} value={option.value} disabled={option.disabled}>
                            {option.label}
                        </option>
                    ))}
                    {children}
                </select>
            )}
        </Field>
    );
});

export default Select;
