import InputError from '@/Components/InputError';
import { imageUrl } from '@/utils/format';
import { useEffect, useRef, useState } from 'react';

/**
 * Champ fichier image avec aperçu (image actuelle ou nouvelle sélection).
 */
export default function ImageField({ id = 'image', current, file, onChange, error }) {
    const [preview, setPreview] = useState(null);
    const inputRef = useRef(null);

    useEffect(() => {
        if (!file) {
            setPreview(null);
            // Vide aussi le champ natif (ex. après un enregistrement réussi).
            if (inputRef.current) {
                inputRef.current.value = '';
            }
            return;
        }

        const url = URL.createObjectURL(file);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [file]);

    const shown = preview ?? imageUrl(current);

    return (
        <div>
            <label htmlFor={id} className="block text-sm font-medium text-gray-700">
                Image <span className="font-normal text-gray-400">(facultatif, 2 Mo max)</span>
            </label>
            <div className="mt-1 flex items-center gap-4">
                <div className="h-20 w-20 shrink-0 overflow-hidden rounded-lg bg-gray-100 ring-1 ring-gray-200">
                    {shown && <img src={shown} alt="" className="h-full w-full object-cover" />}
                </div>
                <input
                    ref={inputRef}
                    id={id}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    onChange={(e) => onChange(e.target.files[0] ?? null)}
                    className="block w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100"
                />
            </div>
            <InputError message={error} className="mt-1" />
        </div>
    );
}
