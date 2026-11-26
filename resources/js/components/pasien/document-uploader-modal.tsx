import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useForm } from '@inertiajs/react';
import { Archive, Download, FileImage, FileText, Upload } from 'lucide-react';
import { useId, type FormEvent } from 'react';

export interface PatientDocumentItem {
    id: number;
    uuid: string;
    title: string;
    category: string;
    categoryLabel: string;
    sensitivity: string;
    documentDate: string | null;
    issuerFacility: string | null;
    toothFdi: string | null;
    visitNumber: string | null;
    originalName: string;
    mimeType: string;
    fileSize: number;
    notes: string | null;
    uploadedBy: string | null;
    uploadedAt: string | null;
    archivedAt: string | null;
    archiveReason: string | null;
    previewUrl: string | null;
    downloadUrl: string | null;
    archiveUrl: string | null;
    canPreview: boolean;
    canDownload: boolean;
    canArchive: boolean;
}

export interface DocumentPermissions {
    canUpload: boolean;
    allowedCategories: { value: string; label: string; sensitivity: string }[];
    allCategories: { value: string; label: string }[];
    uploadUrl: string;
    visitOptions: { id: number; label: string }[];
}

export function formatDocumentSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }

    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

export function DocumentFileIcon({ mimeType, className = 'size-4' }: { mimeType: string; className?: string }) {
    if (mimeType.startsWith('image/')) {
        return <FileImage aria-hidden="true" className={className} />;
    }

    return <FileText aria-hidden="true" className={className} />;
}

interface UploadModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    permissions: DocumentPermissions;
    defaultCategory?: string;
}

