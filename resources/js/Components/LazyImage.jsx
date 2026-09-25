import { useState } from 'react';

/**
 * Image avec squelette animé tant qu'elle charge (connexions lentes),
 * et fond neutre si elle ne peut pas être chargée.
 */
export default function LazyImage({ src, alt = '', className = '' }) {
    const [status, setStatus] = useState('loading');

    if (!src || status === 'error') {
        return <div className={`bg-gray-200 ${className}`} aria-hidden={!alt} />;
    }

    return (
        <div className={`relative overflow-hidden ${className}`}>
            {status === 'loading' && (
                <div className="absolute inset-0 animate-pulse bg-gray-200" aria-hidden="true" />
            )}
            <img
                src={src}
                alt={alt}
                loading="lazy"
                onLoad={() => setStatus('loaded')}
                onError={() => setStatus('error')}
                className={`h-full w-full object-cover transition-opacity duration-300 ${
                    status === 'loaded' ? 'opacity-100' : 'opacity-0'
                }`}
            />
        </div>
    );
}
