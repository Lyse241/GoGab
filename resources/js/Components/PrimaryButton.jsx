import Spinner from '@/Components/Spinner';

/**
 * Bouton principal. `processing` : affiche un spinner et bloque les doubles clics.
 */
export default function PrimaryButton({
    className = '',
    disabled,
    processing = false,
    children,
    ...props
}) {
    const isDisabled = disabled || processing;

    return (
        <button
            {...props}
            aria-busy={processing}
            className={
                `inline-flex items-center justify-center gap-2 rounded-full border border-transparent bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white transition duration-150 ease-in-out hover:bg-primary-700 focus:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 active:bg-primary-800 ${
                    isDisabled ? 'cursor-not-allowed opacity-60' : ''
                } ` + className
            }
            disabled={isDisabled}
        >
            {processing && <Spinner />}
            {children}
        </button>
    );
}
