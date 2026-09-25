import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen flex-col items-center bg-gray-50 pt-6 sm:justify-center sm:pt-0">
            <div className="h-1.5 w-full bg-secondary sm:fixed sm:top-0" aria-hidden="true" />
            <div className="mt-6 sm:mt-0">
                <Link href="/" className="text-4xl" aria-label="Gogab, accueil">
                    <ApplicationLogo />
                </Link>
            </div>

            <div className="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md ring-1 ring-gray-200 sm:max-w-md sm:rounded-xl">
                {children}
            </div>
        </div>
    );
}
