import { useEffect, useState, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

interface ConfirmationRequest {
    title: string;
    description: string;
    action: () => void;
    actionLabel: string;
}

let activeRequest: ConfirmationRequest | null = null;
let returnFocus: HTMLElement | null = null;
let notifyProvider: (() => void) | null = null;

export function confirmAction(description: string, action: () => void, actionLabel = 'Lanjutkan', title = 'Konfirmasi tindakan'): void {
    returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    activeRequest = { title, description, action, actionLabel };
    notifyProvider?.();
}

export function ConfirmDialogProvider({ children }: { children: ReactNode }) {
    const [request, setRequest] = useState<ConfirmationRequest | null>(activeRequest);

    useEffect(() => {
        notifyProvider = () => setRequest(activeRequest);

        return () => {
            notifyProvider = null;
            activeRequest = null;
        };
    }, []);

    function close(): void {
        activeRequest = null;
        setRequest(null);
    }

    function confirm(): void {
        const action = activeRequest?.action;
        close();
        action?.();
    }

    return (
        <>
            {children}
            <AlertDialog onOpenChange={(open) => { if (!open) close(); }} open={request !== null}>
                <AlertDialogContent onCloseAutoFocus={(event) => {
                    event.preventDefault();
                    const trigger = returnFocus;
                    returnFocus = null;
                    if (trigger?.isConnected && !trigger.matches(':disabled')) trigger.focus({ preventScroll: true });
                }}>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{request?.title ?? 'Konfirmasi tindakan'}</AlertDialogTitle>
                        <AlertDialogDescription>{request?.description}</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel asChild onClick={close}>
                            <Button variant="secondary">Batal</Button>
                        </AlertDialogCancel>
                        <Button onClick={confirm} variant={/^(hapus|batalkan|kosongkan|tolak|nonaktifkan)\b/i.test(request?.actionLabel ?? '') ? 'destructiveOutline' : 'secondary'}>
                            {request?.actionLabel ?? 'Lanjutkan'}
                        </Button>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
