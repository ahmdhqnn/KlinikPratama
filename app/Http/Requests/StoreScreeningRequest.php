<?php

namespace App\Http\Requests;

use App\DentalAssessment;
use App\Models\Kunjungan;
use App\Models\Screening;
use App\PerawatClinicResolver;
use App\VisitSchedulingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreScreeningRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('assistance_reason'))) {
            $this->merge(['assistance_reason' => trim($this->input('assistance_reason'))]);
        }
        if ($this->route('kunjungan')?->poliklinik?->jenis === 'gigi') {
            if (in_array($this->input('status_riwayat_penyakit'), ['tidak_ada', 'belum_diketahui'], true) && ! $this->filled('risiko_medis_gigi')) {
                $this->merge(['risiko_medis_gigi' => [$this->input('status_riwayat_penyakit')], 'risiko_medis_gigi_lainnya' => null]);
            }
            if (in_array($this->input('status_riwayat_obat'), ['tidak_ada', 'belum_diketahui'], true)) {
                $this->merge(['dental_medication_categories' => [$this->input('status_riwayat_obat') === 'tidak_ada' ? 'none' : 'unknown'], 'dental_medication_categories_other' => null, 'riwayat_obat' => null]);
            }
        }
    }

    public function authorize(): bool
    {
        /** @var Kunjungan $visit */
        $visit = $this->route('kunjungan');
        $visit->loadMissing(['poliklinik', 'screening', 'pemeriksaan']);
        abort_unless(in_array($visit->poliklinik?->jenis, ['umum', 'gigi'], true), 404);
        if ($this->user()->role === 'perawat') {
            abort_unless((int) $visit->poliklinik_id === app(PerawatClinicResolver::class)->clinicId($this->user()), 404);
        }
        abort_unless($visit->canUpdateScreening(), 422, 'Pengkajian hanya dapat diperbarui sebelum dokter memulai pemeriksaan.');
        app(VisitSchedulingService::class)->ensureServiceDate($visit);

        return in_array($this->user()->role, ['admin', 'perawat'], true);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $isDental = $this->route('kunjungan')->poliklinik?->jenis === 'gigi';
        $wasSubmitted = $this->route('kunjungan')->screening?->submitted_at !== null;
        $required = $wasSubmitted || $this->input('tindakan', 'kirim') === 'kirim' ? 'required' : 'nullable';
        $rules = [
            'assistance_reason' => [Rule::excludeIf($this->user()->role !== 'admin'), 'required', 'string', 'min:10', 'max:1000'],
            'tindakan' => ['sometimes', Rule::in($wasSubmitted ? ['kirim'] : ['draf', 'kirim'])],
            'petugas_id' => [Rule::excludeIf($this->user()->role === 'perawat'), 'required', Rule::exists('nakes', 'id')->whereIn('jabatan', ['perawat', 'bidan', 'terapis_gigi'])->where('poliklinik_id', $this->route('kunjungan')->poliklinik_id)->where('is_active', true)->whereNull('deleted_at')],
            'keluhan' => [$required, 'string', 'max:5000'],
            'keluhan_tambahan' => ['nullable', 'string', 'max:5000'],
            'lama_keluhan' => [$required, 'string', 'max:255'],
            'sumber_informasi' => [$required, 'in:pasien,keluarga,pendamping,tidak_dapat_diperoleh'],
            'screening_revision' => ['sometimes', 'required', 'regex:/^(new|[a-f0-9]{64})$/D'],
            'dental_medication_categories' => [Rule::excludeIf(! $isDental), 'nullable', 'array', 'max:5'],
            'dental_medication_categories.*' => ['required', 'string', 'distinct', Rule::in(array_keys(Screening::dentalMedicationCategories()))],
            'riwayat_obat' => ['nullable', 'string', 'max:5000'],
            'status_alergi' => [$required, 'in:ada,tidak_ada,belum_diketahui,belum_dikonfirmasi'],
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
            'dental_vitals_unmeasurable' => [Rule::excludeIf(! $isDental), 'sometimes', 'boolean'],
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
            'extraoral_symmetry' => [Rule::excludeIf(! $isDental), 'nullable', 'in:symmetric,asymmetric,unknown'],
            'extraoral_swelling' => [Rule::excludeIf(! $isDental), 'nullable', 'in:none,present,unknown'],
            'extraoral_notes' => [Rule::excludeIf(! $isDental), 'nullable', 'string', 'max:2000'],
            'dental_red_flags' => [Rule::excludeIf(! $isDental), 'nullable', 'array', 'max:10'],
            'dental_red_flags.*' => ['string', 'distinct', Rule::in(array_keys(DentalAssessment::redFlagLabels()))],
            'riwayat_perawatan_gigi' => [Rule::excludeIf(! $isDental), 'nullable', 'string', 'max:5000'],
            'kebiasaan_gigi' => [Rule::excludeIf(! $isDental), 'nullable', 'string', 'max:5000'],
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

        foreach (Screening::SAFETY_FIELDS as $field) {
            $rules[$field] = ['nullable', 'string', 'max:2000'];
        }

        $rules['status_riwayat_obat'] = [Rule::excludeIf(! $isDental), 'nullable', Rule::in(['ada', 'tidak_ada', 'belum_diketahui'])];
        $rules['mukosa_mulut'] = [Rule::excludeIf(! $isDental), 'nullable', 'array:lidah,pipi,bibir,palatum,gusi'];
        $rules['mukosa_mulut.*'] = ['nullable', Rule::in(['normal', 'abnormal', 'belum_dinilai'])];
        $rules['kelainan_mukosa'] = [Rule::excludeIf(! $isDental), 'nullable', 'string', 'max:2000'];
        $rules['kebiasaan_gigi_opsi'] = [Rule::excludeIf(! $isDental), 'nullable', 'array', 'max:11'];
        $rules['kebiasaan_gigi_opsi.*'] = ['required', 'string', 'distinct', Rule::in(array_keys(Screening::ORAL_HABITS))];

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
                foreach (Screening::OTHER_DETAILS as $field => [$option, $detail]) {
                    if ($this->route('kunjungan')->poliklinik?->jenis === 'gigi' && in_array($option, $this->input($field, []) ?? [], true) && ! $this->filled($detail)) {
                        $validator->errors()->add($detail, 'Lengkapi keterangan pilihan lainnya sebelum dikirim.');
                    }
                }
                if ($this->input('status_alergi') === 'ada' && ! $this->filled('alergi_reaksi')) {
                    $validator->errors()->add('alergi_reaksi', 'Catat reaksi alergi; tulis belum diketahui jika belum dapat dipastikan.');
                }
                foreach (['status_alergi' => 'riwayat_alergi', 'status_riwayat_penyakit' => 'riwayat_penyakit'] as $status => $detail) {
                    if ($this->input($status) === 'ada' && ! $this->filled($detail) && ! ($status === 'status_riwayat_penyakit' && $this->route('kunjungan')->poliklinik?->jenis === 'gigi' && array_diff($this->input('risiko_medis_gigi', []) ?? [], ['tidak_ada', 'belum_diketahui']) !== [])) {
                        $validator->errors()->add($detail, 'Jelaskan riwayat yang ditemukan sebelum meneruskan pasien.');
                    }
                }
                $unmeasurable = $this->route('kunjungan')->poliklinik?->jenis === 'gigi' && $this->boolean('dental_vitals_unmeasurable');
                if ($unmeasurable && ! $this->filled('alasan_pengukuran_tidak_lengkap')) {
                    $validator->errors()->add('alasan_pengukuran_tidak_lengkap', 'Catat alasan TTV tidak dapat diukur, observasi cepat, dan serah terima ke dokter.');
                }
                foreach ($unmeasurable ? ['kesadaran', 'pola_pernapasan'] : ['td_sistole', 'td_diastole', 'nadi', 'suhu', 'respirasi'] as $field) {
                    if (! $this->filled($field)) {
                        $validator->errors()->add($field, $unmeasurable ? 'Catat hasil penilaian sebelum meneruskan pasien.' : 'Isi hasil pengukuran sebelum meneruskan pasien.');
                    }
                }
            }
            if ($this->filled('td_sistole') && $this->filled('td_diastole') && $this->integer('td_sistole') <= $this->integer('td_diastole')) {
                $validator->errors()->add('td_diastole', 'Tekanan diastole harus lebih rendah daripada sistole. Periksa kembali pengukuran.');
            }
            if ($this->route('kunjungan')->poliklinik?->jenis !== 'gigi') {
                return;
            }
            $habits = $this->input('kebiasaan_gigi_opsi', []) ?? [];
            if (array_intersect(['tidak_ada', 'belum_diketahui'], $habits) && count($habits) > 1) {
                $validator->errors()->add('kebiasaan_gigi_opsi', 'Pilihan tidak ada atau belum diketahui harus dipilih sendiri.');
            }
            if ($final && in_array('abnormal', $this->input('mukosa_mulut', []) ?? [], true) && ! $this->filled('kelainan_mukosa')) {
                $validator->errors()->add('kelainan_mukosa', 'Jelaskan lokasi dan kelainan mukosa yang ditemukan.');
            }
            if ($final && in_array('lainnya', $habits, true) && ! $this->filled('kebiasaan_gigi')) {
                $validator->errors()->add('kebiasaan_gigi', 'Jelaskan kebiasaan lain yang dilaporkan pasien.');
            }
            if ($final && $this->input('status_riwayat_obat') === 'ada' && ! $this->filled('riwayat_obat')) {
                $validator->errors()->add('riwayat_obat', 'Catat nama atau jenis obat; jelaskan bila rinciannya belum diketahui.');
            }
            $medications = $this->input('dental_medication_categories', []) ?? [];
            if (array_intersect(['none', 'unknown'], $medications) && count($medications) > 1) {
                $validator->errors()->add('dental_medication_categories', 'Pilih satu status atau kategori obat yang diketahui.');
            }
            if (in_array('none', $medications, true) && $this->filled('riwayat_obat')) {
                $validator->errors()->add('riwayat_obat', 'Hapus rincian obat atau ubah status obat yang dipilih.');
            }
            $flags = $this->input('dental_red_flags', []) ?? [];
            if (array_intersect(['none', 'unknown'], $flags) && count($flags) > 1) {
                $validator->errors()->add('dental_red_flags', 'Pilihan tidak ditemukan atau belum dapat dinilai harus dipilih sendiri.');
            }
            if ($final && ($this->input('extraoral_symmetry') === 'asymmetric' || $this->input('extraoral_swelling') === 'present' || array_diff($flags, ['none']) !== []) && ! $this->filled('extraoral_notes')) {
                $validator->errors()->add('extraoral_notes', 'Jelaskan lokasi, sisi, temuan / kendala, serta tindak lanjut atau komunikasi kepada dokter.');
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
            'assistance_reason.required' => 'Alasan bantuan admin wajib diisi.',
            'assistance_reason.min' => 'Alasan bantuan admin minimal 10 karakter.',
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
            'risiko_medis_gigi' => 'Riwayat penyakit yang pernah atau sedang dialami',
        ];
    }
}
