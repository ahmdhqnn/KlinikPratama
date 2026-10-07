import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';

const appName = import.meta.env.VITE_APP_NAME || 'Klinik Pratama';

void createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    layout: (name) => (name.startsWith('auth/') ? null : AppLayout),
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster position="top-right" richColors />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#2563eb',
    },
});
