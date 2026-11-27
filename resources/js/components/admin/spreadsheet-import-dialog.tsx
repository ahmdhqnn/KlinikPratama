import { useForm } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import { Attachment } from '@/components/ui/attachment';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';

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
    const [inputKey, setInputKey] = useState(0);
    const upload = useForm<{ file: File | null }>({ file: null });

    function changeOpen(next: boolean) {
        if (upload.processing) return;
        if (!next) {
            upload.reset();
            upload.clearErrors();
            setInputKey((key) => key + 1);
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
                    setInputKey((key) => key + 1);
                    onOpenChange(false);
                } });
            }}>
                <Attachment
                    accept={accept}
                    disabled={upload.processing}
                    error={upload.errors.file}
                    fileName={upload.data.file?.name}
                    id="spreadsheet-import-file"
                    inputKey={inputKey}
                    label={fileLabel}
                    onFileChange={(file) => {
                        upload.clearErrors('file');
                        upload.setData('file', file);
                    }}
                    required
                />
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-neutral-100 pt-3">
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
