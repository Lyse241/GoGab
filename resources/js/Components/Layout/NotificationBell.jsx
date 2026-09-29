import NotificationItem from '@/Components/NotificationItem';
import Skeleton from '@/Components/UI/Skeleton';
import { useNotifications } from '@/Contexts/NotificationsContext';
import { cn } from '@/utils/cn';
import { Popover, PopoverButton, PopoverPanel } from '@headlessui/react';
import { Link } from '@inertiajs/react';
import { Bell, BellOff, CheckCheck } from 'lucide-react';

/**
 * Cloche du header : badge des non lues + menu des 10 dernières notifications.
 * Les données viennent de NotificationsProvider (rafraîchies toutes les 15 s).
 */
export default function NotificationBell({ className }) {
    const { enabled, unreadCount, items, loaded, markAllRead, open } = useNotifications();

    if (!enabled) {
        return null;
    }

    const label = unreadCount
        ? `Notifications, ${unreadCount} non lue${unreadCount > 1 ? 's' : ''}`
        : 'Notifications';

    return (
        <Popover className={cn('relative', className)}>
            <PopoverButton
                aria-label={label}
                className="relative inline-flex h-11 w-11 items-center justify-center rounded-full text-gray-700 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary data-[open]:bg-gray-100"
            >
                <Bell className="h-6 w-6" aria-hidden="true" />
                {unreadCount > 0 && (
                    <span className="absolute right-1 top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-accent px-1 text-[11px] font-bold leading-none text-secondary-900 ring-2 ring-white">
                        {unreadCount > 99 ? '99+' : unreadCount}
                    </span>
                )}
            </PopoverButton>

            <PopoverPanel
                transition
                anchor={{ to: 'bottom end', gap: 8, padding: 12 }}
                className="z-50 flex max-h-[min(32rem,calc(100vh-6rem))] w-[min(24rem,calc(100vw-1.5rem))] origin-top-right flex-col overflow-hidden rounded-2xl bg-white shadow-card-hover ring-1 ring-gray-100 transition duration-100 ease-out focus:outline-none data-[closed]:scale-95 data-[closed]:opacity-0"
            >
                {({ close }) => (
                    <>
                        <div className="flex items-center justify-between gap-2 border-b border-gray-100 px-4 py-3">
                            <p className="font-semibold text-secondary-900">
                                Notifications
                                {unreadCount > 0 && (
                                    <span className="ml-2 rounded-full bg-accent-100 px-2 py-0.5 text-xs font-bold text-secondary-900">
                                        {unreadCount}
                                    </span>
                                )}
                            </p>
                            <button
                                type="button"
                                onClick={markAllRead}
                                disabled={unreadCount === 0}
                                className="inline-flex min-h-9 items-center gap-1.5 rounded-full px-2 text-sm font-semibold text-secondary hover:bg-secondary-50 disabled:pointer-events-none disabled:text-gray-300"
                            >
                                <CheckCheck className="h-4 w-4" aria-hidden="true" />
                                Tout marquer comme lu
                            </button>
                        </div>

                        <div className="flex-1 overflow-y-auto p-1.5">
                            {!loaded ? (
                                <div className="space-y-3 p-3" aria-label="Chargement…">
                                    {[0, 1, 2].map((index) => (
                                        <div key={index} className="flex gap-3">
                                            <Skeleton className="h-10 w-10 rounded-full" />
                                            <div className="flex-1 space-y-2">
                                                <Skeleton className="h-3.5 w-1/2" />
                                                <Skeleton className="h-3 w-full" />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : items.length === 0 ? (
                                <div className="flex flex-col items-center px-6 py-10 text-center">
                                    <span className="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                                        <BellOff className="h-6 w-6" aria-hidden="true" />
                                    </span>
                                    <p className="mt-3 text-sm font-semibold text-gray-900">Aucune notification</p>
                                    <p className="mt-1 text-sm text-gray-500">
                                        Vous serez prévenu ici du suivi de vos commandes.
                                    </p>
                                </div>
                            ) : (
                                <ul className="space-y-0.5">
                                    {items.map((notification) => (
                                        <li key={notification.id}>
                                            <NotificationItem
                                                compact
                                                notification={notification}
                                                onClick={() => {
                                                    close();
                                                    open(notification);
                                                }}
                                            />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>

                        <Link
                            href={route('notifications.index')}
                            onClick={() => close()}
                            className="block border-t border-gray-100 px-4 py-3 text-center text-sm font-semibold text-primary-700 hover:bg-gray-50"
                        >
                            Voir toutes les notifications
                        </Link>
                    </>
                )}
            </PopoverPanel>
        </Popover>
    );
}
