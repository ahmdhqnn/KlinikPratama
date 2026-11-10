import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { ConfirmDialogProvider } from '@/components/ui/confirm-dialog';
import { RequestFeedback } from '@/components/request-feedback';

const configuredAppName = import.meta.env.VITE_APP_NAME?.trim();
const appName = configuredAppName && !configuredAppName.includes('${') ? configuredAppName : 'Klinik Pratama';

void createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    layout: (name) => (name.startsWith('auth/') || name === 'error' ? null : AppLayout),
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                <RequestFeedback />
                <ConfirmDialogProvider>{app}</ConfirmDialogProvider>
                <Toaster position="top-right" richColors />
            </TooltipProvider>
        );
    },
    progress: {
        color: 'hsl(0 0% 25.1%)',
    },
});
