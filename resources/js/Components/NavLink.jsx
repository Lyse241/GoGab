import { Link } from '@inertiajs/react';

export default function NavLink({
    active = false,
    className = '',
    children,
    ...props
}) {
    return (
        <Link
            {...props}
            className={
                'inline-flex items-center border-b-2 px-1 pt-1 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none ' +
                (active
                    ? 'border-accent text-white focus:border-accent-300'
                    : 'border-transparent text-secondary-100 hover:border-secondary-300 hover:text-white focus:border-secondary-300 focus:text-white') +
                className
            }
        >
            {children}
        </Link>
    );
}
