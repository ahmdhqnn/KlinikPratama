<?php

namespace App\Http\Controllers\Master;

use App\ClinicalAuditRecorder;
use App\Http\Controllers\Controller;
use App\Models\Poliklinik;
use App\Models\RuangPoli;
use App\Models\Tindakan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PoliklinikController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'jenis' => ['nullable', 'in:umum,gigi'],
            'status' => ['nullable', 'in:aktif,nonaktif'],
        ]);
        $poliklinik = Poliklinik::query()
            ->whereIn('jenis', ['umum', 'gigi'])
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(function ($query) use ($search): void {
                $query->where('nama', 'like', "%{$search}%")
                    ->orWhere('kode', 'like', "%{$search}%");
            }))
            ->when($filters['jenis'] ?? null, fn ($query, string $type) => $query->where('jenis', $type))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('is_active', $status === 'aktif'))
            ->orderBy('kode')
            ->paginate(10)
            ->withQueryString();

        $clinicIds = Poliklinik::query()->whereIn('jenis', ['umum', 'gigi'])->pluck('id');

        return Inertia::render('master/poliklinik/index', [
            'stats' => [
                'total' => $clinicIds->count(),
                'active' => Poliklinik::query()->whereIn('jenis', ['umum', 'gigi'])->where('is_active', true)->count(),
                'totalRooms' => RuangPoli::query()->whereIn('poliklinik_id', $clinicIds)->count(),
                'activeRooms' => RuangPoli::query()->whereIn('poliklinik_id', $clinicIds)->where('is_active', true)->count(),
            ],
            'clinics' => [
                'data' => $poliklinik->getCollection()->map(fn (Poliklinik $clinic): array => [
                    'id' => $clinic->id,
                    'code' => $clinic->kode,
                    'name' => $clinic->nama,
                    'type' => $clinic->jenis,
                    'active' => $clinic->is_active,
                ])->values(),
                'currentPage' => $poliklinik->currentPage(),
                'lastPage' => $poliklinik->lastPage(),
                'perPage' => $poliklinik->perPage(),
                'total' => $poliklinik->total(),
                'from' => $poliklinik->firstItem(),
                'to' => $poliklinik->lastItem(),
                'previousUrl' => $poliklinik->previousPageUrl(),
                'nextUrl' => $poliklinik->nextPageUrl(),
            ],
            'filters' => ['search' => $filters['search'] ?? '', 'jenis' => $filters['jenis'] ?? '', 'status' => $filters['status'] ?? ''],
        ]);
    }

    public function show(Poliklinik $poliklinik): Response
    {
        abort_unless(in_array($poliklinik->jenis, ['umum', 'gigi'], true), 404);
        $poliklinik->load([
            'ruangPoli' => fn ($query) => $query->orderBy('nama'),
            'tindakan' => fn ($query) => $query->where('kategori', 'medis')->orderBy('kode')->orderBy('nama'),
        ]);

        return Inertia::render('master/poliklinik/show', [
            'clinic' => [
                'id' => $poliklinik->id,
                'code' => $poliklinik->kode,
                'name' => $poliklinik->nama,
                'type' => $poliklinik->jenis,
                'rooms' => $poliklinik->ruangPoli->map(fn (RuangPoli $room): array => [
                    'id' => $room->id,
                    'code' => $room->kode,
                    'name' => $room->nama,
                    'satusehatLocationId' => $room->satusehat_location_id,
                    'physicalTypeCode' => $room->physical_type_code ?? 'ro',
                    'active' => $room->is_active,
                ])->values(),
                'treatments' => $poliklinik->tindakan->map(fn (Tindakan $item): array => [
                    'id' => $item->id,
                    'code' => $item->kode,
                    'icd9Code' => $item->kode_icd9,
                    'name' => $item->nama,
                    'canDelegateToNurse' => (bool) $item->can_delegate_to_nurse,
                    'consentMode' => $item->consentMode(),
                    'consentModeLabel' => $item->consentModeLabel(),
                    'active' => (bool) $item->is_active,
                ])->values(),
            ],
            'consentModes' => Tindakan::CONSENT_MODES,
        ]);
    }

    public function storeRuang(Request $request, Poliklinik $poliklinik): RedirectResponse
    {
        abort_unless(in_array($poliklinik->jenis, ['umum', 'gigi'], true), 404);
        $data = $request->validate([
            'kode' => ['nullable', 'string', 'max:32'],
            'nama' => ['required', 'string', 'max:100', Rule::unique('ruang_poli')->where('poliklinik_id', $poliklinik->id)],
            'satusehat_location_id' => ['nullable', 'string', 'max:64'],
            'physical_type_code' => ['nullable', 'string', 'max:16'],
        ]);

        $poliklinik->ruangPoli()->create([
            'kode' => $data['kode'] ?? null,
            'nama' => $data['nama'],
            'satusehat_location_id' => $data['satusehat_location_id'] ?? null,
            'physical_type_code' => $data['physical_type_code'] ?? 'ro',
            'is_active' => true,
        ]);

        return back()->with('success', 'Ruang poli berhasil ditambahkan.');
    }

    public function updateRuang(Request $request, RuangPoli $ruang): RedirectResponse
    {
        abort_unless(in_array($ruang->poliklinik?->jenis, ['umum', 'gigi'], true), 404);
        $data = $request->validate([
            'kode' => ['nullable', 'string', 'max:32'],
            'nama' => ['required', 'string', 'max:100', Rule::unique('ruang_poli')->where('poliklinik_id', $ruang->poliklinik_id)->ignore($ruang)],
            'satusehat_location_id' => ['nullable', 'string', 'max:64'],
            'physical_type_code' => ['nullable', 'string', 'max:16'],
            'is_active' => ['required', 'boolean'],
        ]);
        $ruang->update($data);

        return back()->with('success', 'Ruang poli berhasil diperbarui.');
    }

    public function storeTindakan(Request $request, Poliklinik $poliklinik, ClinicalAuditRecorder $audit): RedirectResponse
    {
        abort_unless(in_array($poliklinik->jenis, ['umum', 'gigi'], true), 404);
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:32', Rule::unique('tindakan', 'kode')],
            'kode_icd9' => ['nullable', 'string', 'max:32'],
            'nama' => ['required', 'string', 'max:180'],
            'can_delegate_to_nurse' => ['required', 'boolean'],
            'consent_mode' => ['required', Rule::in(array_keys(Tindakan::CONSENT_MODES))],
        ]);

        $procedure = $poliklinik->tindakan()->create([
            'kode' => trim($data['kode']),
            'kode_icd9' => filled($data['kode_icd9'] ?? null) ? trim((string) $data['kode_icd9']) : null,
            'nama' => trim($data['nama']),
            'kategori' => 'medis',
            'can_delegate_to_nurse' => (bool) $data['can_delegate_to_nurse'],
            'consent_mode' => $data['consent_mode'],
            'is_active' => true,
        ]);

        $audit->record($request, 'master_tindakan.created', metadata: [
            'tindakan_id' => $procedure->id,
            'poliklinik_id' => $poliklinik->id,
            'consent_mode' => $procedure->consent_mode,
            'can_delegate_to_nurse' => $procedure->can_delegate_to_nurse,
        ]);

        return back()->with('success', 'Master tindakan poliklinik berhasil ditambahkan.');
    }

    public function updateTindakan(Request $request, Tindakan $tindakan, ClinicalAuditRecorder $audit): RedirectResponse
    {
        abort_unless(in_array($tindakan->poliklinik?->jenis, ['umum', 'gigi'], true), 404);
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:32', Rule::unique('tindakan', 'kode')->ignore($tindakan)],
            'kode_icd9' => ['nullable', 'string', 'max:32'],
            'nama' => ['required', 'string', 'max:180'],
            'can_delegate_to_nurse' => ['required', 'boolean'],
            'consent_mode' => ['required', Rule::in(array_keys(Tindakan::CONSENT_MODES))],
            'is_active' => ['required', 'boolean'],
        ]);

        $tindakan->update([
            'kode' => trim($data['kode']),
            'kode_icd9' => filled($data['kode_icd9'] ?? null) ? trim((string) $data['kode_icd9']) : null,
            'nama' => trim($data['nama']),
            'can_delegate_to_nurse' => (bool) $data['can_delegate_to_nurse'],
            'consent_mode' => $data['consent_mode'],
            'is_active' => (bool) $data['is_active'],
        ]);

        $audit->record($request, 'master_tindakan.updated', metadata: [
            'tindakan_id' => $tindakan->id,
            'poliklinik_id' => $tindakan->poliklinik_id,
            'consent_mode' => $tindakan->consent_mode,
            'can_delegate_to_nurse' => $tindakan->can_delegate_to_nurse,
            'is_active' => $tindakan->is_active,
        ]);

        return back()->with('success', 'Master tindakan poliklinik berhasil diperbarui.');
    }
}
