import type { GeneralNursingData, SystemAnswers } from '@/components/kunjungan/general-assessment';
import { nursingSystems } from '@/components/kunjungan/general-assessment';

export const narrativePrefix = 'Ringkasan otomatis. ';

export function manualNarrative(value: string): string {
    return value.startsWith(narrativePrefix) ? value.split(/\r?\n/).slice(1).join('\n').trimStart() : value;
}

export function composeNarrative(narrative: string, manual: string): string {
    return [narrative ? `${narrativePrefix}${narrative}` : '', manual.trim()].filter(Boolean).join('\n');
}

const clean = (value: unknown) => typeof value === 'string' ? value.trim().replace(/\s+/g, ' ').replace(/[.\s]+$/, '') : '';
const sentence = (label: string, value: unknown) => {
    const detail = clean(value);
    return detail ? `${label}: ${detail.slice(0, 160)}${detail.length > 160 ? '…' : ''}.` : '';
};

export function complaintNarrative(data: { keluhan: string; lama_keluhan: string; keluhan_tambahan: string; skala_nyeri: string; general_assessment: GeneralNursingData }): string {
    const detail = data.general_assessment;
    const progress: Record<string, string> = { membaik: 'Membaik', menetap: 'Menetap', memburuk: 'Memburuk', belum_diketahui: 'Belum diketahui' };
    return [
        sentence('Keluhan utama', data.keluhan),
        sentence('Lama keluhan', data.lama_keluhan),
        sentence('Lokasi', detail.location),
        sentence('Karakteristik', detail.character),
        sentence('Pola dan frekuensi', detail.pattern),
        sentence('Pemicu', detail.triggers),
        sentence('Pereda', detail.relief),
        sentence('Perkembangan', progress[String(detail.progression ?? '')]),
        sentence('Rincian perkembangan', detail.progression_notes),
        sentence('Gejala penyerta', data.keluhan_tambahan),
        data.skala_nyeri !== '' ? `Skala nyeri ${data.skala_nyeri}/10.` : '',
    ].filter(Boolean).join(' ');
}

export function systemsNarrative(systems: SystemAnswers = {}): string {
    const entries = Object.entries(nursingSystems);
    const positive = entries.filter(([key]) => systems[key]?.status === 'positive');
    const negative = entries.filter(([key]) => systems[key]?.status === 'negative');
    const negativeWithNotes = negative.filter(([key]) => clean(systems[key]?.notes));
    const negativeWithoutNotes = negative.filter(([key]) => !clean(systems[key]?.notes));
    const findings = positive.map(([key, label]) => {
        const note = clean(systems[key]?.notes);
        return `Sistem ${label} abnormal${note ? ` (${note.slice(0, 180)}${note.length > 180 ? '…' : ''})` : ''}.`;
    });
    findings.push(...negativeWithNotes.map(([key, label]) => `Sistem ${label} negatif setelah dikaji; catatan: ${clean(systems[key]?.notes).slice(0, 180)}.`));
    findings.push(...entries.filter(([key]) => ['not_assessed', 'not_relevant'].includes(systems[key]?.status) && clean(systems[key]?.notes))
        .map(([key, label]) => `Sistem ${label} ${systems[key].status === 'not_relevant' ? 'tidak relevan pada kunjungan ini' : 'belum dikaji'}; catatan: ${clean(systems[key]?.notes).slice(0, 180)}.`));
    if (negativeWithoutNotes.length > 0 && negative.length + positive.length === entries.length) {
        findings.push(negativeWithoutNotes.length === entries.length ? 'Seluruh sistem tubuh negatif setelah dikaji.' : 'Sistem lainnya negatif setelah dikaji.');
    } else {
        findings.push(...negativeWithoutNotes.map(([, label]) => `Sistem ${label} negatif setelah dikaji.`));
    }
    return findings.join(' ');
}

export function additionalNarrative(data: { status_alergi: string; riwayat_alergi: string; alergi_reaksi: string; status_riwayat_obat: string; riwayat_obat: string; general_assessment: GeneralNursingData }): string {
    const allergy = data.status_alergi === 'ada' ? clean(data.riwayat_alergi).slice(0, 250) : '';
    const medicine = data.status_riwayat_obat === 'ada' ? clean(data.riwayat_obat).slice(0, 250) : '';
    const summary = systemsNarrative(data.general_assessment.systems);
    return [
        allergy ? `Perhatian khusus: pasien melaporkan alergi ${allergy}${clean(data.alergi_reaksi) ? `, reaksi ${clean(data.alergi_reaksi).slice(0, 120)}` : ''}.` : '',
        medicine ? `Riwayat penggunaan obat: ${medicine}.` : '',
        summary ? `Ringkasan sistem tubuh: ${summary}` : '',
    ].filter(Boolean).join(' ');
}
