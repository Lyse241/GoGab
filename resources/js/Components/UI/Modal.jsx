import { cn } from '@/utils/cn';
import {
    Dialog,
    DialogPanel,
    DialogTitle,
    Description,
    Transition,
    TransitionChild,
} from '@headlessui/react';
import { X } from 'lucide-react';

const sizes = {
    sm: 'sm:max-w-sm',
    md: 'sm:max-w-md',
    lg: 'sm:max-w-lg',
    xl: 'sm:max-w-xl',
    '2xl': 'sm:max-w-2xl',
};

/**
 * Fenêtre modale accessible (focus piégé, Échap, clic extérieur).
 * Sur mobile elle s'ouvre en « bottom sheet », centrée à partir de sm.
 *
 * - open / onClose : état contrôlé
 * - title, description : en-tête (annoncés aux lecteurs d'écran)
 * - footer : boutons d'action, collés en bas
 * - closeable=false : empêche la fermeture (ex. pendant un envoi)
 */
export default function Modal({
    open = false,
    onClose = () => {},
    title,
    description,
    footer,
    size = 'md',
    closeable = true,
    className,
    children,
}) {
    const close = () => {
        if (closeable) {
            onClose();
        }
    };

    return (
        <Transition show={open}>
            <Dialog onClose={close} className="relative z-50">
                <TransitionChild
                    enter="ease-out duration-200"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-150"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-secondary-900/50 backdrop-blur-[2px]" aria-hidden="true" />
                </TransitionChild>

                <div className="fixed inset-0 flex items-end justify-center overflow-y-auto sm:items-center sm:p-6">
                    <TransitionChild
                        enter="ease-out duration-200"
                        enterFrom="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
                        enterTo="opacity-100 translate-y-0 sm:scale-100"
                        leave="ease-in duration-150"
                        leaveFrom="opacity-100 translate-y-0 sm:scale-100"
                        leaveTo="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
                    >
                        <DialogPanel
                            className={cn(
                                'relative flex max-h-[92vh] w-full flex-col overflow-hidden rounded-t-3xl bg-white shadow-xl sm:rounded-3xl',
                                sizes[size],
                                className,
                            )}
                        >
                            {/* Poignée visuelle du bottom sheet */}
                            <div className="mx-auto mt-2.5 h-1.5 w-10 shrink-0 rounded-full bg-gray-200 sm:hidden" aria-hidden="true" />

                            {(title || closeable) && (
                                <div className="flex items-start justify-between gap-4 px-5 pb-1 pt-4 sm:px-6 sm:pt-6">
                                    <div className="min-w-0">
                                        {title && (
                                            <DialogTitle className="text-lg font-semibold text-secondary-900">
                                                {title}
                                            </DialogTitle>
                                        )}
                                        {description && (
                                            <Description className="mt-1 text-sm text-gray-600">
                                                {description}
                                            </Description>
                                        )}
                                    </div>
                                    {closeable && (
                                        <button
                                            type="button"
                                            onClick={close}
                                            className="-mr-2 -mt-1 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-gray-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
                                            aria-label="Fermer"
                                        >
                                            <X className="h-5 w-5" aria-hidden="true" />
                                        </button>
                                    )}
                                </div>
                            )}

                            <div className="overflow-y-auto px-5 py-4 sm:px-6">{children}</div>

                            {footer && (
                                <div className="flex flex-col-reverse gap-2 border-t border-gray-100 bg-gray-50/60 px-5 py-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:flex-row sm:justify-end sm:px-6">
                                    {footer}
                                </div>
                            )}
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </Dialog>
        </Transition>
    );
}
