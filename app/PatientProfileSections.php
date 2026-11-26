<?php

namespace App;

use App\Models\FarmasiMandiri;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Poliklinik;
use App\Models\TindakanKunjungan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

final class PatientProfileSections
{
    private const SECTIONS = [
        'informasi' => ['Informasi pasien', ['admin', 'pendaftaran', 'dokter', 'perawat', 'farmasi']],
        'kunjungan' => ['Riwayat kunjungan', ['admin', 'pendaftaran', 'dokter', 'perawat']],
        'kontrol' => ['Kontrol lanjutan', ['admin', 'pendaftaran', 'dokter', 'perawat']],
        'catatan-medis' => ['Catatan medis', ['dokter', 'perawat']],
        'pengkajian' => ['Pengkajian awal', ['dokter', 'perawat']],
        'odontogram' => ['Odontogram', ['dokter', 'perawat']],
        'penunjang' => ['Penunjang dan lampiran', ['dokter', 'perawat']],
        'tindakan' => ['Tindakan', ['dokter', 'perawat']],
        'obat' => ['Resep dan penyerahan obat', ['dokter', 'perawat', 'farmasi']],
        'obat-langsung' => ['Pemberian obat langsung', ['farmasi']],
        'persetujuan' => ['Persetujuan dan penolakan', ['admin', 'pendaftaran', 'dokter', 'perawat']],
        'surat' => ['Surat medis', ['dokter']],
        'rujukan' => ['Rujukan', ['admin', 'pendaftaran', 'dokter']],
        'pelepasan' => ['Pelepasan informasi', ['pendaftaran', 'dokter']],
        'dokumen' => ['Dokumen pasien', ['admin', 'pendaftaran', 'dokter', 'perawat']],
    ];

    public function __construct(private GeneralConsentService $generalConsent) {}

