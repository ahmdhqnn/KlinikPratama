import { useEffect, useState, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Field } from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';
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
    reasonAction?: (reason: string) => void;
}

let activeRequest: ConfirmationRequest | null = null;
let returnFocus: HTMLElement | null = null;
let notifyProvider: (() => void) | null = null;

export function confirmAction(description: string, action: () => void, actionLabel = 'Lanjutkan', title = 'Konfirmasi tindakan'): void {
    returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    activeRequest = { title, description, action, actionLabel };
    notifyProvider?.();
}

export function confirmReason(description: string, action: (reason: string) => void, actionLabel = 'Batalkan', title = 'Alasan pembatalan'): void {
    returnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    activeRequest = { title, description, action: () => undefined, actionLabel, reasonAction: action };
    notifyProvider?.();
}

export function ConfirmDialogProvider({ children }: { children: ReactNode }) {
    const [request, setRequest] = useState<ConfirmationRequest | null>(activeRequest);
    const [reason, setReason] = useState('');

    useEffect(() => {
        notifyProvider = () => { setReason(''); setRequest(activeRequest); };

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
        if (activeRequest?.reasonAction && reason.trim().length < 5) return;
        const action = activeRequest?.action;
        const reasonAction = activeRequest?.reasonAction;
        close();
        if (reasonAction) reasonAction(reason.trim()); else action?.();
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
                    {request?.reasonAction && <Field htmlFor="confirmation-reason" label="Alasan" required><Textarea autoFocus id="confirmation-reason" value={reason} onChange={(event) => setReason(event.target.value)} minLength={5} maxLength={1000} placeholder="Tuliskan alasan pembatalan, minimal 5 karakter." /></Field>}
                    <AlertDialogFooter>
                        <AlertDialogCancel asChild onClick={close}>
                            <Button variant="secondary">Batal</Button>
                        </AlertDialogCancel>
                        <Button disabled={!!request?.reasonAction && reason.trim().length < 5} onClick={confirm} variant={/^(hapus|batalkan|kosongkan|tolak|nonaktifkan)\b/i.test(request?.actionLabel ?? '') ? 'destructiveOutline' : 'default'}>
                            {request?.actionLabel ?? 'Lanjutkan'}
                        </Button>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
