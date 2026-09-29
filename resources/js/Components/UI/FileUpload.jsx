import { FieldError, Hint, Label } from '@/Components/UI/Field';
import { cn } from '@/utils/cn';
import { formatFileSize, imageUrl } from '@/utils/format';
import Spinner from '@/Components/UI/Spinner';
import { Camera, FileText, ImageUp, Trash2, UploadCloud } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const isImage = (type) => /^image\//.test(type ?? '');

/**
 * Zone de dépôt de fichier : glisser-déposer ou clic (ou appareil photo sur mobile),
 * aperçu de l'image, contrôle du type et de la taille avant envoi.
 *
 * - value : File sélectionné (ou null) ; onChange(file | null)
 * - current : chemin ou URL du fichier déjà enregistré (aperçu tant qu'aucun nouveau fichier)
 * - onRemoveCurrent : si fourni, bouton « Retirer » aussi pour le fichier déjà enregistré
 * - accept : ex. "image/jpeg,image/png,image/webp,application/pdf"
 * - maxSize : taille maximale en octets
 * - formats : formats lisibles pour le message d'erreur (ex. "JPG ou PNG")
 * - camera : ajoute « Prendre une photo » sur mobile (ouvre directement l'appareil photo)
 * - prepare : async (file) => file, appliqué avant les contrôles (ex. compression d'une photo)
 * - error : erreur serveur (prioritaire sur l'erreur locale)
 */