    public function load(Request $request, HasMany $visits): array
    {
        $role = $request->user()->role;
        $patient = $visits->getParent();
        $sections = collect(self::SECTIONS)->filter(fn (array $menu): bool => in_array($role, $menu[1], true));
        $section = $request->query('bagian', 'informasi');
        abort_unless(is_string($section) && isset(self::SECTIONS[$section]), 404);
        $direct = FarmasiMandiri::query()->whereBelongsTo($patient, 'pasien');
        if ($role === 'farmasi') {
            $pharmacyClinicId = app(FarmasiClinicResolver::class)->scopedClinicIdOrFail($request->user());
            if ($pharmacyClinicId !== null) {
                $direct->forClinic($pharmacyClinicId);
            }
            $visits->where(fn (Builder $query) => $query->where('status', 'farmasi')
                ->orWhereHas('farmasi', fn (Builder $pharmacy) => $pharmacy->where('status', 'selesai')))
                ->whereDate('tanggal', '<=', today());
            abort_unless((clone $visits)->exists() || (clone $direct)->exists(), 404);
        }
        abort_unless($sections->has($section), 403);
        $menu = $sections->map(fn (array $item, string $key): array => [
            'id' => $key,
            'label' => $item[0],
            'url' => route('pelayanan.pasien.show', [$patient, ...($key === 'informasi' ? [] : ['bagian' => $key])]),
        ])->values();

        $result = [
            'section' => $section,
            'menu' => $menu,
            'visits' => [],
            'directRecords' => [],
            'pagination' => null,
            'visitTab' => $request->query('visit_tab') === 'upcoming' ? 'upcoming' : 'all',
            'upcomingCount' => 0,
            'visitFilters' => [
                'poli' => $request->string('visit_poli')->toString(),
                'dokter' => $request->string('visit_dokter')->toString(),
                'jenis' => $request->string('visit_jenis')->toString(),
                'status' => $request->string('visit_status')->toString(),
                'tahun' => $request->string('visit_tahun')->toString(),
            ],
            'visitFilterOptions' => [
                'clinics' => [],
                'doctors' => [],
                'years' => [],
            ],
        ];
        if ($section === 'dokumen') {
            return $result;
        }
        if ($section === 'obat-langsung') {
            $records = $direct->with('obat:id,nama,satuan_kecil')->latest('diserahkan_pada')->latest('id')
                ->paginate(10)->appends(['bagian' => $section]);
            $result['directRecords'] = $records->getCollection()->map(fn (FarmasiMandiri $record): array => [
                'id' => $record->id,
                'number' => $record->nomor,
                'date' => $record->diserahkan_pada?->format('d/m/Y H:i'),
                'medicine' => $record->obat?->nama,
                'quantity' => $record->jumlah,
                'unit' => $record->obat?->satuan_kecil,
                'url' => route('farmasi.pemberian-obat.cetak', [$record, 'asal' => 'profil']),
            ])->values();
            $result['pagination'] = $this->pagination($records);

            return $result;
        }

        if (in_array($section, ['informasi', 'kunjungan'], true)) {
            $baseVisits = clone $visits;
            $result['upcomingCount'] = (clone $baseVisits)
                ->where('status', 'menunggu')
                ->where(fn (Builder $q) => $q->whereDate('tanggal', '>', today())->orWhere('kontrol_pending', true))
                ->count();
            $clinicIds = (clone $baseVisits)->select('poliklinik_id')->distinct()->pluck('poliklinik_id')->filter()->values();
            $result['visitFilterOptions']['clinics'] = Poliklinik::query()
                ->whereIn('id', $clinicIds)
                ->orderBy('nama')
                ->get(['id', 'nama'])
                ->map(fn ($c): array => ['id' => $c->id, 'name' => $c->nama])
                ->all();
            $doctorIds = (clone $baseVisits)->select('dokter_id')->distinct()->pluck('dokter_id')->filter()->values();
            $result['visitFilterOptions']['doctors'] = Nakes::query()
                ->whereIn('id', $doctorIds)
                ->orderBy('nama')
                ->get(['id', 'nama'])
                ->map(fn ($d): array => ['id' => $d->id, 'name' => $d->nama])
                ->all();
            $result['visitFilterOptions']['years'] = (clone $baseVisits)
                ->select('tanggal')
                ->get()
                ->map(fn ($v): ?string => $v->tanggal?->format('Y'))
                ->filter()
                ->unique()
                ->sortDesc()
                ->values()
                ->all();

            if ($result['visitTab'] === 'upcoming') {
                $visits->where('status', 'menunggu')
                    ->where(fn (Builder $q) => $q->whereDate('tanggal', '>', today())->orWhere('kontrol_pending', true));
            }
            if ($request->filled('visit_poli')) {
                $visits->where('poliklinik_id', $request->integer('visit_poli'));
            }
            if ($request->filled('visit_dokter')) {
                $visits->where('dokter_id', $request->integer('visit_dokter'));
            }
            if ($request->filled('visit_jenis')) {
                match ($request->string('visit_jenis')->toString()) {
                    'kontrol' => $visits->whereNotNull('kontrol_asal_id'),
                    'rujukan_internal' => $visits->whereNull('kontrol_asal_id')->whereNotNull('rujukan_internal_id'),
                    'baru' => $visits->whereNull('kontrol_asal_id')->whereNull('rujukan_internal_id')->where('jenis_pasien', 'baru'),
                    'lama' => $visits->whereNull('kontrol_asal_id')->whereNull('rujukan_internal_id')->where('jenis_pasien', '!=', 'baru'),
                    default => null,
                };
            }
            if ($request->filled('visit_status')) {
                $visits->where('status', $request->string('visit_status')->toString());
            }
            if ($request->filled('visit_tahun') && preg_match('/^\d{4}$/', $request->string('visit_tahun')->toString())) {
                $visits->whereYear('tanggal', (int) $request->string('visit_tahun')->toString());
            }
        }

        $with = ['poliklinik:id,nama,jenis', 'ruangPoli:id,nama', 'dokter:id,nama', 'pemeriksaan:id,kunjungan_id,started_at,anamnesis', 'farmasi:id,kunjungan_id,status'];
        if (in_array($role, ['dokter', 'perawat'], true)) {
            $with[] = 'screening:id,kunjungan_id,keluhan';
        }
        $visits->with($with)->latest('tanggal')->latest('id');
        $this->filterVisits($visits, $section, $role);
        $records = $section === 'informasi'
            ? $visits->limit(5)->get()
            : $visits->paginate(10)->appends($request->only(['bagian', 'visit_tab', 'visit_poli', 'visit_dokter', 'visit_jenis', 'visit_status', 'visit_tahun']));
        $items = $records instanceof LengthAwarePaginator ? $records->getCollection() : $records;
        $canSeeClinical = in_array($role, ['dokter', 'perawat'], true);
        $result['visits'] = $items->map(fn (Kunjungan $visit): array => [
            'id' => $visit->id,
            'number' => $visit->no_kunjungan,
            'date' => $visit->tanggal?->locale('id')->isoFormat('D MMM Y'),
            'time' => $visit->arrived_at?->format('H:i'),
            'clinic' => $visit->poliklinik?->nama ?? 'Belum dicatat',
            'room' => $visit->ruangPoli?->nama,
            'complaint' => $canSeeClinical ? ($visit->screening?->keluhan ?: $visit->pemeriksaan?->anamnesis) : null,
            'visitType' => $visit->kontrol_asal_id
                ? 'Kontrol'
                : ($visit->rujukan_internal_id ? 'Rujukan internal' : ($visit->jenis_pasien === 'baru' ? 'Baru' : 'Lama')),
            'doctor' => $visit->dokter?->nama ?? 'Belum ditentukan',
            'status' => $visit->status,
            'examinationStarted' => $visit->pemeriksaan?->started_at !== null,
            'isReservation' => $visit->status === 'menunggu' && ($visit->isPendingReservation() || $visit->tanggal?->isFuture()),
            'actions' => $this->actions($visit, $section, $role),
        ])->values();
        $result['pagination'] = $records instanceof LengthAwarePaginator ? $this->pagination($records) : null;
        if ($section === 'persetujuan' && $items->isNotEmpty()) {
            $summary = $this->generalConsent->summary($items->first());
            $result['generalConsent'] = [
                'status' => $summary['status'],
                'recordedAt' => $summary['recordedAt'],
                'versionCurrent' => $summary['versionCurrent'],
            ];
        }

        return $result;
    }

