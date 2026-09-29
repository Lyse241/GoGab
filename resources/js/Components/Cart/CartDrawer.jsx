import CartList, { StoreThumb } from '@/Components/Cart/CartList';
import CartPanel from '@/Components/Cart/CartPanel';
import Button from '@/Components/UI/Button';
import { useCart } from '@/Contexts/CartContext';
import { Dialog, DialogPanel, DialogTitle, Transition, TransitionChild } from '@headlessui/react';
import { ArrowLeft, ShoppingBag, X } from 'lucide-react';
import { useEffect } from 'react';

/**
 * Tiroir des paniers : panneau latéral à droite sur desktop, feuille montante sur mobile.
 * Affiche la liste des paniers (un par commerce) ou le panier d'UN commerce.
 * Ouvert via useCart().openCarts() / openCart(storeId).
 */
export default function CartDrawer() {
    const { carts, drawer, cartOf, openCart, openCarts, closeDrawer } = useCart();
    const open = drawer !== null;
    const cart = typeof drawer === 'number' ? cartOf(drawer) : null;
    const showList = drawer === 'list' || (typeof drawer === 'number' && !cart);

    // Le panier affiché vient d'être vidé : retour à la liste s'il en reste, sinon état vide.
    useEffect(() => {
        if (typeof drawer === 'number' && !cart && carts.length > 0) {
            openCarts();
        }
    }, [drawer, cart, carts.length, openCarts]);

    return (
        <Transition show={open}>
            <Dialog onClose={closeDrawer} className="relative z-50">
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

                <div className="fixed inset-0 flex items-end justify-end sm:items-stretch">
                    <TransitionChild
                        enter="ease-out duration-300"
                        enterFrom="translate-y-full sm:translate-y-0 sm:translate-x-full"
                        enterTo="translate-y-0 sm:translate-x-0"
                        leave="ease-in duration-200"
                        leaveFrom="translate-y-0 sm:translate-x-0"
                        leaveTo="translate-y-full sm:translate-y-0 sm:translate-x-full"
                    >
                        <DialogPanel className="flex max-h-[92vh] w-full flex-col overflow-hidden rounded-t-3xl bg-gray-50 shadow-xl sm:h-full sm:max-h-none sm:w-[26rem] sm:rounded-none sm:rounded-l-3xl">
                            <div className="mx-auto mt-2.5 h-1.5 w-10 shrink-0 rounded-full bg-gray-300 sm:hidden" aria-hidden="true" />

                            <header className="flex items-center gap-3 border-b border-gray-100 bg-white px-4 py-3 sm:px-5 sm:py-4">
                                {cart && carts.length > 1 && (
                                    <button
                                        type="button"
                                        onClick={openCarts}
                                        className="-ml-2 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-gray-600 hover:bg-gray-100"
                                        aria-label="Tous mes paniers"
                                    >
                                        <ArrowLeft className="h-5 w-5" aria-hidden="true" />
                                    </button>
                                )}
                                {cart && <StoreThumb store={cart.store} className="h-10 w-10" />}
                                <DialogTitle className="min-w-0 flex-1 truncate text-lg font-bold text-secondary-900">
                                    {cart ? cart.store.name : 'Mes paniers'}
                                </DialogTitle>
                                <button
                                    type="button"
                                    onClick={closeDrawer}
                                    className="-mr-2 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100"
                                    aria-label="Fermer"
                                >
                                    <X className="h-5 w-5" aria-hidden="true" />
                                </button>
                            </header>

                            <div className="flex-1 overflow-y-auto px-4 py-4 pb-[max(1rem,env(safe-area-inset-bottom))] sm:px-5">
                                {carts.length === 0 ? (
                                    <div className="flex flex-col items-center px-4 py-10 text-center">
                                        <span className="flex h-16 w-16 items-center justify-center rounded-full bg-primary-50 text-primary-600">
                                            <ShoppingBag className="h-8 w-8" aria-hidden="true" />
                                        </span>
                                        <p className="mt-4 font-semibold text-secondary-900">Votre panier est vide</p>
                                        <p className="mt-1 text-sm text-gray-500">
                                            Parcourez les commerces de Libreville et ajoutez vos premiers articles.
                                        </p>
                                        <Button href={route('home')} onClick={closeDrawer} className="mt-6">
                                            Voir les commerces
                                        </Button>
                                    </div>
                                ) : cart ? (
                                    <CartPanel key={cart.store.id} cart={cart} onNavigate={closeDrawer} />
                                ) : (
                                    showList && (
                                        <>
                                            <p className="mb-3 text-sm text-gray-600">
                                                Un panier par commerce : chacun se commande séparément.
                                            </p>
                                            <CartList carts={carts} onOpen={openCart} />
                                        </>
                                    )
                                )}
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </Dialog>
        </Transition>
    );
}
