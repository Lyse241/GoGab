import { usePage } from '@inertiajs/react';

export default function FlashMessage({ className = '' }) {
    const { flash } = usePage().props;

    if (!flash?.success && !flash?.error) {
        return null;
    }

    return (
        <div className={`space-y-2 ${className}`}>
            {flash.success && (
                <div className="rounded-md border border-primary-200 bg-primary-50 px-4 py-3 text-sm text-primary-800">
                    {flash.success}
                </div>
            )}
            {flash.error && (
                <div
                    role="alert"
                    className="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
                >
                    {flash.error}
                </div>
            )}
        </div>
    );
}