    private function filterVisits(HasMany $visits, string $section, string $role): void
    {
        match ($section) {
            'kontrol' => $visits->where(fn (Builder $query) => $query->whereNotNull('kontrol_asal_id')->orWhere('kontrol_pending', true)),
            'catatan-medis' => $visits->whereHas('pemeriksaan'),
            'pengkajian' => $visits->whereHas('screening'),
            'odontogram' => $visits->whereHas('odontogramFindings'),
            'penunjang' => $visits->where(fn (Builder $query) => $query->whereJsonLength('lampiran', '>', 0)
                ->orWhereHas('pemeriksaan', fn (Builder $exam) => $exam->where(fn (Builder $results) => $results
                    ->whereNotNull('supporting_exams->laboratory')->orWhereNotNull('supporting_exams->rapid')
                    ->orWhereNotNull('supporting_exams->radiology')->orWhereNotNull('supporting_exams->results')))),
            'tindakan' => $visits->whereHas('tindakanKunjungan'),
            'obat' => $role === 'farmasi' ? null : $visits->whereHas('resep.resepObat'),
            'persetujuan' => in_array($role, ['dokter', 'perawat'], true)
                ? $visits->with(['tindakanKunjungan' => fn (HasMany $items) => $items
                    ->where(fn (Builder $item) => $item->where('consent_review_required', true)->orWhereHas('informedConsents'))
                    ->with('tindakan:id,nama')])
                : null,
            'surat' => $visits->whereHas('suratMedis')->with('suratMedis:id,kunjungan_id,jenis,nomor_surat'),
            'rujukan' => $visits->where(fn (Builder $query) => $query->whereHas('rujukanInternal')
                ->when($role === 'dokter', fn (Builder $doctor) => $doctor->orWhereHas('suratMedis', fn (Builder $letters) => $letters->where('jenis', 'rujukan'))))
                ->with(['rujukanInternal.kePoli', 'rujukanInternal.kunjunganTujuanTerakhir',
                    ...($role === 'dokter' ? ['suratMedis' => fn (HasMany $letters) => $letters->where('jenis', 'rujukan')] : [])]),
            default => null,
        };
    }

