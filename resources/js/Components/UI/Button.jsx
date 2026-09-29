import Spinner from '@/Components/UI/Spinner';
import { cn } from '@/utils/cn';
import { Link } from '@inertiajs/react';
import { forwardRef } from 'react';

const variants = {
    primary:
        'bg-primary-600 text-white shadow-sm hover:bg-primary-700 active:bg-primary-800 focus-visible:ring-primary',
    secondary:
        'bg-secondary text-white shadow-sm hover:bg-secondary-800 active:bg-secondary-900 focus-visible:ring-secondary',
    accent:
        'bg-accent text-secondary-900 shadow-sm hover:bg-accent-300 active:bg-accent-500 focus-visible:ring-accent',
    outline:
        'border border-gray-300 bg-white text-gray-800 hover:bg-gray-50 active:bg-gray-100 focus-visible:ring-secondary',
    ghost:
        'text-secondary hover:bg-secondary-50 active:bg-secondary-100 focus-visible:ring-secondary',
    danger:
        'bg-danger-600 text-white shadow-sm hover:bg-danger-700 active:bg-danger-800 focus-visible:ring-danger',
};

const sizes = {
    sm: 'h-9 gap-1.5 px-3.5 text-sm',
    md: 'h-11 gap-2 px-5 text-sm',
    lg: 'h-[3.25rem] gap-2 px-6 text-base',
    icon: 'h-11 w-11',
    'icon-sm': 'h-9 w-9',
};

const iconSizes = {
    sm: 'h-4 w-4',
    md: 'h-5 w-5',
    lg: 'h-5 w-5',
    icon: 'h-5 w-5',
    'icon-sm': 'h-4 w-4',
};

/**
 * Bouton de la charte. Rendu en lien Inertia quand `href` est fourni.
 *
 * - variant : primary | secondary | accent | outline | ghost | danger
 * - size    : sm | md (44 px, cible tactile) | lg | icon | icon-sm
 * - loading : spinner + clic bloqué (évite les doubles envois)
 * - icon / iconRight : composant lucide-react
 */
const Button = forwardRef(function Button(
    {
        variant = 'primary',
        size = 'md',
        loading = false,
        disabled = false,
        fullWidth = false,
        icon: Icon,
        iconRight: IconRight,
        href,
        type = 'button',
        className,
        children,
        ...props
    },
    ref,
) {
    const isDisabled = disabled || loading;
    const classes = cn(
        'inline-flex shrink-0 select-none items-center justify-center whitespace-nowrap rounded-full font-semibold transition duration-150',
        'focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2',
        variants[variant],
        sizes[size],
        fullWidth && 'w-full',
        isDisabled && 'pointer-events-none opacity-60',
        className,
    );

    const content = (
        <>
            {loading ? (
                <Spinner className={iconSizes[size]} />
            ) : (
                Icon && <Icon className={iconSizes[size]} aria-hidden="true" />
            )}
            {children}
            {IconRight && !loading && <IconRight className={iconSizes[size]} aria-hidden="true" />}
        </>
    );

    if (href && !isDisabled) {
        return (
            <Link ref={ref} href={href} className={classes} {...props}>
                {content}
            </Link>
        );
    }

    return (
        <button
            ref={ref}
            type={type}
            disabled={isDisabled}
            aria-busy={loading || undefined}
            className={classes}
            {...props}
        >
            {content}
        </button>
    );
});

export default Button;
