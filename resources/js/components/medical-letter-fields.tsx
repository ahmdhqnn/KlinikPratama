import { Field } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Select } from '@/components/ui/select';
import { DatePicker } from '@/components/ui/date-picker';

export const letterTypes = [
    ['sakit', 'Surat istirahat'], ['sehat', 'Surat keterangan kesehatan'],
    ['rujukan', 'Surat rujukan eksternal'], ['laboratorium', 'Pengantar laboratorium'],
    ['rontgen_gigi', 'X-ray dental'], ['ringkasan', 'Ringkasan pemeriksaan'], ['lainnya', 'Surat keterangan lainnya'],
];
export const emptyLetterDetails = {
    rest_start: '', rest_end: '', purpose: '', conclusion: '', recipient: '', recipient_address: '',
    requested_tests: '', xray_types: [] as string[], teeth: [] as number[], result_delivery: 'pasien',
};
export type LetterDetails = typeof emptyLetterDetails;
export const toothRows = [
    [18,17,16,15,14,13,12,11,21,22,23,24,25,26,27,28],
    [48,47,46,45,44,43,42,41,31,32,33,34,35,36,37,38],
    [55,54,53,52,51,61,62,63,64,65],
    [85,84,83,82,81,71,72,73,74,75],
];

export function MedicalLetterFields({ type, value, onChange, errors = {} }: {
    type: string; value: LetterDetails; onChange: (value: LetterDetails) => void; errors?: Record<string, string>;
}) {
    const update = <K extends keyof LetterDetails>(key: K, next: LetterDetails[K]) => onChange({ ...value, [key]: next });
    return <div className="space-y-4">
        <p className="text-xs text-neutral-600">Identitas pasien dan data klinis diambil dari pemeriksaan yang tersimpan. Simpan perubahan pemeriksaan sebelum menerbitkan surat.</p>
        {type === 'sakit' && <div className="grid gap-4 sm:grid-cols-2">
            <Field htmlFor="medical-rest_start" label="Istirahat mulai tanggal" required error={errors['details.rest_start']}><DatePicker id="medical-rest_start" required value={value.rest_start} onChange={(e) => update('rest_start', e.target.value)} /></Field>
            <Field htmlFor="medical-rest_end" label="Istirahat sampai tanggal" required error={errors['details.rest_end']}><DatePicker id="medical-rest_end" required value={value.rest_end} onChange={(e) => update('rest_end', e.target.value)} /></Field>
        </div>}
        {type === 'sehat' && <>
            <Field htmlFor="medical-purpose" label="Keperluan surat" required error={errors['details.purpose']}><Input id="medical-purpose" required maxLength={500} value={value.purpose} onChange={(e) => update('purpose', e.target.value)} /></Field>
            <Field htmlFor="medical-conclusion" label="Kesimpulan pemeriksaan kesehatan oleh dokter" required error={errors['details.conclusion']}><Textarea id="medical-conclusion" required maxLength={2000} value={value.conclusion} onChange={(e) => update('conclusion', e.target.value)} placeholder="Isi sesuai hasil pemeriksaan, termasuk kelainan atau batasan bila ada." /></Field>
        </>}
        {type === 'rujukan' && <div className="grid gap-4 sm:grid-cols-2">
            <Field htmlFor="medical-recipient" label="Dokter / fasilitas tujuan" required error={errors['details.recipient']}><Input id="medical-recipient" required maxLength={300} value={value.recipient} onChange={(e) => update('recipient', e.target.value)} /></Field>
            <Field htmlFor="medical-recipient_address" label="Alamat tujuan" error={errors['details.recipient_address']}><Input id="medical-recipient_address" maxLength={1000} value={value.recipient_address} onChange={(e) => update('recipient_address', e.target.value)} /></Field>
        </div>}
        {type === 'laboratorium' && <Field htmlFor="medical-requested_tests" label="Pemeriksaan yang diminta" required error={errors['details.requested_tests']}><Textarea id="medical-requested_tests" required rows={5} maxLength={4000} value={value.requested_tests} onChange={(e) => update('requested_tests', e.target.value)} placeholder={'Tuliskan satu pemeriksaan per baris, misalnya:\nHematologi rutin\nGlukosa puasa\nHbA1c'} /></Field>}
        {type === 'rontgen_gigi' && <>
            <Field htmlFor="medical-xray_types" label="Jenis foto" required error={errors['details.xray_types']}><div id="medical-xray_types" className="flex flex-wrap gap-4">{['Dental','Panoramic','Cephalometric','Occlusal','TMJ'].map((name) => <label key={name} className="flex items-center gap-2 text-sm"><input type="checkbox" checked={value.xray_types.includes(name)} onChange={(e) => update('xray_types', e.target.checked ? [...value.xray_types, name] : value.xray_types.filter((x) => x !== name))} />{name}</label>)}</div></Field>
            <Field htmlFor="medical-teeth" label="Lokasi gigi (FDI) bila diperlukan" error={errors['details.teeth']}><div id="medical-teeth" className="space-y-2 overflow-x-auto">{toothRows.map((row, i) => <div className="flex min-w-max justify-center gap-1" key={i}>{row.map((tooth) => <label key={tooth} className="flex w-9 flex-col items-center gap-1 rounded border border-neutral-200 py-2 text-xs"><span>{tooth}</span><input aria-label={`Gigi ${tooth}`} type="checkbox" checked={value.teeth.includes(tooth)} onChange={(e) => update('teeth', e.target.checked ? [...value.teeth, tooth] : value.teeth.filter((x) => x !== tooth))} /></label>)}</div>)}</div></Field>
        </>}
        {['laboratorium', 'rontgen_gigi'].includes(type) && <Field htmlFor="medical-result_delivery" label="Penyerahan hasil" required error={errors['details.result_delivery']}><Select id="medical-result_delivery" value={value.result_delivery} onChange={(e) => update('result_delivery', e.target.value)}><option value="pasien">Diserahkan kepada pasien</option><option value="dokter">Dikirim kepada dokter</option></Select></Field>}
    </div>;
}
