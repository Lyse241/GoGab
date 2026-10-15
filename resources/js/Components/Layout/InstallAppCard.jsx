import Button from '@/Components/UI/Button';
import Card from '@/Components/UI/Card';
import useInstallPrompt from '@/Hooks/useInstallPrompt';
import { cn } from '@/utils/cn';
import { Download, Share, X } from 'lucide-react';
import { useState } from 'react';

const STORAGE_KEY = 'gogab_install_dismissed';

function wasDismissed() {
    try {
        return localStorage.getItem(STORAGE_KEY) === '1';
    } catch {
        return false;
    }
}

/**
 * Invitation à installer Gogab sur l'écran d'accueil. N'apparaît que si l'installation est
 * possible (ou sur iPhone, avec la consigne), jamais une fois installée, et se ferme pour de bon.
 */
export default function InstallAppCard({ className }) {
    const { canInstall, iosHint, install } = useInstallPrompt();
    const [dismissed, setDismissed] = useState(wasDismissed);

    if (dismissed || (!canInstall && !iosHint)) {
        return null;
    }

    const dismiss = () => {
        setDismissed(true);
        try {
            localStorage.setItem(STORAGE_KEY, '1');
        } catch {
            // Stockage indisponible : la carte réapparaîtra au prochain chargement.
        }
    };

    return (
        <Card padding="sm" className={cn('flex items-center gap-3 px-4', className)}>
            <img src="/icons/icon-192.png" alt="" width="44" height="44" className="h-11 w-11 shrink-0 rounded-xl" />
            <div className="min-w-0 flex-1">
                <p className="font-semibold text-secondary-900">Installez Gogab</p>
                {iosHint ? (
                    <p className="text-sm text-gray-600">
                        Touchez <Share className="inline h-4 w-4 align-text-bottom" aria-label="Partager" /> puis « Sur l’écran d’accueil ».
                    </p>
                ) : (
                    <p className="text-sm text-gray-600">Accès direct depuis votre écran d’accueil, sans passer par le navigateur.</p>
                )}
            </div>
            {canInstall && (
                <Button size="sm" icon={Download} onClick={install}>
                    Installer
                </Button>
            )}
            <button
                type="button"
                onClick={dismiss}
                aria-label="Fermer cette invitation"
                className="tap-area shrink-0 rounded-full p-1 text-gray-500 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary"
            >
                <X className="h-5 w-5" aria-hidden="true" />
            </button>
        </Card>
    );
}
