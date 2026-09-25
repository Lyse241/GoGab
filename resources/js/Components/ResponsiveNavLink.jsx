import { Link } from '@inertiajs/react';

export default function ResponsiveNavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            className={`flex w-full items-start border-l-4 py-2 pe-4 ps-3 ${
                active
                    ? 'border-accent bg-secondary-800 text-white focus:border-accent-300 focus:bg-secondary-800'
                    : 'border-transparent text-secondary-100 hover:border-secondary-300 hover:bg-secondary-800 hover:text-white focus:border-secondary-300 focus:bg-secondary-800 focus:text-white'
            } text-base font-medium transition duration-150 ease-in-out focus:outline-none ${className}`}
        >
            {children}
        </Link>
    );
}
