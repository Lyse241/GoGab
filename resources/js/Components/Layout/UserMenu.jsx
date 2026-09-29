import StatusBadge from '@/Components/UI/StatusBadge';
import { cn } from '@/utils/cn';
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/react';
import { Link, usePage } from '@inertiajs/react';
import { Bike, ChevronDown, Hourglass, LayoutDashboard, LogOut, ShieldCheck, Store, TriangleAlert, UserRound } from 'lucide-react';

/**
 * Avatar à initiales. `initials` vient du serveur (auth.user.initials) ; à défaut, calculé depuis le nom.
 */
export function Avatar({ name = '', initials, className }) {
    const letters =
        initials ??
        (name
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part[0]?.toUpperCase())
            .join('') ||
            '?');

    return (
        <span
            className={cn(
                'flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-secondary text-sm font-semibold text-white',
                className,
            )}
            aria-hidden="true"
        >
            {letters}
        </span>
    );
}

// « Mon espace » selon le rôle (compte validé).
const spaces = {
    admin: { label: 'Administration', icon: ShieldCheck },
    business: { label: 'Mon commerce', icon: Store },
    delivery: { label: 'Mes courses', icon: Bike },
    client: null, // l'espace du client, c'est le catalogue
};

const itemClasses =
    'flex min-h-tap w-full items-center gap-3 rounded-xl px-3 text-left text-sm font-medium text-gray-700 data-[focus]:bg-gray-100 data-[focus]:text-gray-900';

/**
 * Menu du compte connecté (avatar) : Mon espace (selon le rôle), Mon profil, Déconnexion.
 * Un compte non validé voit son statut et un lien vers le suivi de son inscription.
 * `showDashboard` : lien vers l'espace du rôle (masqué quand on y est déjà).
 */
export default function UserMenu({ showDashboard = true, compact = false }) {
    const { auth, moderation } = usePage().props;
    const { user, role, role_label } = auth;
    const warningsCount = moderation?.warnings_count ?? 0;
    const approved = user.account_status === 'approved';
    const space = approved ? spaces[role] : { label: 'Suivi de mon inscription', icon: Hourglass };
    const SpaceIcon = space?.icon ?? LayoutDashboard;

    return (
        <Menu as="div" className="relative">
            <MenuButton
                className="inline-flex min-h-tap items-center gap-2 rounded-full p-1 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary sm:pr-2"
                aria-label={`Menu de ${user.name}`}
            >
                <span className="relative">
                    <Avatar name={user.name} initials={user.initials} />
                    {!approved && (
                        <span
                            className="absolute -right-0.5 -top-0.5 h-3 w-3 rounded-full bg-accent ring-2 ring-white"
                            aria-hidden="true"
                        />
                    )}
                </span>
                {!compact && (
                    <>
                        <span className="hidden max-w-[9rem] truncate text-sm font-semibold text-gray-800 md:block">
                            {user.name}
                        </span>
                        <ChevronDown className="hidden h-4 w-4 text-gray-500 md:block" aria-hidden="true" />
                    </>
                )}
            </MenuButton>

            <MenuItems
                transition
                anchor="bottom end"
                className="z-50 mt-2 w-64 origin-top-right rounded-2xl bg-white p-2 shadow-card-hover ring-1 ring-gray-100 transition duration-100 ease-out focus:outline-none data-[closed]:scale-95 data-[closed]:opacity-0"
            >
                <div className="border-b border-gray-100 px-3 pb-3 pt-2">
                    <p className="truncate text-sm font-semibold text-gray-900">{user.name}</p>
                    <p className="truncate text-xs text-gray-500">{user.email}</p>
                    <div className="mt-1.5 flex flex-wrap items-center gap-2">
                        {role_label && <span className="text-xs font-medium text-primary-700">{role_label}</span>}
                        {!approved && <StatusBadge type="account" status={user.account_status} size="sm" />}
                    </div>
                </div>
                <div className="pt-2">
                    {showDashboard && space && (
                        <MenuItem>
                            <Link href={route('dashboard')} className={itemClasses}>
                                <SpaceIcon className="h-5 w-5 text-gray-500" aria-hidden="true" />
                                {space.label}
                            </Link>
                        </MenuItem>
                    )}
                    <MenuItem>
                        <Link href={route('profile.edit')} className={itemClasses}>
                            <UserRound className="h-5 w-5 text-gray-500" aria-hidden="true" />
                            Mon profil
                        </Link>
                    </MenuItem>
                    {warningsCount > 0 && (
                        <MenuItem>
                            <Link href={route('account.warnings')} className={itemClasses}>
                                <TriangleAlert className="h-5 w-5 text-warning-600" aria-hidden="true" />
                                <span className="flex-1">Mes avertissements</span>
                                <span className="rounded-full bg-warning-50 px-2 py-0.5 text-xs font-bold text-warning-800">{warningsCount}</span>
                            </Link>
                        </MenuItem>
                    )}
                    <MenuItem>
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className={cn(itemClasses, 'text-danger-700 data-[focus]:bg-danger-50 data-[focus]:text-danger-700')}
                        >
                            <LogOut className="h-5 w-5" aria-hidden="true" />
                            Déconnexion
                        </Link>
                    </MenuItem>
                </div>
            </MenuItems>
        </Menu>
    );
}
