import { useForm } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

interface Props {
    title: string;
    description: string;
    importUrl: string;
    templateUrl: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    accept?: string;
    fileLabel?: string;
    submitLabel?: string;
}

export function SpreadsheetImportDialog({ title, description, importUrl, templateUrl, open, onOpenChange, accept = '.xlsx', fileLabel = 'Berkas Excel (.xlsx)', submitLabel = 'Impor data' }: Props) {
    const upload = useForm<{ file: File | null }>({ file: null });

    function changeOpen(next: boolean) {
        if (upload.processing) return;
        if (!next) {
            upload.reset();
            upload.clearErrors();
        }
        onOpenChange(next);
    }

    return <Dialog open={open} onOpenChange={changeOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader><DialogTitle>{title}</DialogTitle><DialogDescription>{description}</DialogDescription></DialogHeader>
            <form className="space-y-4" onSubmit={(event) => {
                event.preventDefault();
                upload.post(importUrl, { preserveScroll: true, forceFormData: true, onSuccess: () => {
                    upload.reset();
                    upload.clearErrors();
                    onOpenChange(false);
                } });
            }}>
                <Field label={fileLabel} htmlFor="spreadsheet-import-file" required error={upload.errors.file}>
                    <Input id="spreadsheet-import-file" type="file" accept={accept} required disabled={upload.processing} onChange={(event) => upload.setData('file', event.target.files?.[0] ?? null)} />
                </Field>
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Button asChild variant="secondary"><a href={templateUrl}><Download className="size-4" />Unduh template</a></Button>
                    <div className="ml-auto flex flex-wrap justify-end gap-2">
                        <Button type="button" variant="secondary" disabled={upload.processing} onClick={() => changeOpen(false)}>Batal</Button>
                        <Button type="submit" disabled={upload.processing || !upload.data.file}>{upload.processing ? 'Memeriksa…' : submitLabel}</Button>
                    </div>
                </div>
            </form>
        </DialogContent>
    </Dialog>;
}
