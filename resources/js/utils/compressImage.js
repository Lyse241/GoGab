/**
 * Réduit une photo avant l'envoi (données mobiles coûteuses, photos de téléphone de 3 à 8 Mo) :
 * plus grand côté ramené à `maxDimension`, réencodée en JPEG.
 *
 * Les PDF, les petites images et tout fichier qu'on ne sait pas décoder sont renvoyés tels quels :
 * le serveur reste juge (format, taille).
 */
export async function compressImage(file, { maxDimension = 1600, quality = 0.82, minBytes = 400 * 1024 } = {}) {
    if (!/^image\/(jpeg|png)$/.test(file.type) || file.size < minBytes) {
        return file;
    }

    try {
        const bitmap = await createImageBitmap(file);
        const scale = Math.min(1, maxDimension / Math.max(bitmap.width, bitmap.height));
        const width = Math.round(bitmap.width * scale);
        const height = Math.round(bitmap.height * scale);

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const context = canvas.getContext('2d');
        // Fond blanc : la transparence d'un PNG deviendrait noire en JPEG.
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, width, height);
        context.drawImage(bitmap, 0, 0, width, height);
        bitmap.close?.();

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));

        if (!blob || blob.size >= file.size) {
            return file;
        }

        const name = file.name.replace(/\.(png|jpe?g)$/i, '') + '.jpg';

        return new File([blob], name, { type: 'image/jpeg', lastModified: Date.now() });
    } catch {
        return file;
    }
}
