import FileUpload from '@/Components/UI/FileUpload';
import { cn } from '@/utils/cn';
import { compressImage } from '@/utils/compressImage';
import { CircleCheck, CircleDashed } from 'lucide-react';

/**
 * Un emplacement de pièce justificative (CIN, permis, plaque, photo du véhicule…).
 *
 * - spec : description envoyée par le serveur (DocumentType::toUploader()) :
 *   { type, label, hint, photo, accept, max_kb }
 * - value : File | null ; onChange(file | null)
 *
 * Les photos (plaque, véhicule) : JPG / PNG uniquement, « Prendre une photo » sur mobile,
 * compressées avant l'envoi. CIN et permis : image ou PDF.
 */
export default function DocumentUploader({ spec, value, onChange, error, required = true, className }) {
    const done = Boolean(value);

    return (
        <div
            className={cn(
                'rounded-2xl border p-4 transition',
                error ? 'border-danger-200 bg-danger-50/30' : done ? 'border-primary-200 bg-primary-50/30' : 'border-gray-200 bg-white',
                className,
            )}
        >
            <div className="mb-3 flex items-start gap-2">
                {done ? (
                    <CircleCheck className="mt-0.5 h-5 w-5 shrink-0 text-primary-600" aria-hidden="true" />
                ) : (
                    <CircleDashed className="mt-0.5 h-5 w-5 shrink-0 text-gray-400" aria-hidden="true" />
                )}
                <div>
                    <p className="font-semibold text-secondary-900">
                        {spec.label}
                        {required && <span className="ml-0.5 text-danger-600" aria-hidden="true">*</span>}
                        <span className="sr-only">{done ? ' (ajouté)' : required ? ' (obligatoire)' : ''}</span>
                    </p>
                    <p className="mt-0.5 text-sm text-gray-600">{spec.hint}</p>
                </div>
            </div>

            <FileUpload
                id={`documents-${spec.type}`}
                value={value}
                onChange={onChange}
                error={error}
                required={required}
                accept={spec.accept}
                formats={spec.photo ? 'JPG ou PNG' : 'JPG, PNG ou PDF'}
                maxSize={spec.max_kb * 1024}
                camera={spec.photo}
                prepare={compressImage}
            />
        </div>
    );
}
