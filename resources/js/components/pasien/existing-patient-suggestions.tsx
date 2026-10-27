import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';

interface PatientMatch { id: number; nama: string; no_rm: string }

export function ExistingPatientSuggestions({ query }: { query: string }) {
    const [patients, setPatients] = useState<PatientMatch[]>([]);
    useEffect(() => {
        setPatients([]);
        if (query.trim().length < 2) return;
        const controller = new AbortController();
        const timeout = window.setTimeout(async () => {
            try {
                const params = new URLSearchParams({ q: query.trim() });
                const response = await fetch(`/pendaftaran/pasien/search?${params}`, { headers: { Accept: 'application/json' }, signal: controller.signal });
                if (response.ok) {
                    const matches: PatientMatch[] = await response.json();
                    if (!controller.signal.aborted) setPatients(matches);
                }
            } catch {
                if (!controller.signal.aborted) setPatients([]);
            }
        }, 300);
        return () => { window.clearTimeout(timeout); controller.abort(); };
    }, [query]);
    if (!patients.length) return null;

    return <aside className="rounded-xl border border-amber-200 bg-amber-50/60 p-4" aria-label="Pasien yang mungkin sudah terdaftar">
        <p className="text-sm font-semibold text-neutral-900">Pasien yang mungkin sudah terdaftar</p>
        <p className="mt-1 text-xs text-neutral-600">Periksa identitas berikut sebelum membuat nomor rekam medis baru.</p>
        <ul className="mt-3 max-h-48 space-y-2 overflow-y-auto">
            {patients.map((patient) => <li key={patient.id} className="flex flex-wrap items-center justify-between gap-2">
                <span className="min-w-0 text-sm">{patient.nama} <span className="font-mono text-xs text-neutral-500">{patient.no_rm}</span></span>
                <Button asChild size="sm" variant="secondary"><Link href={`/pendaftaran/pendaftaran-baru?tab=registered&pasien_id=${patient.id}`}>Gunakan pasien</Link></Button>
            </li>)}
        </ul>
    </aside>;
}
