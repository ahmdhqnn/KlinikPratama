import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { focusFirstInvalidField, focusFormControl, revealFormControl } from '@/lib/form-error-focus';

export function RequestFeedback() {
    useEffect(() => {
        let frame = 0;
        const afterRender = (callback: () => void) => {
            window.cancelAnimationFrame(frame);
            frame = window.requestAnimationFrame(() => {
                frame = window.requestAnimationFrame(callback);
            });
        };

        const focusValidationError = (errors?: Record<string, string>) => {
            const messages = errors ? Object.values(errors).filter((value): value is string => typeof value === 'string' && value.trim() !== '') : [];
            if (messages.length > 0) {
                const suffix = messages.length > 1 ? ` (+${messages.length - 1} isian lainnya)` : '';
                toast.error(`${messages[0]}${suffix}`, { id: 'request-validation' });
            }

            afterRender(() => {
                const root = document.querySelector('[role="dialog"][data-state="open"]') ?? document;
                if (!focusFirstInvalidField(root)) {
                    const summary = root.querySelector<HTMLElement>('[data-validation-summary]');
                    if (summary && revealFormControl(summary)) focusFormControl(summary);
                }
            });
        };
        const removeValidation = router.on('error', ({ detail: { errors } }) => focusValidationError(errors));
        focusValidationError();

        const onInvalid = (event: Event) => {
            const control = event.target;
            if (!(control instanceof HTMLElement)) return;
            const form = control.closest('form');
            if (!form) return;
            event.preventDefault();
            afterRender(() => {
                const first = Array.from(form.querySelectorAll<HTMLElement>('input:invalid, select:invalid, textarea:invalid'))
                    .map((candidate) => {
                        const targetId = candidate.dataset.validationTarget;
                        if (targetId) return document.getElementById(targetId) ?? candidate;
                        // Radix uses hidden native controls for its visible checkboxes and selects.
                        const proxy = candidate.getAttribute('aria-hidden') === 'true' ? candidate.previousElementSibling : null;
                        return proxy instanceof HTMLElement && proxy.matches('[role="checkbox"], [role="combobox"]') ? proxy : candidate;
                    })
                    .find(revealFormControl);
                if (!first) return;

                focusFormControl(first);
                const label = first.id ? form.querySelector<HTMLLabelElement>('label[for="' + CSS.escape(first.id) + '"]')?.textContent?.replace(/\s*\*$/, '').trim() : '';
                toast.error(label ? 'Periksa isian ' + label + '.' : 'Lengkapi isian wajib sebelum melanjutkan.', { id: 'invalid-form' });
            });
        };
        document.addEventListener('invalid', onInvalid, true);

        const removeHttp = router.on('httpException', ({ detail: { response } }) => {
            if (response.headers['x-inertia']) return;
            const error = typeof response.data === 'object' && response.data !== null ? response.data.workflowError : null;
            if (response.status === 419) {
                toast.error('Sesi telah berakhir. Tinjau isian sebelum memuat ulang.', {
                    action: { label: 'Muat ulang', onClick: () => window.location.reload() },
                    duration: Infinity,
                });
            } else {
                toast.error(typeof error === 'string' ? error : 'Permintaan belum dapat diproses. Coba kembali beberapa saat lagi.');
            }
            return false;
        });
        const removeNetwork = router.on('networkError', () => {
            toast.error('Koneksi ke sistem terputus. Periksa jaringan sebelum mencoba lagi.');
            return false;
        });

        return () => {
            window.cancelAnimationFrame(frame);
            document.removeEventListener('invalid', onInvalid, true);
            removeValidation();
            removeHttp();
            removeNetwork();
        };
    }, []);

    return null;
}
