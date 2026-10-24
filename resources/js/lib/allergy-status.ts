import { normalizeClinicalAnswer } from '@/lib/clinical-answer';

export interface AllergyStatus {
    label: string;
    state: 'reported' | 'none' | 'unknown';
}

function normalize(value: string): string {
    return value.trim().toLocaleLowerCase('id-ID').replace(/[.!]+$/, '').trim();
}

export function isNoAllergyReport(value: string | null | undefined): boolean {
    return ['tidak ada', 'tidak ada alergi', 'tidak ada riwayat alergi', 'tidak ada riwayat alergi menurut pasien', 'tidak ada, sudah ditanyakan', 'nihil', 'tidak_ada'].includes(normalize(value ?? ''));
}

export function isReportedAllergy(value: string | null | undefined): value is string {
    return normalizeClinicalAnswer(value) !== null && !isNoAllergyReport(value);
}

export function getAllergyStatus(values: Array<string | null | undefined>): AllergyStatus {
    const reported = values.filter(isReportedAllergy).map((value) => value.trim());
    if (reported.length) {
        return { state: 'reported', label: [...new Set(reported)].join(' · ') };
    }
    if (values.some(isNoAllergyReport)) {
        return { state: 'none', label: 'Tidak ada riwayat alergi' };
    }

    return { state: 'unknown', label: 'Belum dikaji / belum diketahui' };
}
