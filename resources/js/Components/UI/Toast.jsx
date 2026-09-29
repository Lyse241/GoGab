import { cn } from '@/utils/cn';
import { router } from '@inertiajs/react';
import { CircleAlert, CircleCheck, Info, TriangleAlert, X } from 'lucide-react';
import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';

const variants = {
    success: { icon: CircleCheck, classes: 'border-l-primary', iconClasses: 'text-primary-600', duration: 4000 },
    error: { icon: CircleAlert, classes: 'border-l-danger-500', iconClasses: 'text-danger-600', duration: 7000 },
    warning: { icon: TriangleAlert, classes: 'border-l-accent', iconClasses: 'text-accent-600', duration: 6000 },
    info: { icon: Info, classes: 'border-l-secondary', iconClasses: 'text-secondary', duration: 5000 },
};

const ToastContext = createContext(null);

let nextId = 0;

/**
 * Une notification éphémère. Fermeture auto (plus longue pour les erreurs), en pause au survol.
 */
export function Toast({ toast, onDismiss }) {
    const variant = variants[toast.type] ?? variants.info;
    const Icon = variant.icon;
    const [paused, setPaused] = useState(false);

    useEffect(() => {
        if (paused) {
            return;
        }

        const timer = setTimeout(() => onDismiss(toast.id), toast.duration ?? variant.duration);

        return () => clearTimeout(timer);
    }, [paused, toast, variant.duration, onDismiss]);

    return (
        <div
            role={toast.type === 'error' ? 'alert' : 'status'}
            onMouseEnter={() => setPaused(true)}
            onMouseLeave={() => setPaused(false)}
            className={cn(
                'pointer-events-auto flex w-full animate-toast-in items-start gap-3 rounded-2xl border-l-4 bg-white p-4 shadow-card-hover ring-1 ring-gray-100',
                variant.classes,
            )}
        >
            <Icon className={cn('mt-0.5 h-5 w-5 shrink-0', variant.iconClasses)} aria-hidden="true" />
            <div className="min-w-0 flex-1 text-sm">
                {toast.title && <p className="font-semibold text-gray-900">{toast.title}</p>}
                <p className={cn('text-gray-700', toast.title && 'mt-0.5')}>{toast.message}</p>
            </div>
            <button
                type="button"
                onClick={() => onDismiss(toast.id)}
                className="-m-1.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                aria-label="Fermer la notification"
            >
                <X className="h-4 w-4" aria-hidden="true" />
            </button>
        </div>
    );
}

/**
 * Fournit useToast() et affiche automatiquement les messages flash Laravel
 * (`flash.success`, `flash.error`, `flash.warning`, `flash.info`) après chaque visite Inertia.
 *
 * À placer autour de l'App Inertia (app.jsx), avec `initialFlash` = flash de la première page.
 */
export function ToastProvider({ initialFlash, children }) {
    const [toasts, setToasts] = useState([]);

    const dismiss = useCallback((id) => setToasts((current) => current.filter((toast) => toast.id !== id)), []);

    const show = useCallback((type, message, options = {}) => {
        if (!message) {
            return;
        }

        const id = ++nextId;

        // 4 toasts au plus ; un message identique déjà affiché n'est pas dupliqué.
        setToasts((current) => [
            ...current.filter((toast) => !(toast.type === type && toast.message === message)).slice(-3),
            { id, type, message, ...options },
        ]);

        return id;
    }, []);

    const showFlash = useCallback(
        (flash) => Object.keys(variants).forEach((type) => show(type, flash?.[type])),
        [show],
    );

    const initialShown = useRef(false);

    useEffect(() => {
        if (!initialShown.current) {
            initialShown.current = true;
            showFlash(initialFlash);
        }

        return router.on('success', (event) => showFlash(event.detail.page.props.flash));
    }, [initialFlash, showFlash]);

    const value = useMemo(
        () => ({
            show,
            dismiss,
            success: (message, options) => show('success', message, options),
            error: (message, options) => show('error', message, options),
            warning: (message, options) => show('warning', message, options),
            info: (message, options) => show('info', message, options),
        }),
        [show, dismiss],
    );

    return (
        <ToastContext.Provider value={value}>
            {children}
            {/* En haut sur mobile (la barre du bas reste libre), en bas à droite sur desktop. */}
            <div
                aria-live="polite"
                className="pointer-events-none fixed inset-x-3 top-3 z-[60] flex flex-col gap-2 sm:inset-x-auto sm:bottom-6 sm:right-6 sm:top-auto sm:w-96"
            >
                {toasts.map((toast) => (
                    <Toast key={toast.id} toast={toast} onDismiss={dismiss} />
                ))}
            </div>
        </ToastContext.Provider>
    );
}

/**
 * const toast = useToast(); toast.success('Produit ajouté au panier');
 */
export function useToast() {
    const context = useContext(ToastContext);

    if (!context) {
        throw new Error('useToast doit être utilisé dans un <ToastProvider>.');
    }

    return context;
}