    private function actions(Kunjungan $visit, string $section, string $role): array
    {
        $action = fn (string $label, string $name, mixed $parameters): array => ['label' => $label, 'url' => route($name, $parameters)];
        $record = fn (string $label): array => $action($label, 'pelayanan.pasien.rekam-medis', [
            $visit->pasien_id, 'kunjungan_id' => $visit->id,
        ]);
        $pharmacy = fn (): array => $visit->farmasi?->status === 'selesai'
            ? $action('Lihat penyerahan', 'farmasi.pasien.kunjungan', [$visit, 'asal' => 'profil'])
            : $action('Buka layanan farmasi', 'pelayanan.farmasi.show', $visit);
        $letter = fn ($item): array => $action($item->nomor_surat ?: 'Lihat surat', 'persuratan.surat.cetak', [$item, 'asal' => $section === 'rujukan' ? 'profil-rujukan' : 'profil']);

        return match ($section) {
            'informasi', 'kunjungan', 'kontrol' => [match ($role) {
                'dokter' => $visit->status !== 'batal' && $visit->tanggal && ! $visit->tanggal->isFuture() && ! $visit->isPendingReservation()
                    ? $action('Detail layanan', 'pelayanan.pemeriksaan.show', $visit)
                    : $record('Lihat riwayat'),
                'farmasi' => $pharmacy(),
                'pendaftaran' => $action('Detail kunjungan', 'pendaftaran.kunjungan.show', $visit),
                default => $action('Detail kunjungan', 'pelayanan.kunjungan.show', $visit),
            }],
            'catatan-medis', 'odontogram', 'penunjang', 'tindakan' => [$record('Lihat catatan kunjungan')],
            'pengkajian' => [$role === 'perawat' && in_array($visit->poliklinik?->jenis, ['umum', 'gigi'], true)
                ? $action('Lihat pengkajian', 'pelayanan.screening.print', $visit)
                : $record('Lihat pengkajian')],
            'obat' => [$role === 'farmasi' ? $pharmacy() : $record('Lihat riwayat resep')],
            'persetujuan' => [
                $action('Persetujuan umum', 'general-consent.show', [$visit, 'asal' => 'profil']),
                ...($visit->relationLoaded('tindakanKunjungan') ? $visit->tindakanKunjungan->map(fn (TindakanKunjungan $procedure): array => $action(
                    $procedure->nama_tindakan ?? $procedure->nama_tindakan_manual ?? $procedure->tindakan?->nama ?? 'Persetujuan tindakan',
                    'procedure-consent.show', [$visit, $procedure, 'asal' => 'profil'],
                ))->all() : []),
            ],
            'surat' => $visit->suratMedis->map($letter)->all(),
            'rujukan' => [
                ...($role === 'dokter' ? $visit->suratMedis->map($letter)->all() : []),
                ...$visit->rujukanInternal->map(function ($referral) use ($action, $visit, $role): array {
                    $referral->setRelation('kunjungan', $visit);
                    $label = $referral->kePoli?->nama ?? 'Rujukan internal';
                    $status = match ($referral->statusLayanan()) {
                        'terdaftar' => 'Terdaftar', 'dilayani' => 'Dilayani', 'selesai' => 'Selesai', 'batal' => 'Batal',
                        default => 'Menunggu',
                    };
                    if ($role === 'dokter') {
                        return $action($label.' · '.$status, 'persuratan.rujukan.cetak', [$referral, 'asal' => 'profil-rujukan']);
                    }
                    if ($referral->canRegisterDestination()) {
                        return $action('Jadwalkan '.$label, 'pendaftaran.pendaftaran-baru', ['rujukan_internal_id' => $referral->id]);
                    }

                    return ['label' => $label.' · '.$status, 'url' => $referral->kunjunganTujuanTerakhir
                        ? route('pendaftaran.kunjungan.show', $referral->kunjunganTujuanTerakhir) : null];
                })->all(),
            ],
            'pelepasan' => [$action('Buka register pelepasan', 'information-release.show', $visit)],
        };
    }

    private function pagination(LengthAwarePaginator $records): array
    {
        return [
            'currentPage' => $records->currentPage(),
            'lastPage' => $records->lastPage(),
            'from' => $records->firstItem(),
            'to' => $records->lastItem(),
            'total' => $records->total(),
            'previousUrl' => $records->previousPageUrl(),
            'nextUrl' => $records->nextPageUrl(),
        ];
    }
}
