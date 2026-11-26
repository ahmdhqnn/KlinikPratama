<?php

namespace App;

use App\Models\RujukanInternal;
use Illuminate\Database\Eloquent\Builder;

class InternalReferralDirectory
{
    public function paginate(string $search, ?int $visitId, ?int $doctorId, ?string $destinationRoute, bool $canPrint, string $pageName = 'page', ?int $destinationClinicId = null, ?string $date = null, ?string $originClinicType = null): array
    {
        $referrals = RujukanInternal::query()
            ->when($originClinicType, fn (Builder $query, string $type) => $query->whereHas('dariPoli', fn (Builder $clinic) => $clinic->where('jenis', $type)))
            ->when($destinationClinicId, fn (Builder $query, int $id) => $query->where('ke_poli_id', $id))
            ->when($date, fn (Builder $query, string $value) => $query->whereDate('created_at', $value))
            ->when($visitId, fn (Builder $query, int|string $visitId) => $query->where('kunjungan_id', $visitId))
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $matches) => $matches
                ->where('nomor_surat', 'like', "%{$search}%")
                ->orWhereHas('kunjungan', fn (Builder $visit) => $visit->where('no_kunjungan', 'like', "%{$search}%"))
                ->orWhereHas('kunjunganTujuanTerakhir', fn (Builder $visit) => $visit->where('no_kunjungan', 'like', "%{$search}%"))
                ->orWhereHas('kunjungan.pasien', fn (Builder $patients) => $patients->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%"))))
            ->with(['kunjungan.pasien', 'kunjungan.dokter', 'dariPoli', 'kePoli', 'kunjunganTujuanTerakhir'])
            ->when($doctorId !== null, fn (Builder $query) => $query->whereHas('kunjungan', fn (Builder $visit) => $visit->where('dokter_id', $doctorId)))
            ->latest('id')->paginate(10, ['*'], $pageName)->withQueryString();

        return [
            'data' => $referrals->getCollection()->map(fn (RujukanInternal $referral): array => [
                'id' => $referral->id,
                'number' => $referral->nomor_surat,
                'date' => $referral->created_at?->format('d/m/Y'),
                'patient' => $referral->kunjungan?->pasien?->nama ?? 'Belum diisi',
                'visitNumber' => $referral->kunjungan?->no_kunjungan ?? 'Belum diisi',
                'fromClinic' => $referral->dariPoli?->nama ?? 'Belum diisi',
                'toClinic' => $referral->kePoli?->nama ?? 'Belum diisi',
                'doctor' => $referral->kunjungan?->dokter?->nama ?? 'Belum diisi',
                'status' => $referral->statusLayanan(),
                'destinationVisit' => $referral->kunjunganTujuanTerakhir?->no_kunjungan,
                'destinationDate' => $referral->kunjunganTujuanTerakhir?->tanggal?->format('d/m/Y'),
                'destinationUrl' => $destinationRoute && $referral->kunjunganTujuanTerakhir ? route($destinationRoute, $referral->kunjunganTujuanTerakhir) : null,
                'registrationUrl' => $destinationRoute && $referral->canRegisterDestination()
                    ? route('pendaftaran.pendaftaran-baru', ['rujukan_internal_id' => $referral->id])
                    : null,
                'printUrl' => $canPrint ? route('persuratan.rujukan.cetak', $referral) : null,
            ])->values(),
            'currentPage' => $referrals->currentPage(), 'lastPage' => $referrals->lastPage(),
            'from' => $referrals->firstItem(), 'to' => $referrals->lastItem(), 'total' => $referrals->total(),
            'previousUrl' => $referrals->previousPageUrl(), 'nextUrl' => $referrals->nextPageUrl(),
        ];
    }
}
