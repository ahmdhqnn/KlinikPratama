import { useEffect, useState } from 'react';
import { Link } from '@inertiajs/react';
import { ShieldCheck, Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Field } from '@/components/ui/field';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Spinner } from '@/components/ui/spinner';

export interface MemberOption {
    id: number; name: string; nik: string | null; nip: string | null; category: string; unit: string; costCenter: string; reason: string | null;
    tempatLahir: string | null; tanggalLahir: string | null; jenisKelamin: string | null; agama: string | null; golonganDarah: string | null;
    alamat: string | null; rt: string | null; rw: string | null; kelurahan: string | null; kecamatan: string | null;
}

export function MembershipPicker({ value, label, error, onSelect }: { value: string; label?: string; error?: string; onSelect: (member: MemberOption) => void }) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<MemberOption[]>([]);
    const [loading, setLoading] = useState(false);
    const [failure, setFailure] = useState('');
    const [selected, setSelected] = useState<MemberOption | null>(null);

    useEffect(() => {
        if (query.trim().length < 2) { setResults([]); setLoading(false); return; }
        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            setLoading(true); setFailure('');
            try {
                const response = await fetch('/kepesertaan/search?q=' + encodeURIComponent(query.trim()), { headers: { Accept: 'application/json' }, signal: controller.signal });
                if (!response.ok) throw new Error('Direktori belum dapat dimuat. Coba kembali.');
                setResults(await response.json() as MemberOption[]);
            } catch (error) {
                if (!controller.signal.aborted) { setFailure(error instanceof Error ? error.message : 'Pencarian gagal.'); setResults([]); }
            } finally { if (!controller.signal.aborted) setLoading(false); }
        }, 300);
        return () => { window.clearTimeout(timer); controller.abort(); };
    }, [query]);

    return <div className="space-y-3">
        <Field error={error ?? failure} htmlFor="membership-search" label="Peserta dari direktori instansi" required>
            <div className="relative"><Input id="membership-search" autoComplete="off" placeholder="Cari NIP, NIK, atau nama peserta…" value={query} onChange={(event) => setQuery(event.target.value)} /><span className="absolute right-3 top-3 text-neutral-400">{loading ? <Spinner label="Mencari peserta" /> : <Search className="size-4" />}</span></div>
        </Field>
        {results.length > 0 && <div className="max-h-64 divide-y divide-neutral-100 overflow-y-auto rounded-xl border border-neutral-200">{results.map((member) => <Button className="h-auto w-full justify-start rounded-none p-3 text-left" key={member.id} variant="ghost" type="button" disabled={!!member.reason} onClick={() => { setSelected(member); onSelect(member); setQuery(''); setResults([]); }}><span className="min-w-0"><span className="block font-semibold">{member.name} · {member.nip ?? member.nik}</span><span className="block text-xs font-normal text-neutral-500">{member.category} · {member.unit}</span><span className={member.reason ? 'block text-xs font-normal text-red-700' : 'block text-xs font-normal text-emerald-700'}>{member.reason ?? 'Berhak atas layanan internal'}</span></span></Button>)}</div>}
        {query.trim().length >= 2 && !loading && !failure && results.length === 0 && <p className="text-sm text-neutral-500">Peserta belum ditemukan. Impor data kepegawaian terlebih dahulu.</p>}
        {value && <Alert variant="success"><ShieldCheck /><AlertDescription>{selected?.name ?? label ?? 'Peserta terhubung'}{selected ? ` · ${selected.category} · ${selected.costCenter}` : ''}. Hak layanan diperiksa kembali saat kunjungan didaftarkan.</AlertDescription></Alert>}
        <p className="text-xs text-neutral-500">Sumber kepesertaan: <Link className="font-medium underline underline-offset-4" href="/kepesertaan">direktori instansi terverifikasi</Link>. Layanan ditanggung anggaran instansi.</p>
    </div>;
}
