import { cn } from '@/utils/cn';
import { CircleAlert } from 'lucide-react';

/**
 * Briques communes des champs de formulaire (Input, Textarea, Select, FileUpload).
 */
export function Label({ htmlFor, required = false, className, children }) {
    return (
        <label htmlFor={htmlFor} className={cn('block text-sm font-medium text-gray-800', className)}>
            {children}
            {required && (
                <span className="ml-0.5 text-danger-600" aria-hidden="true">
                    *
                </span>
            )}
        </label>
    );
}

export function FieldError({ id, message, className }) {
    if (!message) {
        return null;
    }

    return (
        <p id={id} className={cn('mt-1.5 flex items-start gap-1.5 text-sm text-danger-700', className)}>
            <CircleAlert className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
            <span>{message}</span>
        </p>
    );
}

export function Hint({ id, children, className }) {
    if (!children) {
        return null;
    }

    return (
        <p id={id} className={cn('mt-1.5 text-sm text-gray-500', className)}>
            {children}
        </p>
    );
}

/**
 * Libellé + contrôle + aide + erreur, reliés par aria-describedby.
 * `children(describedBy)` reçoit les ids à poser sur le contrôle.
 */
export default function Field({ id, label, hint, error, required, className, children }) {
    const hintId = hint ? `${id}-hint` : null;
    const errorId = error ? `${id}-error` : null;
    const describedBy = [errorId, hintId].filter(Boolean).join(' ') || undefined;

    return (
        <div className={className}>
            {label && (
                <Label htmlFor={id} required={required} className="mb-1.5">
                    {label}
                </Label>
            )}
            {children(describedBy)}
            {error ? <FieldError id={errorId} message={error} /> : <Hint id={hintId}>{hint}</Hint>}
        </div>
    );
}

/**
 * Classes partagées des champs texte (bordure, arrondi, focus, état erreur).
 */
export function controlClasses(error, className) {
    return cn(
        'block w-full rounded-xl border bg-white text-base text-gray-900 shadow-sm transition placeholder:text-gray-500 sm:text-sm',
        'focus:outline-none focus:ring-2 focus:ring-offset-0',
        'disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500',
        error
            ? 'border-danger-400 focus:border-danger-500 focus:ring-danger-200'
            : 'border-gray-300 focus:border-primary focus:ring-primary-100',
        className,
    );
}
