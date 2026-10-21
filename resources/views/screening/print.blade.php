<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Asesmen awal — {{ $visit->no_kunjungan }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #f3f4f6; color: #111; font: 12px/1.4 Arial, sans-serif; }
        .toolbar { max-width: 210mm; margin: 16px auto; display: flex; justify-content: space-between; gap: 12px; align-items: center; }
        button, .toolbar a { background: #fff; border: 1px solid #555; border-radius: 6px; padding: 10px 16px; color: #111; font: inherit; cursor: pointer; text-decoration: none; }
        .sheet { width: 210mm; max-width: 100%; min-height: 297mm; background: #fff; padding: 12mm; margin: 0 auto 20px; }
        header { text-align: center; border-bottom: 3px double #111; padding-bottom: 8px; }
        h1 { font-size: 17px; margin: 0 0 3px; text-transform: uppercase; }
        h2 { font-size: 14px; text-align: center; margin: 12px 0 3px; text-transform: uppercase; }
        p { margin: 3px 0; }
        .muted { color: #555; font-size: 11px; }
        .banner { border: 2px solid #111; padding: 7px; text-align: center; font-weight: bold; margin-top: 10px; }
        .section { margin-top: 10px; }
        .section-title { font-weight: bold; background: #eee; border: 1px solid #333; padding: 5px 7px; break-after: avoid; }
        table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        th, td { border: 1px solid #555; padding: 5px 7px; vertical-align: middle; overflow-wrap: anywhere; text-align: center; }
        th { font-weight: bold; background: #f6f6f6; }
        tr { break-inside: avoid; }
        .identity td { text-align: left; }
        .identity td:nth-child(odd) { width: 18%; font-weight: bold; }
        .details th { width: 28%; }
        .details td { text-align: left; white-space: pre-line; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 18px; text-align: center; break-inside: avoid; }
        .signature-space { min-height: 45px; }
        footer { border-top: 1px solid #555; padding-top: 6px; margin-top: 16px; font-size: 10px; }
        @page { size: A4 portrait; margin: 10mm; }
        @media print { body { background: #fff; font-size: 10px; } .toolbar { display: none; } .sheet { width: auto; min-height: 0; margin: 0; padding: 0; } h1 { font-size: 15px; } h2 { font-size: 12px; } th, td { padding: 4px 6px; } }
    </style>
</head>
<body>
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
    ];
    $choice = static fn (?string $value): string => $labels[$value] ?? $answer($value, 'Belum dikaji');
    $choices = static fn (?array $values): string => $values ? implode(', ', array_map($choice, $values)) : 'Belum dikaji';
@endphp
<nav class="toolbar"><a href="{{ route('pelayanan.screening.history') }}">Kembali ke riwayat</a><span>Data tersimpan · kertas A4</span><button type="button" onclick="window.print()">Cetak / Simpan PDF</button></nav>
<main class="sheet">
    <header><h1>{{ $clinic?->nama_klinik ?? config('app.name') }}</h1><p>{{ $clinic?->alamat }}</p><p class="muted">{{ $clinic?->telepon }}{{ $clinic?->email ? ' · '.$clinic->email : '' }}</p></header>
    <h2>Asesmen Awal Keperawatan & Tanda-Tanda Vital</h2><p style="text-align:center">{{ $dental ? 'POLIKLINIK GIGI DAN MULUT' : 'POLIKLINIK UMUM' }}</p>
    @if ($isDraft)<p class="banner">DRAF — BELUM DITERUSKAN KE DOKTER</p>@endif
    @if ($visit->status === 'batal')<p class="banner">KUNJUNGAN DIBATALKAN</p>@endif
    <div class="section"><table class="identity"><tbody>
        <tr><td>Nama pasien</td><td>{{ $visit->pasien?->nama }}</td><td>No. RM</td><td>{{ $visit->pasien?->no_rm }}</td></tr>
        <tr><td>Tanggal lahir</td><td>{{ $visit->pasien?->tanggal_lahir?->format('d/m/Y') ?? 'Belum dicatat' }}</td><td>Jenis kelamin</td><td>{{ $visit->pasien?->jenis_kelamin === 'L' ? 'Laki-laki' : ($visit->pasien?->jenis_kelamin === 'P' ? 'Perempuan' : 'Belum dicatat') }}</td></tr>
        <tr><td>No. kunjungan</td><td>{{ $visit->no_kunjungan }}</td><td>Tanggal</td><td>{{ $visit->tanggal?->format('d/m/Y') }}</td></tr>
        <tr><td>Petugas</td><td>{{ $screening->petugas?->nama ?? 'Belum dicatat' }}</td><td>Dokter tujuan</td><td>{{ $visit->dokter?->nama ?? 'Belum ditentukan' }}</td></tr>
    </tbody></table></div>
    <section class="section"><div class="section-title">A. ANAMNESIS & RIWAYAT KESEHATAN</div><table class="details"><tbody>
        @foreach ([
            'Sumber informasi' => $choice($screening->sumber_informasi), 'Keluhan utama' => $screening->keluhan,
            'Keluhan tambahan' => $screening->keluhan_tambahan, 'Lama keluhan' => $screening->lama_keluhan,
            'Status riwayat penyakit' => $choice($screening->status_riwayat_penyakit), 'Riwayat penyakit sistemik' => $screening->riwayat_penyakit,
            'Nama / keterangan penyakit' => implode(' — ', array_filter([$screening->penyakit_nama, $screening->penyakit_keterangan])),
            'Obat yang digunakan' => $screening->riwayat_obat, 'Riwayat keluarga' => $screening->riwayat_penyakit_keluarga,
            'Status alergi' => $choice($screening->status_alergi), 'Riwayat alergi' => $screening->riwayat_alergi,
            'Jenis / reaksi alergi' => implode(' — ', array_filter([$screening->alergi_jenis, $screening->alergi_reaksi])),
        ] as $label => $value)<tr><th>{{ $label }}</th><td>{{ $answer($value) }}</td></tr>@endforeach
    </tbody></table></section>
    <section class="section"><div class="section-title">B. TANDA-TANDA VITAL</div><table><thead><tr><th>Tekanan darah<br>mmHg</th><th>Nadi<br>kali/menit</th><th>Suhu<br>°C</th><th>Respirasi<br>kali/menit</th><th>SpO2<br>%</th></tr></thead><tbody><tr><td>@if ($screening->td_sistole !== null && $screening->td_diastole !== null){{ $screening->td_sistole }} / {{ $screening->td_diastole }}@else Belum diukur @endif</td>@foreach (["nadi", "suhu", "respirasi", "spo2"] as $field)<td>{{ $answer($screening->{$field}, "Belum diukur") }}</td>@endforeach</tr></tbody></table></section>
    <section class="section"><div class="section-title">C. ANTROPOMETRI (OPSIONAL)</div><table><thead><tr><th>Berat badan (kg)</th><th>Tinggi badan (cm)</th><th>IMT (kg/m²)</th><th>Lingkar perut (cm)</th></tr></thead><tbody><tr>@foreach (["berat_badan", "tinggi_badan", "imt", "lingkar_perut"] as $field)<td>{{ $answer($screening->{$field}, $field === "imt" ? "Belum dihitung" : "Belum diukur") }}</td>@endforeach</tr></tbody></table><table class="details"><tr><th>Catatan Pengkajian</th><td>{{ $answer($screening->alasan_pengukuran_tidak_lengkap) }}</td></tr></table></section>
    <section class="section"><div class="section-title">D. PENAPISAN & PRIORITAS LAYANAN</div><table><tbody>
        @foreach ([['Nyeri dada', 'nyeri_dada', 'Kejang', 'kejang'], ['Nadi', 'nadi_teraba', 'Pola pernapasan', 'pola_pernapasan'], ['Kesadaran', 'kesadaran', 'Kondisi psikiatri', 'kondisi_psikiatri']] as [$labelA, $fieldA, $labelB, $fieldB])<tr><th>{{ $labelA }}</th><td>{{ $choice($screening->{$fieldA}) }}</td><th>{{ $labelB }}</th><td>{{ $choice($screening->{$fieldB}) }}</td></tr>@endforeach
        <tr><th>Risiko jatuh</th><td>{{ $choice($screening->risiko_jatuh_visual) }}</td><th>Skala nyeri</th><td>{{ $screening->skala_nyeri === null ? 'Belum dikaji' : $screening->skala_nyeri.' / 10' }}</td></tr>
        <tr><th>Triase / prioritas</th><td colspan="3"><strong>{{ strtoupper($screening->kesimpulan_triase ?? 'Belum ditentukan') }} · {{ $answer($screening->prioritas_layanan) }}</strong></td></tr>
    </tbody></table><table class="details"><tr><th>Catatan triase</th><td>{{ $answer($screening->catatan_triase) }}</td></tr></table></section>
    @if ($dental)<section class="section"><div class="section-title">E. ASESMEN KHUSUS GIGI DAN MULUT</div><table class="details"><tbody>
        @foreach (['Risiko medis sistemik' => $choices($screening->risiko_medis_gigi), 'Lokasi nyeri' => $screening->lokasi_nyeri_gigi, 'Pemicu nyeri' => $choices($screening->pemicu_nyeri_gigi), 'Pola / durasi nyeri' => $screening->durasi_keluhan_gigi, 'Riwayat infeksi' => $choices($screening->riwayat_infeksi_gigi), 'Catatan untuk dokter gigi' => $screening->catatan_medis_gigi] as $label => $value)<tr><th>{{ $label }}</th><td>{{ $answer($value) }}</td></tr>@endforeach
    </tbody></table><p class="muted">Asesmen awal ini tidak menyatakan kelayakan anestesi atau tindakan. Keputusan klinis oleh dokter gigi.</p></section>@endif
    <section class="section"><div class="section-title">{{ $dental ? 'F' : 'E' }}. ASESMEN TAMBAHAN & SERAH TERIMA</div><table class="details"><tr><th>Pengkajian gizi</th><td>{{ $choice($screening->skrining_gizi) }}</td></tr><tr><th>Observasi awal</th><td>{{ $answer($screening->pemeriksaan_fisik) }}</td></tr><tr><th>Waktu mulai / kirim</th><td>{{ $screening->started_at?->format('d/m/Y H:i') ?? 'Belum dicatat' }} / {{ $screening->submitted_at?->format('d/m/Y H:i') ?? ($isDraft ? 'Belum dikirim' : 'Waktu kirim tidak tercatat pada data lama') }}</td></tr></table></section>
    <div class="signatures"><div><p>Petugas pelaksana asesmen</p><div class="signature-space"></div><strong>{{ $screening->petugas?->nama ?? '________________________' }}</strong><p class="muted">{{ $screening->submitted_at ? 'Dikirim pada '.$screening->submitted_at->format('d/m/Y H:i') : 'Nama petugas sesuai catatan tersimpan' }}</p></div><div><p>Dokter penerima / tindak lanjut</p><div class="signature-space"></div><strong>{{ $visit->dokter?->nama ?? '________________________' }}</strong><p class="muted">Kolom paraf serah terima manual</p></div></div>
    <footer>{{ $visit->no_kunjungan }} · Dicetak {{ now()->format('d/m/Y H:i') }} · Dokumen rekam medis, bersifat rahasia.<br>“Belum dicatat/dikaji/diukur” berarti data belum tersedia, bukan hasil normal atau tidak ada keluhan.</footer>
</main>
</body>
</html>
