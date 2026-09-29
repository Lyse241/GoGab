import Field, { controlClasses } from '@/Components/UI/Field';
import { cn } from '@/utils/cn';
import { forwardRef } from 'react';

/**
 * Zone de texte multiligne. `maxLength` affiche un compteur de caractères.
 */
const Textarea = forwardRef(function Textarea(
    { id, label, hint, error, required, rows = 4, maxLength, value, className, wrapperClassName, ...props },
    ref,
) {
    const counter = maxLength ? `${String(value ?? '').length} / ${maxLength}` : null;

    return (
        <Field
            id={id}
            label={label}
            hint={hint ?? counter}
            error={error}
            required={required}
            className={wrapperClassName}
        >
            {(describedBy) => (
                <textarea
                    ref={ref}
                    id={id}
                    rows={rows}
                    value={value}
                    maxLength={maxLength}
                    required={required}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={describedBy}
                    className={controlClasses(error, cn('px-3.5 py-2.5', className))}
                    {...props}
                />
            )}
        </Field>
    );
});

export default Textarea;
