import { router } from '@inertiajs/react';
import { useEffect } from 'react';

export function useUnsavedChangesGuard(
    hasUnsavedChanges: boolean,
    message = 'Ada isian klinis yang belum disimpan. Yakin ingin meninggalkan halaman ini?',
): void {
    useEffect(() => {
        if (!hasUnsavedChanges) return;

        const handleBeforeUnload = (event: BeforeUnloadEvent) => {
            event.preventDefault();
            event.returnValue = message;
            return message;
        };

        const removeInertiaListener = router.on('before', (event) => {
            const visit = event.detail.visit;
            if (visit.method !== 'get' || visit.preserveState) {
                return;
            }
            if (visit.url.pathname === window.location.pathname && visit.url.search === window.location.search) {
                return;
            }
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });

        window.addEventListener('beforeunload', handleBeforeUnload);

        return () => {
            window.removeEventListener('beforeunload', handleBeforeUnload);
            removeInertiaListener();
        };
    }, [hasUnsavedChanges, message]);
}
