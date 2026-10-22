<?php

namespace App\Http\Requests;

use App\Models\Kunjungan;
use App\PerawatClinicResolver;
use App\VisitSchedulingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreScreeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Kunjungan $visit */
        $visit = $this->route('kunjungan');
        $visit->loadMissing('poliklinik');
        abort_unless(in_array($visit->poliklinik?->jenis, ['umum', 'gigi'], true), 404);
        if ($this->user()->role === 'perawat') {
            abort_unless((int) $visit->poliklinik_id === app(PerawatClinicResolver::class)->clinicId($this->user()), 404);
        }
        abort_unless(in_array($visit->status, ['menunggu', 'screening'], true), 422, 'Pengkajian awal sudah diteruskan dan tidak dapat diubah.');
        app(VisitSchedulingService::class)->ensureServiceDate($visit);

        return in_array($this->user()->role, ['admin', 'perawat'], true);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $isDental = $this->route('kunjungan')->poliklinik?->jenis === 'gigi';
        $required = $this->input('tindakan', 'kirim') === 'kirim' ? 'required' : 'nullable';
        $rules = [
            'tindakan' => ['sometimes', 'in:draf,kirim'],
            'petugas_id' => [Rule::excludeIf($this->user()->role === 'perawat'), 'required', Rule::exists('nakes', 'id')->whereIn('jabatan', ['perawat', 'bidan', 'terapis_gigi'])->where('poliklinik_id', $this->route('kunjungan')->poliklinik_id)->where('is_active', true)->whereNull('deleted_at')],
            'keluhan' => [$required, 'string', 'max:5000'],
            'keluhan_tambahan' => ['nullable', 'string', 'max:5000'],
            'lama_keluhan' => [$required, 'string', 'max:255'],
            'sumber_informasi' => [$required, 'in:pasien,keluarga,pendamping,tidak_dapat_diperoleh'],
            'riwayat_obat' => ['nullable', 'string', 'max:5000'],
            'status_alergi' => [$required, 'in:ada,tidak_ada,belum_diketahui'],
            'status_riwayat_penyakit' => [$required, 'in:ada,tidak_ada,belum_diketahui'],
            'konfirmasi_riwayat' => [Rule::excludeIf($required === 'nullable'), 'required', 'accepted'],
            'riwayat_alergi' => ['nullable', 'string', 'max:5000'],
            'riwayat_penyakit' => ['nullable', 'string', 'max:5000'],
            'riwayat_penyakit_keluarga' => ['nullable', 'string', 'max:5000'],
            'alergi_jenis' => ['nullable', 'string', 'max:255'],
            'alergi_reaksi' => ['nullable', 'string', 'max:5000'],
            'penyakit_nama' => ['nullable', 'string', 'max:255'],
            'penyakit_keterangan' => ['nullable', 'string', 'max:5000'],
            'skala_nyeri' => ['nullable', 'integer', 'between:0,10'],
            'risiko_jatuh' => ['nullable', 'in:rendah,sedang,tinggi'],
            'risiko_nyeri' => ['nullable', 'string', 'max:255'],
            'skrining_gizi' => ['nullable', 'in:normal,kurang,lebih'],
            'pemeriksaan_fisik' => ['nullable', 'string', 'max:5000'],
            'alasan_pengukuran_tidak_lengkap' => ['nullable', 'string', 'min:5', 'max:2000'],
            'triase_perawat' => ['nullable', 'in:merah,kuning,hijau'],
            'catatan_triase' => ['nullable', 'string', 'max:2000'],
            'nyeri_dada' => ['nullable', 'in:ya,tidak'],
            'kejang' => ['nullable', 'in:ya,tidak'],
            'nadi_teraba' => ['nullable', 'in:teraba,tidak_teraba'],
            'pola_pernapasan' => ['nullable', 'in:normal,tidak_normal'],
            'kesadaran' => ['nullable', 'in:sadar,menurun,tidak_sadar'],
            'kondisi_psikiatri' => ['nullable', 'in:normal,terganggu'],
            'risiko_jatuh_visual' => ['nullable', 'in:rendah,sedang,tinggi'],
            'lokasi_nyeri_gigi' => [Rule::excludeIf(! $isDental), 'nullable', 'string', 'max:255'],
            'durasi_keluhan_gigi' => [Rule::excludeIf(! $isDental), 'nullable', 'string', 'max:255'],
            'catatan_medis_gigi' => [Rule::excludeIf(! $isDental), 'nullable', 'string', 'max:5000'],
            'pemicu_nyeri_gigi' => [Rule::excludeIf(! $isDental), 'nullable', 'array', 'max:7'],
            'pemicu_nyeri_gigi.*' => ['string', 'distinct', 'in:dingin,manis,mengunyah,panas,spontan,lainnya,tidak_ada'],
            'risiko_medis_gigi' => [Rule::excludeIf(! $isDental), $required, 'array', $required === 'required' ? 'min:1' : 'min:0', 'max:8'],
            'risiko_medis_gigi.*' => ['string', 'distinct', 'in:hipertensi,diabetes,penyakit_jantung,gangguan_pembekuan,antikoagulan,lainnya,tidak_ada,belum_diketahui'],
            'riwayat_infeksi_gigi' => [Rule::excludeIf(! $isDental), 'nullable', 'array', 'max:4'],
            'riwayat_infeksi_gigi.*' => ['string', 'distinct', 'in:hepatitis,hiv,infeksi_lain,tidak_ada'],
        ];

        foreach (['td_sistole' => 300, 'td_diastole' => 200, 'nadi' => 300, 'respirasi' => 100, 'spo2' => 100] as $field => $maximum) {
            $rules[$field] = ['nullable', 'integer', 'min:0', 'max:'.$maximum];
        }
        foreach (['berat_badan' => 500, 'tinggi_badan' => 300, 'lingkar_perut' => 300] as $field => $maximum) {
            $rules[$field] = ['nullable', 'numeric', 'gt:0', 'max:'.$maximum];
        }
        $rules['suhu'] = ['nullable', 'numeric', 'between:30,45'];

        return $rules;
    }

    /** @return array<callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $final = $this->input('tindakan', 'kirim') === 'kirim';
            if ($final) {
                foreach (['status_alergi' => 'riwayat_alergi', 'status_riwayat_penyakit' => 'riwayat_penyakit'] as $status => $detail) {
                    if ($this->input($status) === 'ada' && ! $this->filled($detail)) {
                        $validator->errors()->add($detail, 'Jelaskan riwayat yang ditemukan sebelum meneruskan pasien.');
                    }
                }
                foreach (['td_sistole', 'td_diastole', 'nadi', 'suhu', 'respirasi'] as $field) {
                    if (! $this->filled($field)) {
                        $validator->errors()->add($field, 'Isi hasil pengukuran sebelum meneruskan pasien.');
                    }
                }
            }
            if ($this->filled('td_sistole') && $this->filled('td_diastole') && $this->integer('td_sistole') <= $this->integer('td_diastole')) {
                $validator->errors()->add('td_diastole', 'Tekanan diastole harus lebih rendah daripada sistole. Periksa kembali pengukuran.');
            }
            if ($this->route('kunjungan')->poliklinik?->jenis !== 'gigi') {
                return;
            }
            foreach (['pemicu_nyeri_gigi', 'risiko_medis_gigi', 'riwayat_infeksi_gigi'] as $field) {
                $values = $this->input($field, []) ?? [];
                if (array_intersect(['tidak_ada', 'belum_diketahui'], $values) && count($values) > 1) {
                    $validator->errors()->add($field, 'Pilihan Tidak ada atau Belum diketahui tidak dapat digabung dengan pilihan lain.');
                }
            }
            $risks = $this->input('risiko_medis_gigi', []) ?? [];
            if ($final && $risks === ['belum_diketahui'] && ! $this->filled('catatan_medis_gigi')) {
                $validator->errors()->add('catatan_medis_gigi', 'Catat kendala pengkajian dan informasi yang perlu dikonfirmasi dokter gigi.');
            }
            if ($final && $this->input('status_riwayat_penyakit') === 'tidak_ada' && array_intersect(['hipertensi', 'diabetes', 'penyakit_jantung', 'gangguan_pembekuan'], $risks)) {
                $validator->errors()->add('status_riwayat_penyakit', 'Riwayat penyakit tidak sesuai dengan risiko medis gigi yang dipilih.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => ':attribute wajib diisi sebelum meneruskan pasien.',
            'in' => 'Pilih jawaban :attribute yang tersedia.',
            'konfirmasi_riwayat.accepted' => 'Konfirmasi riwayat pasien setelah melakukan pengkajian.',
            'petugas_id.exists' => 'Pilih petugas aktif yang ditugaskan di poli ini.',
            'numeric' => ':attribute harus berupa angka.',
            'integer' => ':attribute harus berupa bilangan bulat.',
            'max' => ':attribute melebihi batas yang diizinkan (:max).',
            'between' => ':attribute harus berada dalam rentang :min sampai :max.',
            'gt' => ':attribute harus lebih besar dari :value.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'petugas_id' => 'Petugas pelaksana', 'keluhan' => 'Keluhan utama', 'lama_keluhan' => 'Lama keluhan',
            'sumber_informasi' => 'Sumber informasi', 'status_alergi' => 'Status riwayat alergi',
            'status_riwayat_penyakit' => 'Status riwayat penyakit', 'konfirmasi_riwayat' => 'Konfirmasi riwayat',
            'td_sistole' => 'Tekanan darah sistole', 'td_diastole' => 'Tekanan darah diastole', 'nadi' => 'Denyut nadi',
            'alasan_pengukuran_tidak_lengkap' => 'Catatan Pengkajian',
            'respirasi' => 'Frekuensi napas', 'suhu' => 'Suhu tubuh', 'spo2' => 'Saturasi oksigen',
            'berat_badan' => 'Berat badan', 'tinggi_badan' => 'Tinggi badan', 'lingkar_perut' => 'Lingkar perut',
            'nyeri_dada' => 'Nyeri dada', 'kejang' => 'Kejang',
            'nadi_teraba' => 'Keterabaan nadi', 'pola_pernapasan' => 'Pola pernapasan', 'kesadaran' => 'Kesadaran',
            'kondisi_psikiatri' => 'Kondisi psikiatri', 'risiko_jatuh_visual' => 'Risiko jatuh visual',
            'risiko_medis_gigi' => 'Riwayat sistemik untuk tindakan gigi',
        ];
    }
}
