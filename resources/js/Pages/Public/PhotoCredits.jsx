import Card from '@/Components/UI/Card';
import PublicLayout from '@/Layouts/PublicLayout';
import { Head } from '@inertiajs/react';
import { ExternalLink } from 'lucide-react';

/**
 * Crédits des photos de démonstration. Les photos sous licence CC BY / CC BY-SA sont citées une à
 * une (auteur, licence, source) ; les autres viennent de banques libres de droits (Pexels, CC0).
 */
export default function PhotoCredits({ attributed, others }) {
    return (
        <PublicLayout tone="white">
            <Head title="Crédits photos" />

            <div className="mx-auto max-w-3xl px-4 py-8 sm:px-6">
                <h1 className="text-2xl font-bold text-secondary-900">Crédits photos</h1>
                <p className="mt-2 text-gray-600">
                    Les photos de cette démonstration illustrent des commerces fictifs. Les photos des plats gabonais viennent de
                    Wikimedia Commons : merci à leurs auteurs. Elles ont été recadrées et redimensionnées.
                </p>

                <h2 className="mt-8 text-lg font-semibold text-secondary-900">Photos sous licence Creative Commons ({attributed.length})</h2>
                <Card padding="none" className="mt-3">
                    <ul className="divide-y divide-gray-100">
                        {attributed.map((credit) => (
                            <li key={credit.page} className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-4 py-3 text-sm">
                                <span className="min-w-0">
                                    <a
                                        href={credit.page}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="inline-flex items-center gap-1 font-medium text-secondary hover:underline"
                                    >
                                        {credit.title ?? 'Photo'}
                                        <ExternalLink className="h-3.5 w-3.5" aria-hidden="true" />
                                    </a>
                                    <span className="block text-gray-600">
                                        {credit.author} · {credit.source}
                                    </span>
                                </span>
                                <span className="shrink-0 text-gray-500">{credit.licence}</span>
                            </li>
                        ))}
                    </ul>
                </Card>

                <h2 className="mt-8 text-lg font-semibold text-secondary-900">Autres photos</h2>
                <p className="mt-2 text-gray-600">
                    Libres de droits pour un usage commercial, sans obligation d’attribution :
                </p>
                <ul className="mt-2 list-inside list-disc text-gray-700">
                    {Object.entries(others).map(([source, count]) => (
                        <li key={source}>
                            {source} : {count} photos
                        </li>
                    ))}
                </ul>
            </div>
        </PublicLayout>
    );
}
