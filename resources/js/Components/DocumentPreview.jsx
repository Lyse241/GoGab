import { cn } from '@/utils/cn';
import { ExternalLink, FileText, ImageOff } from 'lucide-react';
import { useState } from 'react';

/**
 * Aperçu d'une pièce justificative servie par la route sécurisée (documents.show).
 * Image : vignette ; PDF : aperçu intégré. Un clic ouvre le fichier en grand dans un nouvel onglet.
 */
export default function DocumentPreview({ document, className }) {
    const [broken, setBroken] = useState(false);

    return (
        <a
            href={document.url}
            target="_blank"
            rel="noopener noreferrer"
            className={cn(
                'group relative block overflow-hidden rounded-xl bg-gray-100 ring-1 ring-gray-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary',
                className,
            )}
            aria-label={`Ouvrir ${document.label} (${document.original_name}) dans un nouvel onglet`}
        >
            {broken ? (
                <span className="flex h-full min-h-40 flex-col items-center justify-center gap-2 p-4 text-center text-sm text-gray-500">
                    <ImageOff className="h-8 w-8" aria-hidden="true" />
                    Fichier introuvable
                </span>
            ) : document.is_image ? (
                <img
                    src={document.url}
                    alt=""
                    loading="lazy"
                    onError={() => setBroken(true)}
                    className="h-48 w-full object-contain transition group-hover:scale-[1.02]"
                />
            ) : document.is_pdf ? (
                <span className="block h-48">
                    {/* L'aperçu PDF est décoratif : le lien ouvre le document en grand. */}
                    <object data={`${document.url}#toolbar=0&view=FitH`} type="application/pdf" className="pointer-events-none h-full w-full" aria-hidden="true" tabIndex={-1}>
                        <span className="flex h-full flex-col items-center justify-center gap-2 text-sm text-gray-500">
                            <FileText className="h-8 w-8" aria-hidden="true" />
                            PDF
                        </span>
                    </object>
                </span>
            ) : (
                <span className="flex h-48 flex-col items-center justify-center gap-2 text-sm text-gray-500">
                    <FileText className="h-8 w-8" aria-hidden="true" />
                    {document.original_name}
                </span>
            )}
            <span className="absolute right-2 top-2 inline-flex items-center gap-1 rounded-full bg-white/90 px-2 py-1 text-xs font-semibold text-secondary shadow-sm">
                <ExternalLink className="h-3.5 w-3.5" aria-hidden="true" />
                Ouvrir
            </span>
        </a>
    );
}