export function DocumentUploadModal({ open, onOpenChange, permissions, defaultCategory: preferredCategory }: UploadModalProps) {
    const idPrefix = useId();
    const defaultCategory = (preferredCategory && permissions.allowedCategories.some((cat) => cat.value === preferredCategory)
        ? preferredCategory
        : permissions.allowedCategories.find((cat) => cat.value === 'rme_lama')?.value ?? permissions.allowedCategories[0]?.value) ?? '';
    const form = useForm<{
        title: string;
        category: string;
        document_date: string;
        issuer_facility: string;
        kunjungan_id: string;
        tooth_fdi: string;
        notes: string;
        file: File | null;
    }>({
        title: '',
        category: defaultCategory,
        document_date: new Date().toISOString().slice(0, 10),
        issuer_facility: '',
        kunjungan_id: '',
        tooth_fdi: '',
        notes: '',
        file: null,
    });

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(permissions.uploadUrl, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                form.setData('category', defaultCategory);
                onOpenChange(false);
            },
        });
    }

    return (
        <Dialog onOpenChange={onOpenChange} open={open}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Unggah dokumen & RME eksternal pasien</DialogTitle>
                    <DialogDescription>
                        Simpan berkas rekam medis sebelumnya (PDF), resume medis fasyankes lain, hasil laboratorium/radiologi, atau dokumen identitas (PDF, JPG, PNG · maksimal 10 MB). Dokumen dapat disimpan tanpa dikaitkan ke kunjungan di aplikasi.
                    </DialogDescription>
                </DialogHeader>
                <form className="space-y-4" onSubmit={handleSubmit}>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field error={form.errors.category} htmlFor={`${idPrefix}-cat`} label="Kategori dokumen" required>
                            <Select
                                id={`${idPrefix}-cat`}
                                onChange={(event) => form.setData('category', event.target.value)}
                                required
                                value={form.data.category}
                            >
                                <option value="">Pilih kategori</option>
                                {permissions.allowedCategories.map((cat) => (
                                    <option key={cat.value} value={cat.value}>{cat.label}</option>
                                ))}
                            </Select>
                        </Field>
                        <Field error={form.errors.document_date} htmlFor={`${idPrefix}-date`} label="Tanggal dokumen" required>
                            <DatePicker
                                id={`${idPrefix}-date`}
                                onChange={(event) => form.setData('document_date', event.target.value)}
                                required
                                value={form.data.document_date}
                            />
                        </Field>
                    </div>
                    <Field error={form.errors.title} htmlFor={`${idPrefix}-title`} label="Judul dokumen" required>
                        <Input
                            id={`${idPrefix}-title`}
                            maxLength={160}
                            onChange={(event) => form.setData('title', event.target.value)}
                            placeholder="Contoh: RME Sebelumnya (PDF) / Resume Medis RS / Hasil Lab"
                            required
                            value={form.data.title}
                        />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field error={form.errors.issuer_facility} htmlFor={`${idPrefix}-issuer`} label="Fasilitas / instansi penerbit">
                            <Input
                                id={`${idPrefix}-issuer`}
                                maxLength={160}
                                onChange={(event) => form.setData('issuer_facility', event.target.value)}
                                placeholder="Contoh: RSUD / Klinik Sebelumnya / Lab"
                                value={form.data.issuer_facility}
                            />
                        </Field>
                        <Field error={form.errors.kunjungan_id} htmlFor={`${idPrefix}-visit`} label="Kaitan kunjungan di aplikasi (opsional)">
                            <Select
                                id={`${idPrefix}-visit`}
                                onChange={(event) => form.setData('kunjungan_id', event.target.value)}
                                value={form.data.kunjungan_id}
                            >
                                <option value="">Bukan kunjungan di aplikasi (RME / riwayat eksternal)</option>
                                {permissions.visitOptions.map((visit) => (
                                    <option key={visit.id} value={String(visit.id)}>{visit.label}</option>
                                ))}
                            </Select>
                            <p className="text-xs leading-5 text-neutral-500">
                                Kosongkan jika berkas merupakan RME lama atau riwayat medis dari fasyankes lain.
                            </p>
                        </Field>
                    </div>
                    {form.data.category === 'radiologi_gigi' && (
                        <Field error={form.errors.tooth_fdi} htmlFor={`${idPrefix}-tooth`} label="Nomor elemen gigi (FDI, opsional)">
                            <Input
                                id={`${idPrefix}-tooth`}
                                maxLength={4}
                                onChange={(event) => form.setData('tooth_fdi', event.target.value.replace(/\D/g, '').slice(0, 2))}
                                placeholder="Contoh: 36 atau 46"
                                value={form.data.tooth_fdi}
                            />
                        </Field>
                    )}
                    <Field error={form.errors.file} htmlFor={`${idPrefix}-file`} label="Berkas (PDF, JPG, PNG · maks. 10 MB)" required>
                        <Input
                            accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                            id={`${idPrefix}-file`}
                            onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)}
                            required
                            type="file"
                        />
                    </Field>
                    <Field error={form.errors.notes} htmlFor={`${idPrefix}-notes`} label="Catatan klinis / keterangan">
                        <Textarea
                            id={`${idPrefix}-notes`}
                            maxLength={1000}
                            onChange={(event) => form.setData('notes', event.target.value)}
                            placeholder="Ringkasan temuan atau keterangan konteks dokumen (opsional)"
                            rows={2}
                            value={form.data.notes}
                        />
                    </Field>
                    <DialogFooter className="gap-2 pt-2">
                        <Button onClick={() => onOpenChange(false)} type="button" variant="secondary">Batalkan</Button>
                        <Button disabled={form.processing || !form.data.file || !form.data.title.trim() || !form.data.category} type="submit">
                            <Upload className="size-4" />
                            {form.processing ? 'Mengunggah…' : 'Simpan dokumen'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

interface PreviewModalProps {
    document: PatientDocumentItem | null;
    onOpenChange: (open: boolean) => void;
}

export function DocumentPreviewModal({ document, onOpenChange }: PreviewModalProps) {
    if (!document || !document.previewUrl) {
        return null;
    }

    const isPdf = document.mimeType === 'application/pdf';

    return (
        <Dialog onOpenChange={onOpenChange} open={Boolean(document)}>
            <DialogContent className="flex max-h-[92vh] flex-col sm:max-w-4xl">
                <DialogHeader>
                    <div className="flex flex-wrap items-center gap-2">
                        <DialogTitle className="break-words">{document.title}</DialogTitle>
                        <Badge variant="outline">{document.categoryLabel}</Badge>
                    </div>
                    <DialogDescription>
                        {[
                            document.documentDate ? `Tanggal dokumen: ${document.documentDate}` : null,
                            document.issuerFacility ? `Penerbit: ${document.issuerFacility}` : null,
                            document.toothFdi ? `Gigi FDI ${document.toothFdi}` : null,
                            formatDocumentSize(document.fileSize),
                        ].filter(Boolean).join(' · ')}
                    </DialogDescription>
                </DialogHeader>
                <div className="min-h-0 flex-1 overflow-auto rounded-lg border border-neutral-200 bg-neutral-50">
                    {isPdf ? (
                        <iframe
                            className="h-[65vh] w-full"
                            src={document.previewUrl}
                            title={`Pratinjau ${document.title}`}
                        />
                    ) : (
                        <div className="flex min-h-[20rem] items-center justify-center p-4">
                            <img
                                alt={document.title}
                                className="max-h-[65vh] max-w-full rounded object-contain"
                                src={document.previewUrl}
                            />
                        </div>
                    )}
                </div>
                {document.notes && (
                    <p className="rounded-lg border border-neutral-200 bg-surface px-3 py-2 text-xs text-neutral-700">
                        <span className="font-semibold text-neutral-900">Catatan:</span> {document.notes}
                    </p>
                )}
                <DialogFooter className="gap-2">
                    <Button onClick={() => onOpenChange(false)} type="button" variant="secondary">Tutup</Button>
                    {document.downloadUrl && (
                        <Button asChild>
                            <a href={document.downloadUrl}>
                                <Download className="size-4" />
                                Unduh berkas
                            </a>
                        </Button>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

interface ArchiveModalProps {
    document: PatientDocumentItem | null;
    onOpenChange: (open: boolean) => void;
}

export function DocumentArchiveModal({ document, onOpenChange }: ArchiveModalProps) {
    const idPrefix = useId();
    const form = useForm({ archive_reason: '' });

    if (!document || !document.archiveUrl) {
        return null;
    }

    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!document?.archiveUrl) {
            return;
        }
        form.post(document.archiveUrl, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    }

    return (
        <Dialog onOpenChange={onOpenChange} open={Boolean(document)}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Arsipkan dokumen pasien</DialogTitle>
                    <DialogDescription>
                        Sesuai PMK No. 24 Tahun 2022, berkas rekam medis tidak dihapus permanen. Dokumen akan dipindahkan ke arsip dengan catatan alasan yang tercatat di log audit.
                    </DialogDescription>
                </DialogHeader>
                <Alert dismissible={false} variant="warning">
                    <p className="text-xs font-medium">{document.title} ({document.originalName})</p>
                </Alert>
                <form className="space-y-4" onSubmit={handleSubmit}>
                    <Field error={form.errors.archive_reason} htmlFor={`${idPrefix}-reason`} label="Alasan pengarsipan (minimal 10 karakter)" required>
                        <Textarea
                            id={`${idPrefix}-reason`}
                            minLength={10}
                            onChange={(event) => form.setData('archive_reason', event.target.value)}
                            placeholder="Contoh: Berkas terunggah ganda atau digantikan versi hasil pemeriksaan terbaru."
                            required
                            rows={3}
                            value={form.data.archive_reason}
                        />
                    </Field>
                    <DialogFooter className="gap-2">
                        <Button onClick={() => onOpenChange(false)} type="button" variant="secondary">Batalkan</Button>
                        <Button disabled={form.processing || form.data.archive_reason.trim().length < 10} type="submit" variant="destructive">
                            <Archive className="size-4" />
                            {form.processing ? 'Mengarsipkan…' : 'Arsipkan dokumen'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
