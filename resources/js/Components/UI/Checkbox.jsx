import { FieldError } from '@/Components/UI/Field';
import { cn } from '@/utils/cn';
import { forwardRef } from 'react';

/**
 * Case à cocher avec libellé cliquable (zone tactile de 44 px) et description facultative.
 */
const Checkbox = forwardRef(function Checkbox(
    { id, label, description, error, className, ...props },
    ref,
) {
    const errorId = error ? `${id}-error` : undefined;

    const box = (
        <input
            ref={ref}
            id={id}
            type="checkbox"
            aria-invalid={error ? true : undefined}
            aria-describedby={errorId}
            className={cn(
                'h-5 w-5 shrink-0 cursor-pointer rounded-md border-gray-300 text-primary-600 shadow-sm',
                'focus:ring-2 focus:ring-primary focus:ring-offset-0 disabled:cursor-not-allowed disabled:opacity-50',
                error && 'border-danger-400',
                !label && className,
            )}
            {...props}
        />
    );

    if (!label) {
        return box;
    }

    return (
        <div className={className}>
            <label htmlFor={id} className="flex min-h-tap cursor-pointer items-start gap-3 py-2.5">
                <span className="flex h-6 items-center">{box}</span>
                <span className="text-sm">
                    <span className="font-medium text-gray-900">{label}</span>
                    {description && <span className="mt-0.5 block text-gray-500">{description}</span>}
                </span>
            </label>
            <FieldError id={errorId} message={error} className="mt-0" />
        </div>
    );
});

export default Checkbox;
