<!doctype html>
<html lang="id" class="min-h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pengkajian awal - {{ $visit->no_kunjungan }}</title>
    <script>
        (() => {
            const root = document.documentElement;
            const systemTheme = window.matchMedia('(prefers-color-scheme: dark)');
            let preference = null;

            try {
                preference = window.localStorage.getItem('clinic-theme');
            } catch {
                preference = null;
            }

            function applyTheme() {
                const isDark = preference === 'dark' || (preference !== 'light' && systemTheme.matches);
                root.classList.toggle('dark', isDark);
                const button = document.getElementById('themeToggleBtn');
                const actionLabel = isDark ? 'Aktifkan tema terang' : 'Aktifkan tema gelap';
                button?.setAttribute('aria-label', actionLabel);
                button?.setAttribute('title', actionLabel);
                button?.setAttribute('aria-pressed', String(isDark));
                document.getElementById('sunIcon')?.classList.toggle('hidden', !isDark);
                document.getElementById('moonIcon')?.classList.toggle('hidden', isDark);
            }

            applyTheme();
            document.addEventListener('DOMContentLoaded', () => {
                applyTheme();
                document.getElementById('themeToggleBtn')?.addEventListener('click', () => {
                    preference = root.classList.contains('dark') ? 'light' : 'dark';
                    try {
                        window.localStorage.setItem('clinic-theme', preference);
                    } catch {
                        // The selected theme still applies when browser storage is unavailable.
                    }
                    applyTheme();
                });
            }, { once: true });
            systemTheme.addEventListener('change', () => {
                if (preference !== 'light' && preference !== 'dark') {
                    applyTheme();
                }
            });
            window.addEventListener('storage', (event) => {
                if (event.key === 'clinic-theme' || event.key === null) {
                    preference = event.newValue;
                    applyTheme();
                }
            });
        })();
    </script>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            @page { size: A4 portrait; margin: 12mm; }
            body { background: #ffffff !important; color: #000000 !important; }
            .print\:hidden { display: none !important; }
            article {
                border: 0 !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
                min-height: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                background: transparent !important;
            }
            tr { break-inside: avoid; }
            section { break-inside: avoid; }
        }
    </style>
</head>
<body class="min-h-full font-sans antialiased bg-canvas text-neutral-950 print:bg-white print:text-black">
@php
    $dental = $visit->poliklinik?->jenis === 'gigi';
    $answer = static fn (mixed $value, string $fallback = 'Belum dicatat'): string => filled($value) ? (string) $value : $fallback;
    $labels = [
        'ya' => 'Ya', 'tidak' => 'Tidak', 'teraba' => 'Teraba', 'tidak_teraba' => 'Tidak teraba',
        'normal' => 'Normal', 'tidak_normal' => 'Tidak normal', 'sadar' => 'Sadar', 'menurun' => 'Menurun',
        'tidak_sadar' => 'Tidak sadar', 'terganggu' => 'Terganggu', 'rendah' => 'Rendah', 'sedang' => 'Sedang',
        'tinggi' => 'Tinggi', 'ada' => 'Ada riwayat', 'tidak_ada' => 'Tidak ada (telah dikaji)',
        'belum_diketahui' => 'Belum dapat diketahui', 'pasien' => 'Pasien sendiri', 'keluarga' => 'Keluarga',
        'pendamping' => 'Pendamping', 'tidak_dapat_diperoleh' => 'Belum dapat diperoleh',
        'hipertensi' => 'Hipertensi', 'diabetes' => 'Diabetes melitus', 'penyakit_jantung' => 'Penyakit jantung',
        'gangguan_pembekuan' => 'Gangguan pembekuan darah', 'antikoagulan' => 'Obat antikoagulan',
        'lainnya' => 'Lainnya', 'hepatitis' => 'Hepatitis', 'hiv' => 'HIV', 'infeksi_lain' => 'Infeksi lain',
        'dingin' => 'Dingin', 'manis' => 'Manis', 'panas' => 'Panas', 'spontan' => 'Spontan', 'mengunyah' => 'Mengunyah',
        'kurang' => 'Gizi kurang', 'lebih' => 'Gizi lebih',
        'merah' => 'Merah - perlu respons segera', 'kuning' => 'Kuning - perlu penilaian lanjut', 'hijau' => 'Hijau - prioritas rutin',
        'darurat' => 'Respons darurat', 'segera' => 'Penilaian segera',
    ];
    $choice = static fn (?string $value): string => $labels[$value] ?? $answer($value, 'Belum dikaji');
    $choices = static fn (?array $values): string => $values ? implode(', ', array_map($choice, $values)) : 'Belum dikaji';
    $logoUrl = $clinic?->logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($clinic->logo) : null;
    $printDate = $screening->submitted_at?->locale('id')->isoFormat('D MMMM Y') ?? now()->locale('id')->isoFormat('D MMMM Y');
