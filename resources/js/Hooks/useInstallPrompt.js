import { useCallback, useEffect, useState } from 'react';

const isStandalone = () =>
    typeof window !== 'undefined' &&
    (window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone === true);

const isIos = () =>
    typeof navigator !== 'undefined' &&
    /iphone|ipad|ipod/i.test(navigator.userAgent) &&
    !/crios|fxios/i.test(navigator.userAgent);

/**
 * Installation de l'application sur l'écran d'accueil (PWA).
 *
 * - canInstall : le navigateur propose l'installation (Chrome, Edge, Android) → install()
 * - iosHint : Safari sur iPhone, qui n'a pas de bouton d'installation → afficher la consigne
 *   « Partager puis Sur l'écran d'accueil »
 * - installed : déjà ouverte comme application, rien à proposer
 */
export default function useInstallPrompt() {
    const [promptEvent, setPromptEvent] = useState(null);
    const [installed, setInstalled] = useState(isStandalone);

    useEffect(() => {
        const onPrompt = (event) => {
            event.preventDefault();
            setPromptEvent(event);
        };
        const onInstalled = () => {
            setInstalled(true);
            setPromptEvent(null);
        };

        window.addEventListener('beforeinstallprompt', onPrompt);
        window.addEventListener('appinstalled', onInstalled);

        return () => {
            window.removeEventListener('beforeinstallprompt', onPrompt);
            window.removeEventListener('appinstalled', onInstalled);
        };
    }, []);

    const install = useCallback(async () => {
        if (!promptEvent) return;
        promptEvent.prompt();
        await promptEvent.userChoice;
        setPromptEvent(null);
    }, [promptEvent]);

    return {
        installed,
        canInstall: !installed && promptEvent !== null,
        iosHint: !installed && promptEvent === null && isIos(),
        install,
    };
}
