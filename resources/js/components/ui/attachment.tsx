import { Paperclip, Upload } from 'lucide-react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export function Attachment({
    id,
    label,
    accept,
    fileName,
    disabled = false,
    required = false,
    inputKey,
    error,
    onFileChange,
}: {
    id: string;
    label: string;
    accept?: string;
    fileName?: string;
    disabled?: boolean;
    required?: boolean;
    inputKey?: number;
    error?: string;
    onFileChange: (file: File | null) => void;
}) {
    return (
        <div className="space-y-2">
            <Label className="block" htmlFor={id}>{label}</Label>
            <div className={cn('relative flex min-h-20 items-center gap-3 rounded-xl border border-dashed bg-surface px-4 py-3 transition-colors', error ? 'border-red-300 bg-red-50/40' : 'border-neutral-300 hover:border-neutral-400 hover:bg-neutral-50/30', disabled && 'cursor-not-allowed opacity-60')}>
                <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-neutral-50 text-neutral-700">
                    {fileName ? <Paperclip aria-hidden="true" className="size-5" /> : <Upload aria-hidden="true" className="size-5" />}
                </span>
                <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-medium text-neutral-800">{fileName ?? 'Pilih berkas untuk diunggah'}</span>
                    <span className="mt-0.5 block text-xs text-neutral-500">{fileName ? 'Klik untuk mengganti berkas' : 'Klik atau tekan Enter untuk memilih berkas'}</span>
                </span>
                <input
                    accept={accept}
                    aria-describedby={error ? `${id}-error` : undefined}
                    aria-invalid={Boolean(error)}
                    className="absolute inset-0 size-full cursor-pointer opacity-0 disabled:cursor-not-allowed"
                    disabled={disabled}
                    id={id}
                    key={inputKey}
                    onChange={(event) => onFileChange(event.target.files?.[0] ?? null)}
                    required={required}
                    type="file"
                />
            </div>
            {error && <p className="text-xs font-medium text-red-600" id={`${id}-error`}>{error}</p>}
        </div>
    );
}