@endphp

{{-- Application Top Header Bar (matches AppLayout header, hidden on print) --}}
<header class="sticky top-0 z-30 flex min-h-16 items-center justify-between gap-3 border-b border-neutral-200 bg-surface/95 px-4 py-3 backdrop-blur print:hidden sm:px-6 lg:px-8">
    <div class="flex min-w-0 flex-1 items-center gap-3">
        @if ($logoUrl)
            <img alt="Logo klinik" class="size-8 shrink-0 rounded-lg bg-white p-1 object-contain" src="{{ $logoUrl }}" />
        @else
            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-neutral-900 text-xs font-bold text-neutral-50">KP</span>
        @endif
        <div class="hidden h-5 w-px shrink-0 bg-neutral-200 sm:block"></div>
        <nav aria-label="Breadcrumb" class="flex min-w-0 items-center gap-2 text-sm">
            <span class="hidden text-neutral-500 sm:inline">Pelayanan</span>
            <span aria-hidden="true" class="hidden text-neutral-300 sm:inline">/</span>
            <a href="{{ route('pelayanan.screening.history') }}" class="min-w-0 truncate rounded text-neutral-600 transition hover:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-500">Pengkajian saya</a>
            <span aria-hidden="true" class="shrink-0 text-neutral-300">/</span>
            <span aria-current="page" class="shrink-0 font-medium text-neutral-900">Cetak</span>
        </nav>
    </div>
    <div class="flex shrink-0 items-center gap-2">
        <button id="themeToggleBtn" type="button" aria-label="Aktifkan tema gelap" aria-pressed="false" title="Aktifkan tema gelap" class="inline-flex size-10 shrink-0 items-center justify-center rounded-lg text-neutral-600 transition hover:bg-neutral-100 hover:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-500 focus-visible:ring-offset-2 focus-visible:ring-offset-surface">
            <svg id="sunIcon" class="size-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <svg id="moonIcon" class="size-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
            </svg>
        </button>
        @if (auth()->check())
            <div class="hidden min-w-0 items-center gap-2 border-l border-neutral-200 pl-3 sm:flex">
                <div class="flex size-8 shrink-0 items-center justify-center rounded-full bg-neutral-200 text-xs font-semibold text-neutral-800">
                    {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 2)) }}
                </div>
                <div class="min-w-0 max-w-48 text-left text-xs">
                    <p class="truncate font-medium text-neutral-900">{{ auth()->user()->name }}</p>
                    <p class="text-neutral-500 capitalize">{{ auth()->user()->role_label }}</p>
                </div>
            </div>
        @endif
    </div>
</header>

