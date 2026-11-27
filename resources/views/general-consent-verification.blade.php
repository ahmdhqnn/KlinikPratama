<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi Persetujuan Umum</title>
    @include('partials.theme-init')
    @vite('resources/css/app.css')
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 24px 16px; background: var(--clinic-canvas); font-size: 15px; line-height: 1.55; color: var(--clinic-neutral-900); }
        main { max-width: 210mm; margin: auto; }
        nav { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px; margin-bottom: 16px; }
        .action { padding: 8px 14px; border: 1px solid var(--clinic-neutral-200); border-radius: 8px; background: var(--clinic-surface); color: inherit; font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; }
        .primary { background: var(--clinic-neutral-900); color: var(--clinic-neutral-50); border-color: transparent; }
        .sheet { padding: clamp(16px, 4vw, 36px); border: 1px solid var(--clinic-neutral-200); border-radius: 16px; background: var(--clinic-surface); }
        h1 { margin: 24px 0 10px; text-align: center; font-size: 1.125rem; font-weight: 700; line-height: 1.25; text-transform: uppercase; }
        p { margin: 0; }
        .muted { color: var(--clinic-neutral-600); font-size: .875rem; }
        .status { display: inline-block; padding: 3px 10px; border: 1px solid var(--clinic-neutral-200); border-radius: 99px; font-size: .8125rem; }
        .status + p { margin-top: 8px; }
        dl { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 20px; margin: 20px 0; }
        .full { grid-column: 1 / -1; }
        dt { color: var(--clinic-neutral-600); font-size: .8125rem; font-weight: 600; }
        dd { margin: 2px 0 0; overflow-wrap: anywhere; }
        .qr { display: flex; align-items: center; gap: 16px; border-top: 1px solid var(--clinic-neutral-200); padding-top: 12px; break-inside: avoid; }
        .code { position: relative; flex: 0 0 120px; height: 120px; background: white; }
        .logo { position: absolute; top: 43.75%; left: 43.75%; width: 12.5%; height: 12.5%; object-fit: contain; background: white; border-radius: 2px; }
        @media screen and (max-width: 420px) { dl { grid-template-columns: 1fr; } }
        @page { size: A4; margin: 16mm; }
        @media print {
            body { padding: 0; background: white; font-size: 11pt; }
            nav { display: none; }
            .sheet { padding: 0; border: 0; background: white; }
            header, dl > div { break-inside: avoid; }
        }
    </style>
</head>
<body>
<main>
    <nav aria-label="Navigasi">
        <a class="action" href="{{ route('general-consent.show', $visit) }}">Kembali ke Persetujuan Umum</a>
        @if ($valid)
            <button class="action primary" type="button" onclick="window.print()">Cetak bukti</button>
        @endif
    </nav>
    <article class="clinical-sheet print-preview-paper sheet">
    <header class="clinical-document-head">
        <img class="clinical-logo" src="{{ asset('logo_kemenpar.webp') }}" alt="Kementerian Pariwisata RI">
        <div class="clinical-head-copy">
            <strong>{{ $clinic['name'] ?: 'Klinik Pratama Kementerian Pariwisata' }}</strong>
            @if (! empty($clinic['license']))
                <p class="clinical-license">Izin Operasional Klinik Nomor: {{ $clinic['license'] }}</p>
            @endif
            <p>{{ $clinic['address'] }}</p>
            @if (! empty($clinic['phone']))
                <p>Telp. {{ $clinic['phone'] }}</p>
            @endif
        </div>
        <img class="clinical-logo" src="{{ asset('logo_wonderful.png') }}" alt="Wonderful Indonesia">
    </header>
    <h1>Bukti Verifikasi Persetujuan Umum</h1>
    @if ($valid)
            <strong class="status">{{ $current ? 'Catatan terverifikasi' : 'Catatan historis terverifikasi' }}</strong>
            @unless ($current)
                <p class="muted">Keputusan atau versi penjelasan telah diperbarui.</p>
            @endunless
            <dl>
                <div><dt>Pasien dan nomor rekam medis</dt><dd>{{ $visit->pasien->nama }} · {{ $visit->pasien->no_rm }}</dd></div>
                <div><dt>Keputusan</dt><dd>{{ match ($consent->decision) {
                    'accepted' => 'Setuju', 'refused' => 'Menolak setelah penjelasan', 'withdrawn' => 'Persetujuan dicabut',
                } }}</dd></div>
                <div><dt>Pemberi keputusan</dt><dd>{{ $consent->signer_name }}</dd></div>
                <div><dt>Petugas dan waktu pencatatan</dt><dd>{{ $recorder }} · {{ $consent->recorded_at->format('d/m/Y H:i:s T') }}</dd></div>
                @if ($consent->reason)
                    <div class="full"><dt>Alasan / catatan</dt><dd>{{ $consent->reason }}</dd></div>
                @endif
                <div class="full"><dt>Penjelasan ({{ $consent->text_version }})</dt><dd>{{ $consent->text_snapshot }}</dd></div>
            </dl>
            <div class="qr">
                <div class="code">
                    <img src="{{ $qrDataUrl }}" alt="QR verifikasi" width="120" height="120">
                    <img class="logo" src="{{ asset('logo_kemenpar.webp') }}" alt="">
                </div>
                <div><strong>Verifikasi catatan klinik</strong><p class="muted">Pindai QR untuk memeriksa status catatan. Masuk dengan akun klinik yang berwenang.</p></div>
            </div>
            <footer class="mt-6 border-t border-neutral-200 pt-3 text-center text-xs text-neutral-600">Kunjungan pencatatan {{ $visit->no_kunjungan }} · Dokumen rekam medis, bersifat rahasia.</footer>
    @else
        <p role="alert">Integritas catatan gagal diverifikasi. Periksa catatan melalui pengelola sistem klinik.</p>
    @endif
    </article>
</main>
</body>
</html>
