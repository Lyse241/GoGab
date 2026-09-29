import { cn } from '@/utils/cn';
import { formatRelativeTime } from '@/utils/format';
import { CircleCheck, Info, TriangleAlert } from 'lucide-react';

const types = {
    info: { icon: Info, classes: 'bg-secondary-50 text-secondary' },
    success: { icon: CircleCheck, classes: 'bg-primary-50 text-primary-700' },
    warning: { icon: TriangleAlert, classes: 'bg-accent-100 text-accent-700' },
};

/**
 * Une notification (cloche et page /notifications) : icône du type, titre, message, date,
 * pastille verte tant qu'elle n'est pas lue. Rendue comme un bouton : `onClick` l'ouvre.
 */
export default function NotificationItem({ notification, onClick, compact = false, className }) {
    const type = types[notification.type] ?? types.info;
    const Icon = type.icon;

    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex w-full items-start gap-3 rounded-xl text-left transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary',
                compact ? 'px-3 py-3' : 'p-4',
                notification.read ? 'hover:bg-gray-50' : 'bg-primary-50/40 hover:bg-primary-50',
                className,
            )}
        >
            <span className={cn('flex h-10 w-10 shrink-0 items-center justify-center rounded-full', type.classes)}>
                <Icon className="h-5 w-5" aria-hidden="true" />
            </span>
            <span className="min-w-0 flex-1">
                <span className="flex items-start justify-between gap-2">
                    <span className={cn('text-sm text-gray-900', notification.read ? 'font-medium' : 'font-semibold')}>
                        {notification.title}
                    </span>
                    {!notification.read && (
                        <>
                            <span className="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full bg-primary" aria-hidden="true" />
                            <span className="sr-only">(non lue)</span>
                        </>
                    )}
                </span>
                <span className={cn('mt-0.5 block text-sm text-gray-600', compact && 'line-clamp-2')}>
                    {notification.message}
                </span>
                <time dateTime={notification.created_at} className="mt-1 block text-xs text-gray-400">
                    {formatRelativeTime(notification.created_at)}
                </time>
            </span>
        </button>
    );
}