<main class="mx-auto max-w-4xl space-y-4 p-4 sm:p-8 print:max-w-none print:p-0">
    {{-- Document Action Bar (matches persuratan/cetak.tsx) --}}
    <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
        <a href="{{ route('pelayanan.screening.history') }}" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg border border-neutral-200 bg-surface px-4 py-2 text-sm font-semibold text-neutral-700 shadow-sm transition-colors hover:bg-neutral-50">
            <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Kembali ke pengkajian saya
        </a>
        <div class="flex items-center gap-2">
            <button type="button" onclick="window.print()" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-neutral-900 px-4 py-2 text-sm font-semibold text-neutral-50 shadow-sm transition-colors hover:bg-neutral-800">
                <svg class="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                Cetak dokumen
            </button>
        </div>
    </div>

    {{-- Official Print Article (100% matches persuratan/cetak.tsx paper design) --}}
    <article class="min-h-[10in] rounded-xl border border-neutral-200 bg-surface p-6 text-neutral-950 shadow-sm sm:p-12 print:min-h-0 print:rounded-none print:border-0 print:bg-white print:p-0 print:text-black print:shadow-none">
        {{-- Clinic Kop Surat Header --}}
        <header class="flex items-start gap-4 border-b-2 border-neutral-900 pb-5 print:border-neutral-900">
            @if ($logoUrl)
                <img alt="Logo klinik" class="max-h-20 max-w-24 shrink-0 rounded-lg bg-white p-1 object-contain print:max-h-16" src="{{ $logoUrl }}" />
            @endif
            <div class="min-w-0 flex-1 text-center">
                <h1 class="break-words text-xl font-bold uppercase tracking-wide text-neutral-950 print:text-black">
                    {{ $clinic?->nama_klinik ?? config('app.name') }}
                </h1>
                @if ($clinic?->alamat)
                    <p class="mt-1 text-sm text-neutral-600 print:text-neutral-700">
                        {{ $clinic->alamat }}
                    </p>
                @endif
                <p class="text-sm text-neutral-600 print:text-neutral-700">
                    {{ implode(' · ', array_filter([$clinic?->telepon, $clinic?->email])) }}
                </p>
            </div>
        </header>

        {{-- Document Title & Date Row --}}
        <div class="mt-7 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-bold uppercase tracking-tight text-neutral-950 print:text-black">
                    Pengkajian Awal Perawat & Tanda-Tanda Vital
                </h2>
                <p class="mt-1 text-sm text-neutral-600 print:text-neutral-700">
                    Nomor: <span class="font-mono font-medium text-neutral-900 print:text-black">{{ $visit->no_kunjungan }}</span>
                </p>
            </div>
            <p class="text-sm text-neutral-600 print:text-neutral-700">{{ $printDate }}</p>
        </div>

        @if ($isDraft)
            <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-center text-xs font-bold uppercase tracking-wide text-amber-900 print:border-neutral-900 print:bg-neutral-100 print:text-neutral-900">
                DRAF — BELUM DITERUSKAN KE DOKTER
            </div>
        @endif
        @if ($visit->status === 'batal')
            <div class="mt-4 rounded-lg border border-rose-300 bg-rose-50 px-4 py-2 text-center text-xs font-bold uppercase tracking-wide text-rose-900 print:border-neutral-900 print:bg-neutral-100 print:text-neutral-900">
                KUNJUNGAN DIBATALKAN
            </div>
        @endif

        {{-- Patient Identity Section (matches persuratan/cetak.tsx 2-column grid & w-24 labels) --}}
        <section class="mt-7 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2 border-b border-neutral-200 pb-6 print:border-neutral-300">
            <p><span class="inline-block w-24 text-neutral-600 print:text-neutral-600">Nama</span>: <strong class="text-neutral-900 print:text-black">{{ $visit->pasien?->nama ?? 'Belum diisi' }}</strong></p>
            <p><span class="inline-block w-24 text-neutral-600 print:text-neutral-600">No. RM</span>: <span class="font-mono text-neutral-900 print:text-black">{{ $visit->pasien?->no_rm ?? 'Belum diisi' }}</span></p>
            <p><span class="inline-block w-24 text-neutral-600 print:text-neutral-600">Jenis kelamin</span>: {{ $visit->pasien?->jenis_kelamin === 'L' ? 'Laki-laki' : ($visit->pasien?->jenis_kelamin === 'P' ? 'Perempuan' : 'Belum diisi') }}</p>
            <p><span class="inline-block w-24 text-neutral-600 print:text-neutral-600">Umur</span>: {{ $visit->pasien?->umur === null ? 'Belum diisi' : $visit->pasien->umur.' tahun' }}</p>
            <p><span class="inline-block w-24 text-neutral-600 print:text-neutral-600">Poliklinik</span>: {{ $visit->poliklinik?->nama ?? 'Belum diisi' }}</p>
            <p><span class="inline-block w-24 text-neutral-600 print:text-neutral-600">No. kunjungan</span>: <span class="font-mono text-neutral-900 print:text-black">{{ $visit->no_kunjungan }}</span></p>
            <p><span class="inline-block w-24 text-neutral-600 print:text-neutral-600">Petugas</span>: {{ $screening->petugas?->nama ?? 'Belum dicatat' }}</p>
            <p><span class="inline-block w-24 text-neutral-600 print:text-neutral-600">Dokter tujuan</span>: {{ $visit->dokter?->nama ?? 'Belum ditentukan' }}</p>
            @if ($visit->pasien?->alamat)
                <p class="sm:col-span-2">
                    <span class="inline-block w-24 text-neutral-600 print:text-neutral-600">Alamat</span>: {{ $visit->pasien->alamat }}
                </p>
            @endif
        </section>

        {{-- Section A: Anamnesis & Riwayat Kesehatan --}}
        <section class="mt-6">
            <h3 class="border-b border-neutral-900 pb-1.5 text-xs font-bold uppercase tracking-wider text-neutral-900 print:border-neutral-900 print:text-neutral-900">
                A. ANAMNESIS & RIWAYAT KESEHATAN
            </h3>
            <div class="mt-2.5 overflow-hidden rounded-lg border border-neutral-200 print:border-neutral-400">
                <table class="w-full text-xs">
                    <tbody class="divide-y divide-neutral-200 print:divide-neutral-300">
                        @foreach ([
                            'Sumber informasi' => $choice($screening->sumber_informasi),
                            'Keluhan utama' => $screening->keluhan,
                            'Lama keluhan' => $screening->lama_keluhan,
                            'Keluhan tambahan' => $screening->keluhan_tambahan,
                            'Status penyakit terdahulu' => $choice($screening->status_riwayat_penyakit),
                            'Riwayat penyakit' => implode(' — ', array_filter([$screening->riwayat_penyakit, $screening->penyakit_nama, $screening->penyakit_keterangan])),
                            'Obat yang digunakan' => $screening->riwayat_obat,
                            'Riwayat penyakit keluarga' => $screening->riwayat_penyakit_keluarga,
                            'Status alergi' => $choice($screening->status_alergi),
                            'Riwayat alergi' => $screening->riwayat_alergi,
                            'Jenis / reaksi alergi' => implode(' — ', array_filter([$screening->alergi_jenis, $screening->alergi_reaksi])),
                        ] as $label => $value)
                            <tr>
                                <th class="w-1/3 bg-neutral-50 px-3 py-2 text-left font-medium text-neutral-600 print:bg-neutral-100 print:text-neutral-700">{{ $label }}</th>
                                <td class="px-3 py-2 text-neutral-900 print:text-black">{{ $answer($value) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Section B: Tanda-Tanda Vital --}}
        <section class="mt-6">
            <h3 class="border-b border-neutral-900 pb-1.5 text-xs font-bold uppercase tracking-wider text-neutral-900 print:border-neutral-900 print:text-neutral-900">
                B. TANDA-TANDA VITAL
            </h3>
            <div class="mt-2.5 overflow-hidden rounded-lg border border-neutral-200 print:border-neutral-400">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-neutral-200 bg-neutral-50 text-neutral-700 print:border-neutral-400 print:bg-neutral-100 print:text-neutral-800">
                            <th class="px-3 py-2 text-center font-medium">Tekanan Darah (mmHg)</th>
                            <th class="px-3 py-2 text-center font-medium">Nadi (x/mnt)</th>
                            <th class="px-3 py-2 text-center font-medium">Suhu (°C)</th>
                            <th class="px-3 py-2 text-center font-medium">Respirasi (x/mnt)</th>
                            <th class="px-3 py-2 text-center font-medium">SpO2 (%)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="divide-x divide-neutral-200 text-center font-medium text-neutral-900 print:divide-neutral-400 print:text-black">
                            <td class="px-3 py-2.5">
                                @if ($screening->td_sistole !== null && $screening->td_diastole !== null)
                                    {{ $screening->td_sistole }} / {{ $screening->td_diastole }}
                                @else
                                    <span class="text-neutral-400 print:text-neutral-600">Belum diukur</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5">{{ $answer($screening->nadi, 'Belum diukur') }}</td>
                            <td class="px-3 py-2.5">{{ $answer($screening->suhu, 'Belum diukur') }}</td>
                            <td class="px-3 py-2.5">{{ $answer($screening->respirasi, 'Belum diukur') }}</td>
                            <td class="px-3 py-2.5">{{ $answer($screening->spo2, 'Belum diukur') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Section C: Antropometri --}}
        <section class="mt-6">
            <h3 class="border-b border-neutral-900 pb-1.5 text-xs font-bold uppercase tracking-wider text-neutral-900 print:border-neutral-900 print:text-neutral-900">
                C. ANTROPOMETRI (OPSIONAL)
            </h3>
            <div class="mt-2.5 overflow-hidden rounded-lg border border-neutral-200 print:border-neutral-400">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-neutral-200 bg-neutral-50 text-neutral-700 print:border-neutral-400 print:bg-neutral-100 print:text-neutral-800">
                            <th class="px-3 py-2 text-center font-medium">Berat Badan (kg)</th>
                            <th class="px-3 py-2 text-center font-medium">Tinggi Badan (cm)</th>
                            <th class="px-3 py-2 text-center font-medium">IMT (kg/m²)</th>
                            <th class="px-3 py-2 text-center font-medium">Lingkar Perut (cm)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="divide-x divide-neutral-200 text-center font-medium text-neutral-900 print:divide-neutral-400 print:text-black">
                            <td class="px-3 py-2.5">{{ $answer($screening->berat_badan, 'Belum diukur') }}</td>
                            <td class="px-3 py-2.5">{{ $answer($screening->tinggi_badan, 'Belum diukur') }}</td>
                            <td class="px-3 py-2.5">{{ $answer($screening->imt, 'Belum dihitung') }}</td>
                            <td class="px-3 py-2.5">{{ $answer($screening->lingkar_perut, 'Belum diukur') }}</td>
                        </tr>
                    </tbody>
                </table>
                @if ($screening->alasan_pengukuran_tidak_lengkap)
                    <div class="border-t border-neutral-200 bg-neutral-50/50 px-3 py-2 text-xs text-neutral-700 print:border-neutral-300 print:text-neutral-800">
                        <strong>Catatan pengkajian antropometri:</strong> {{ $screening->alasan_pengukuran_tidak_lengkap }}
                    </div>
                @endif
            </div>
        </section>

        {{-- Section D: Penapisan Awal & Prioritas Respons --}}
        <section class="mt-6">
            <h3 class="border-b border-neutral-900 pb-1.5 text-xs font-bold uppercase tracking-wider text-neutral-900 print:border-neutral-900 print:text-neutral-900">
                D. PENAPISAN AWAL & PRIORITAS RESPONS
            </h3>
            <div class="mt-2.5 overflow-hidden rounded-lg border border-neutral-200 print:border-neutral-400">
                <table class="w-full text-xs">
                    <tbody class="divide-y divide-neutral-200 print:divide-neutral-300">
                        @foreach ([
                            ['Nyeri dada', $choice($screening->nyeri_dada), 'Kejang', $choice($screening->kejang)],
                            ['Nadi teraba', $choice($screening->nadi_teraba), 'Pola pernapasan', $choice($screening->pola_pernapasan)],
                            ['Kesadaran', $choice($screening->kesadaran), 'Kondisi psikiatri', $choice($screening->kondisi_psikiatri)],
                            ['Risiko jatuh visual', $choice($screening->risiko_jatuh_visual), 'Skala nyeri', $screening->skala_nyeri !== null ? $screening->skala_nyeri.' / 10' : 'Belum dikaji'],
                        ] as [$label1, $val1, $label2, $val2])
                            <tr>
                                <th class="w-1/4 bg-neutral-50 px-3 py-2 text-left font-medium text-neutral-600 print:bg-neutral-100 print:text-neutral-700">{{ $label1 }}</th>
                                <td class="w-1/4 px-3 py-2 text-neutral-900 print:text-black">{{ $val1 }}</td>
                                <th class="w-1/4 bg-neutral-50 px-3 py-2 text-left font-medium text-neutral-600 print:bg-neutral-100 print:text-neutral-700">{{ $label2 }}</th>
                                <td class="w-1/4 px-3 py-2 text-neutral-900 print:text-black">{{ $val2 }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <th class="w-1/4 bg-neutral-50 px-3 py-2 text-left font-medium text-neutral-600 print:bg-neutral-100 print:text-neutral-700">Kebutuhan respons awal</th>
                            <td colspan="3" class="px-3 py-2">
                                <span class="font-bold uppercase text-neutral-950 print:text-black">
                                    {{ $choice($screening->kesimpulan_triase ?? 'Belum ditentukan') }} · {{ $choice($screening->prioritas_layanan) }}
                                </span>
                            </td>
                        </tr>
                        @if ($screening->catatan_triase)
                            <tr>
                                <th class="w-1/4 bg-neutral-50 px-3 py-2 text-left font-medium text-neutral-600 print:bg-neutral-100 print:text-neutral-700">Catatan penapisan</th>
                                <td colspan="3" class="px-3 py-2 text-neutral-900 print:text-black">{{ $screening->catatan_triase }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Section E: Asesmen Khusus Gigi dan Mulut (khusus poli gigi) --}}
        @if ($dental)
            <section class="mt-6">
                <h3 class="border-b border-neutral-900 pb-1.5 text-xs font-bold uppercase tracking-wider text-neutral-900 print:border-neutral-900 print:text-neutral-900">
                    E. ASESMEN KHUSUS GIGI DAN MULUT
                </h3>
                <div class="mt-2.5 overflow-hidden rounded-lg border border-neutral-200 print:border-neutral-400">
                    <table class="w-full text-xs">
                        <tbody class="divide-y divide-neutral-200 print:divide-neutral-300">
                            @foreach ([
                                'Risiko medis sistemik' => $choices($screening->risiko_medis_gigi),
                                'Lokasi keluhan/nyeri' => $screening->lokasi_nyeri_gigi,
                                'Pemicu keluhan' => $choices($screening->pemicu_nyeri_gigi),
                                'Pola / durasi keluhan' => $screening->durasi_keluhan_gigi,
                                'Riwayat infeksi / bengkak' => $choices($screening->riwayat_infeksi_gigi),
                                'Catatan untuk dokter gigi' => $screening->catatan_medis_gigi,
                            ] as $label => $value)
                                <tr>
                                    <th class="w-1/3 bg-neutral-50 px-3 py-2 text-left font-medium text-neutral-600 print:bg-neutral-100 print:text-neutral-700">{{ $label }}</th>
                                    <td class="px-3 py-2 text-neutral-900 print:text-black">{{ $answer($value) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-xs italic text-neutral-500 print:text-neutral-600">
                    Asesmen awal ini tidak menyatakan kelayakan anestesi atau tindakan. Keputusan klinis sepenuhnya oleh dokter gigi.
                </p>
            </section>
        @endif

        {{-- Section F (or E): Pengkajian Tambahan & Serah Terima --}}
        <section class="mt-6">
            <h3 class="border-b border-neutral-900 pb-1.5 text-xs font-bold uppercase tracking-wider text-neutral-900 print:border-neutral-900 print:text-neutral-900">
                {{ $dental ? 'F' : 'E' }}. PENGKAJIAN TAMBAHAN & SERAH TERIMA
            </h3>
            <div class="mt-2.5 overflow-hidden rounded-lg border border-neutral-200 print:border-neutral-400">
                <table class="w-full text-xs">
                    <tbody class="divide-y divide-neutral-200 print:divide-neutral-300">
                        <tr>
                            <th class="w-1/3 bg-neutral-50 px-3 py-2 text-left font-medium text-neutral-600 print:bg-neutral-100 print:text-neutral-700">Pengkajian gizi</th>
                            <td class="px-3 py-2 text-neutral-900 print:text-black">{{ $choice($screening->skrining_gizi) }}</td>
                        </tr>
                        <tr>
                            <th class="w-1/3 bg-neutral-50 px-3 py-2 text-left font-medium text-neutral-600 print:bg-neutral-100 print:text-neutral-700">Observasi awal</th>
                            <td class="px-3 py-2 text-neutral-900 print:text-black">{{ $answer($screening->pemeriksaan_fisik) }}</td>
                        </tr>
                        <tr>
                            <th class="w-1/3 bg-neutral-50 px-3 py-2 text-left font-medium text-neutral-600 print:bg-neutral-100 print:text-neutral-700">Waktu mulai / kirim</th>
                            <td class="px-3 py-2 text-neutral-900 print:text-black">
                                {{ $screening->started_at?->format('d/m/Y H:i') ?? 'Belum dicatat' }} / {{ $screening->submitted_at?->format('d/m/Y H:i') ?? ($isDraft ? 'Belum dikirim' : 'Belum dicatat') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Signatures Block (matches persuratan/cetak.tsx footer style) --}}
        <div class="mt-14 grid grid-cols-2 gap-8 text-center text-sm print:mt-10">
            <div>
                <p class="text-neutral-700 print:text-neutral-800">{{ $visit->poliklinik?->nama ?? 'Poliklinik' }}, {{ $printDate }}</p>
                <p class="mt-1 text-neutral-600 print:text-neutral-700">Petugas pengkajian awal,</p>
                <div class="h-20 flex items-center justify-center">
                    {{-- Space for manual signature --}}
                </div>
                <p class="font-semibold underline underline-offset-2 text-neutral-900 print:text-black">
                    {{ $screening->petugas?->nama ?? '________________________' }}
                </p>
                <p class="mt-1 text-xs text-neutral-500 print:text-neutral-600">
                    {{ $screening->submitted_at ? 'Dikirim: '.$screening->submitted_at->format('d/m/Y H:i') : 'Petugas pengkaji' }}
                </p>
            </div>
            <div>
                <p class="text-neutral-700 print:text-neutral-800">Serah terima dokter pemeriksa,</p>
                <p class="mt-1 text-neutral-600 print:text-neutral-700">Dokter penerima,</p>
                <div class="h-20 flex items-center justify-center">
                    {{-- Space for manual signature --}}
                </div>
                <p class="font-semibold underline underline-offset-2 text-neutral-900 print:text-black">
                    {{ $visit->dokter?->nama ?? '________________________' }}
                </p>
                <p class="mt-1 text-xs text-neutral-500 print:text-neutral-600">
                    Paraf serah terima tindakan
                </p>
            </div>
        </div>

        {{-- Document Footer --}}
        <footer class="mt-10 border-t border-neutral-200 pt-4 text-center text-xs text-neutral-500 print:border-neutral-300 print:text-neutral-600">
            {{ $visit->no_kunjungan }} · Dicetak {{ now()->format('d/m/Y H:i') }} · Dokumen rekam medis, bersifat rahasia.<br>
            “Belum dicatat/dikaji/diukur” berarti data belum tersedia, bukan hasil normal atau tidak ada keluhan.
        </footer>
    </article>
</main>

</body>
</html>
