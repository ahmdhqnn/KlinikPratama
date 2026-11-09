export interface ScreeningVersionSummary {
    version: number; reason: string | null; recordedAt: string | null; actor: string | null; summary: Record<string, string | null>;
}

export function ScreeningVersionHistory({ versions = [] }: { versions?: ScreeningVersionSummary[] }) {
    if (!versions.length) return null;
    return <details className="rounded-xl border border-neutral-200 p-4 print:hidden"><summary className="cursor-pointer text-sm font-semibold">Riwayat versi pengkajian perawat ({versions.length})</summary><div className="mt-4 space-y-4">{versions.map(version => <details className="rounded-lg border border-neutral-200 p-3" key={version.version}><summary className="cursor-pointer text-sm font-medium">Versi {version.version} · {version.recordedAt} · {version.actor ?? 'Identitas historis tidak tersedia'}</summary>{version.reason && <p className="mt-2 whitespace-pre-wrap text-sm text-neutral-600">Alasan: {version.reason}</p>}<dl className="mt-3 grid min-w-0 gap-3 md:grid-cols-2">{Object.entries(version.summary).filter(([, value]) => value !== null && value !== '').map(([label, value]) => <div className="min-w-0" key={label}><dt className="text-xs text-neutral-500">{label}</dt><dd className="mt-1 whitespace-pre-wrap break-words text-sm">{value}</dd></div>)}</dl></details>)}</div></details>;
}
