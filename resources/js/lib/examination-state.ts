interface ExaminationVisit {
    status: string;
    canClaim: boolean;
    isReservation?: boolean;
    examination?: { status?: string; signedAt?: string | null; integrityValid?: boolean | null } | null;
}
interface ExaminationNotice { title: string; description: string; destructive?: boolean }

export function examinationState(visit: ExaminationVisit) {
    const signed = Boolean(visit.examination?.signedAt);
    const invalid = visit.examination?.integrityValid === false;
    const finalized = signed || visit.examination?.status === 'selesai';
    const canStart = !invalid && !finalized && !visit.isReservation && visit.status === 'pemeriksaan' && (!visit.examination || visit.canClaim);
    const canEdit = !visit.canClaim && Boolean(visit.examination) && !invalid && !finalized && visit.status === 'pemeriksaan';
    const canCorrect = !visit.canClaim && signed && visit.examination?.integrityValid === true && ['tindakan', 'farmasi', 'kasir', 'selesai'].includes(visit.status);
    let notice: ExaminationNotice | null = null;
    if (invalid) {
        notice = { title: 'Catatan perlu diverifikasi', description: 'Keutuhan catatan belum dapat diverifikasi. Perubahan dihentikan sementara; hubungi pengelola rekam medis untuk pemeriksaan data.', destructive: true };
    } else if (visit.status === 'batal') {
        notice = { title: 'Kunjungan dibatalkan', description: 'Pemeriksaan tidak dapat dilanjutkan pada kunjungan ini. Data yang sudah tercatat tetap tersedia untuk ditinjau.' };
    } else if (signed) {
        notice = { title: 'Pemeriksaan telah difinalisasi', description: canCorrect ? 'Catatan pemeriksaan telah ditandatangani. Koreksi dapat dicatat melalui addendum dengan alasan perubahan.' : 'Catatan pemeriksaan telah ditandatangani dan tersedia untuk ditinjau. Perubahan langsung tidak tersedia.' };
    } else if (visit.status === 'menunggu') {
        notice = visit.isReservation
            ? { title: 'Reservasi kunjungan', description: 'Kunjungan dijadwalkan untuk tanggal mendatang. Pemeriksaan dapat dimulai setelah pasien hadir dan pengkajian awal selesai.' }
            : { title: 'Menunggu pengkajian awal', description: 'Pasien belum menyelesaikan pengkajian awal perawat. Pemeriksaan dokter dapat diisi setelah pasien diteruskan ke tahap pemeriksaan.' };
    } else if (visit.status === 'screening') {
        notice = { title: 'Pengkajian awal berlangsung', description: 'Perawat sedang melengkapi pengkajian awal. Pemeriksaan dokter dapat diisi setelah pengkajian diselesaikan.' };
    } else if (canStart) {
        notice = { title: 'Menunggu pemeriksaan', description: 'Pengkajian awal sudah selesai. Klik Mulai pemeriksaan saat Anda mulai memeriksa pasien.' };
    } else if (!canEdit) {
        notice = { title: visit.status === 'selesai' ? 'Kunjungan telah selesai' : 'Pemeriksaan hanya dapat ditinjau', description: finalized ? 'Pemeriksaan sudah ditandai selesai. Catatan ini belum memiliki tanda tangan final; hubungi pengelola rekam medis bila diperlukan koreksi.' : 'Kunjungan tidak berada pada tahap pemeriksaan dokter. Belum ada catatan pemeriksaan final yang dapat dikoreksi melalui addendum.' };
    }
    return { canStart, canEdit, canCorrect, notice };
}
