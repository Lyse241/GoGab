import NotificationItem from '@/Components/NotificationItem';
import Button from '@/Components/UI/Button';
import Card from '@/Components/UI/Card';
import EmptyState from '@/Components/UI/EmptyState';
import Pagination from '@/Components/UI/Pagination';
import Tabs from '@/Components/UI/Tabs';
import { useNotifications } from '@/Contexts/NotificationsContext';
import DashboardLayout from '@/Layouts/DashboardLayout';
import { Head, router } from '@inertiajs/react';
import { BellOff, CheckCheck, MailCheck } from 'lucide-react';
import { useEffect } from 'react';

const empty = {
    all: {
        icon: BellOff,
        title: 'Aucune notification pour le moment',
        description: 'Le suivi de vos commandes, les validations de compte et les messages de Gogab apparaîtront ici.',
    },
    unread: {
        icon: MailCheck,
        title: 'Vous êtes à jour',
        description: 'Toutes vos notifications ont été lues.',
    },
    read: {
        icon: BellOff,
        title: 'Aucune notification lue',
        description: 'Les notifications que vous ouvrez sont rangées ici.',
    },
};

export default function Index({ notifications, filter, counts }) {
    const { markRead, markAllRead, open, unreadCount } = useNotifications();

    // La cloche détecte une nouvelle notification : on met la liste à jour.
    // (Les lectures faites ici rechargent elles-mêmes la liste une fois confirmées.)
    useEffect(() => {
        if (unreadCount > counts.unread) {
            router.reload({ only: ['notifications', 'counts'] });
        }
    }, [unreadCount, counts.unread]);

    const changeFilter = (value) =>
        router.get(route('notifications.index'), value === 'all' ? {} : { filter: value }, {
            preserveState: true,
            preserveScroll: true,
            only: ['notifications', 'filter', 'counts'],
        });

    const openNotification = async (notification) => {
        if (notification.url) {
            await open(notification);
            return;
        }

        if (!notification.read) {
            await markRead(notification.id);
            router.reload({ only: ['notifications', 'counts'] });
        }
    };

    const readAll = async () => {
        await markAllRead();
        router.reload({ only: ['notifications', 'counts'] });
    };

    const state = empty[filter] ?? empty.all;

    return (
        <DashboardLayout
            header={<h1 className="text-xl font-bold text-secondary-900">Notifications</h1>}
            actions={
                <Button variant="outline" size="sm" icon={CheckCheck} onClick={readAll} disabled={counts.unread === 0}>
                    Tout marquer comme lu
                </Button>
            }
        >
            <Head title="Notifications" />

            <div className="mx-auto max-w-3xl space-y-5 px-4 py-6 sm:px-6 lg:px-8">
                <Tabs
                    label="Filtrer les notifications"
                    value={filter}
                    onChange={changeFilter}
                    items={[
                        { value: 'all', label: 'Toutes', count: counts.all },
                        { value: 'unread', label: 'Non lues', count: counts.unread },
                        { value: 'read', label: 'Lues' },
                    ]}
                />

                {notifications.data.length === 0 ? (
                    <EmptyState
                        icon={state.icon}
                        title={state.title}
                        description={state.description}
                        action={
                            filter !== 'all' && counts.all > 0 ? (
                                <Button variant="outline" onClick={() => changeFilter('all')}>
                                    Voir toutes les notifications
                                </Button>
                            ) : (
                                <Button href={route('dashboard')}>Retour à mon espace</Button>
                            )
                        }
                    />
                ) : (
                    <Card padding="none">
                        <ul className="divide-y divide-gray-100 p-1.5">
                            {notifications.data.map((notification) => (
                                <li key={notification.id} className="py-0.5">
                                    <NotificationItem
                                        notification={notification}
                                        onClick={() => openNotification(notification)}
                                    />
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}

                <Pagination paginator={notifications} />
            </div>
        </DashboardLayout>
    );
}
