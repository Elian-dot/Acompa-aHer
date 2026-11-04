import { useEffect, useState } from 'react';

type InstallEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

/**
 * Permite instalar la aplicación como PWA cuando el navegador lo ofrece.
 */
export function useInstallPrompt() {
    const [event, setEvent] = useState<InstallEvent | null>(null);
    const [installed, setInstalled] = useState(false);

    useEffect(() => {
        const onPrompt = (raw: Event) => {
            raw.preventDefault();
            setEvent(raw as InstallEvent);
        };
        const onInstalled = () => {
            setInstalled(true);
            setEvent(null);
        };
        window.addEventListener('beforeinstallprompt', onPrompt);
        window.addEventListener('appinstalled', onInstalled);
        return () => {
            window.removeEventListener('beforeinstallprompt', onPrompt);
            window.removeEventListener('appinstalled', onInstalled);
        };
    }, []);

    async function install() {
        if (!event) return;
        await event.prompt();
        const choice = await event.userChoice;
        if (choice.outcome === 'accepted') setInstalled(true);
        setEvent(null);
    }

    return { canInstall: event !== null && !installed, installed, install };
}