export default function FileUpload({
    id,
    label,
    hint,
    error,
    required = false,
    value = null,
    current = null,
    onRemoveCurrent,
    onChange,
    accept = 'image/jpeg,image/png,image/webp',
    maxSize = 2 * 1024 * 1024,
    formats,
    camera = false,
    prepare,
    className,
}) {
    const inputRef = useRef(null);
    const cameraRef = useRef(null);
    const [dragging, setDragging] = useState(false);
    const [localError, setLocalError] = useState(null);
    const [preview, setPreview] = useState(null);
    const [preparing, setPreparing] = useState(false);

    useEffect(() => {
        if (!value) {
            setPreview(null);
            // Vide aussi le champ natif (ex. après un enregistrement réussi).
            [inputRef, cameraRef].forEach((ref) => {
                if (ref.current) {
                    ref.current.value = '';
                }
            });
            return;
        }

        if (!isImage(value.type)) {
            setPreview(null);
            return;
        }

        const url = URL.createObjectURL(value);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [value]);

    const acceptedTypes = accept.split(',').map((type) => type.trim());
    const acceptsType = (type) =>
        acceptedTypes.some((accepted) => (accepted.endsWith('/*') ? type.startsWith(accepted.slice(0, -1)) : type === accepted));

    const pick = async (picked) => {
        if (!picked) {
            return;
        }

        if (!acceptsType(picked.type)) {
            setLocalError(formats ? `Format non accepté : ${formats} uniquement.` : 'Ce type de fichier n’est pas accepté.');
            return;
        }

        let file = picked;
        if (prepare) {
            setPreparing(true);
            try {
                file = await prepare(picked);
            } finally {
                setPreparing(false);
            }
        }

        if (file.size > maxSize) {
            setLocalError(`Le fichier dépasse la taille maximale (${formatFileSize(maxSize)}).`);
            return;
        }

        setLocalError(null);
        onChange?.(file);
    };

    const onDrop = (event) => {
        event.preventDefault();
        setDragging(false);
        pick(event.dataTransfer.files?.[0]);
    };

    const remove = () => {
        setLocalError(null);
        onChange?.(null);
    };

    const shownError = error || localError;
    const errorId = shownError ? `${id}-error` : undefined;
    const hintId = hint ? `${id}-hint` : undefined;
    const currentUrl = !value ? imageUrl(current) : null;
    const hasFile = value || currentUrl;

    return (
        <div className={className}>
            {label && (
                <Label htmlFor={id} required={required} className="mb-1.5">
                    {label}
                </Label>
            )}

            <input
                ref={inputRef}
                id={id}
                type="file"
                accept={accept}
                required={required && !hasFile}
                aria-invalid={shownError ? true : undefined}
                aria-describedby={[errorId, hintId].filter(Boolean).join(' ') || undefined}
                onChange={(event) => pick(event.target.files?.[0])}
                className="sr-only"
            />
            {camera && (
                <input
                    ref={cameraRef}
                    type="file"
                    accept={accept}
                    capture="environment"
                    tabIndex={-1}
                    aria-hidden="true"
                    onChange={(event) => pick(event.target.files?.[0])}
                    className="sr-only"
                />
            )}

            {preparing ? (
                <div className="flex items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-primary-200 bg-primary-50/40 px-6 py-8 text-sm font-medium text-primary-800">
                    <Spinner /> Préparation de la photo…
                </div>
            ) : null}

            {preparing ? null : hasFile ? (
                <div className="flex items-center gap-4 rounded-2xl border border-gray-200 bg-white p-3">
                    <div className="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-gray-100">
                        {preview || (currentUrl && !/\.pdf($|\?)/i.test(currentUrl)) ? (
                            <img src={preview ?? currentUrl} alt="" className="h-full w-full object-cover" />
                        ) : (
                            <FileText className="h-8 w-8 text-gray-400" aria-hidden="true" />
                        )}
                    </div>
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium text-gray-900">
                            {value ? value.name : 'Fichier actuel'}
                        </p>
                        {value && <p className="text-xs text-gray-500">{formatFileSize(value.size)}</p>}
                        <div className="mt-2 flex flex-wrap gap-3">
                            <button
                                type="button"
                                onClick={() => inputRef.current?.click()}
                                className="inline-flex min-h-9 items-center gap-1.5 text-sm font-semibold text-secondary hover:underline"
                            >
                                <ImageUp className="h-4 w-4" aria-hidden="true" />
                                Remplacer
                            </button>
                            {camera && (
                                <button
                                    type="button"
                                    onClick={() => cameraRef.current?.click()}
                                    className="inline-flex min-h-9 items-center gap-1.5 text-sm font-semibold text-secondary hover:underline sm:hidden"
                                >
                                    <Camera className="h-4 w-4" aria-hidden="true" />
                                    Reprendre
                                </button>
                            )}
                            {(value || onRemoveCurrent) && (
                                <button
                                    type="button"
                                    onClick={value ? remove : onRemoveCurrent}
                                    className="inline-flex min-h-9 items-center gap-1.5 text-sm font-semibold text-danger-600 hover:underline"
                                >
                                    <Trash2 className="h-4 w-4" aria-hidden="true" />
                                    Retirer
                                </button>
                            )}
                        </div>
                    </div>
                </div>
            ) : (
                <label
                    htmlFor={id}
                    onDragOver={(event) => {
                        event.preventDefault();
                        setDragging(true);
                    }}
                    onDragLeave={() => setDragging(false)}
                    onDrop={onDrop}
                    className={cn(
                        'flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed px-6 py-8 text-center transition',
                        'focus-within:ring-2 focus-within:ring-primary',
                        dragging
                            ? 'border-primary bg-primary-50'
                            : shownError
                              ? 'border-danger-300 bg-danger-50/40'
                              : 'border-gray-300 bg-gray-50 hover:border-primary-300 hover:bg-primary-50/40',
                    )}
                >
                    <span className="flex h-12 w-12 items-center justify-center rounded-full bg-white text-primary-600 shadow-sm">
                        <UploadCloud className="h-6 w-6" aria-hidden="true" />
                    </span>
                    <span className="mt-3 text-sm font-semibold text-secondary-900">
                        <span className="text-primary-700">Choisir un fichier</span>
                        <span className="hidden sm:inline"> ou glissez-le ici</span>
                    </span>
                    <span className="mt-1 text-xs text-gray-500">Taille maximale : {formatFileSize(maxSize)}</span>
                </label>
            )}

            {camera && !preparing && !hasFile && (
                <button
                    type="button"
                    onClick={() => cameraRef.current?.click()}
                    className="mt-2 inline-flex min-h-tap w-full items-center justify-center gap-2 rounded-full border border-primary-200 bg-white px-4 text-sm font-semibold text-primary-700 hover:bg-primary-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary sm:hidden"
                >
                    <Camera className="h-5 w-5" aria-hidden="true" />
                    Prendre une photo
                </button>
            )}

            {shownError ? <FieldError id={errorId} message={shownError} /> : <Hint id={hintId}>{hint}</Hint>}
        </div>
    );
}
