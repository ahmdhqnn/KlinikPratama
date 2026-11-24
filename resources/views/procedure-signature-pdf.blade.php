<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 16mm; }
    body { font: 10pt/1.3 "DejaVu Sans", sans-serif; color: #171717; }
    .letterhead { text-align: center; border-bottom: 1.5pt solid #171717; padding-bottom: 6pt; }
    h1 { font-size: 14pt; text-align: center; margin: 10pt 0; }
    p { margin: 2pt 0 4pt; overflow-wrap: break-word; }
    .narrative { white-space: pre-wrap; }
    h2 { font-size: 10pt; margin: 5pt 0 0; page-break-after: avoid; }
    .signature { page-break-inside: avoid; border-top: 1pt solid #bbb; margin-top: 8pt; padding-top: 8pt; }
</style>
</head>
<body>
<div class="letterhead"><strong>KLINIK PRATAMA<br>KEMENTERIAN PARIWISATA REPUBLIK INDONESIA</strong></div>
<h1>Bukti keputusan tindakan pasien</h1>
<p>Pasien {{ $snapshot['patient'] }} · No. RM {{ $snapshot['medical_record_number'] }}<br>
Tindakan {{ $snapshot['plan']['name'] }}{{ $snapshot['plan']['tooth'] ? ' · Gigi '.$snapshot['plan']['tooth'] : '' }} · Jumlah {{ $snapshot['plan']['quantity'] }}{{ !empty($snapshot['plan']['code']) ? ' · Kode '.$snapshot['plan']['code'] : '' }}<br>
Keputusan <strong>{{ ['accepted' => 'Setuju', 'refused' => 'Menolak', 'withdrawn' => 'Mencabut persetujuan'][$snapshot['decision']] }}</strong> · {{ $snapshot['text_version'] }}
@if ($snapshot['consent_template'])<br>SOP {{ $snapshot['consent_template']['sop_reference'] }} · {{ $snapshot['consent_template']['version'] }}@endif</p>
@foreach (['indication' => 'Diagnosis dan indikasi', 'method' => 'Cara dan tahapan', 'benefits' => 'Tujuan dan manfaat', 'risks' => 'Risiko dan komplikasi', 'alternatives' => 'Alternatif dan risikonya', 'without_procedure' => 'Konsekuensi bila tidak dilakukan', 'prognosis' => 'Prognosis'] as $key => $label)
    <h2>{{ $label }}</h2><p class="narrative">{{ $snapshot['explanation'][$key] }}</p>
@endforeach
<h2>Catatan keputusan</h2><p class="narrative">{{ $snapshot['reason'] }}</p>
<div class="signature">
    <strong>{{ $snapshot['signer_name'] }} · {{ ['patient' => 'Pasien', 'parent' => 'Orang tua', 'spouse' => 'Suami / istri', 'guardian' => 'Wali', 'other' => 'Wakil sah'][$snapshot['signer_relationship']] }}</strong>
    @if (!empty($snapshot['representative_verification']))<p>{{ $snapshot['representative_verification'] }}</p>@endif
    <div><img alt="Tanda tangan pasien atau wakil" style="width: 165pt; height: 55pt" src="{{ $signatureImage }}"></div>
    <p>Ditandatangani {{ $signedAt->format('d-m-Y H:i:s T') }}<br>Dokter {{ $snapshot['doctor'] }}<br>Saksi {{ $witness }} · {{ $issuedAt->format('d-m-Y H:i:s T') }}</p>
</div>
</body>
</html>
