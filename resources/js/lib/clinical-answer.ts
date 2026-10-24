export function normalizeClinicalAnswer(value: string | null | undefined): string | null {
    const answer = value?.trim() ?? '';
    const normalized = answer.toLocaleLowerCase('id-ID').replace(/[.!]+$/, '').trim().replace(/\s+/g, ' ');
    return ['', '-', '—', 'belum dicatat', 'belum di catat', 'belum diisi', 'belum di isi', 'belum diketahui', 'belum dapat diketahui', 'tidak diketahui', 'belum dikaji', 'belum_diketahui'].includes(normalized) ? null : answer;
}

export const confirmedAbsentHistory = {
    past: 'Tidak ada riwayat penyakit dahulu menurut pasien',
    family: 'Tidak ada riwayat penyakit keluarga menurut pasien',
    allergy: 'Tidak ada riwayat alergi menurut pasien',
} as const;
