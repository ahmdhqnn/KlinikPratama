<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Log aktivitas</title>
@vite(['resources/css/app.css'])
<style>body{font-size:12px;font-family:'Plus Jakarta Sans Variable',sans-serif;color:#171717;margin:24px}h1{font-size:20px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #d4d4d4;padding:7px;text-align:left;vertical-align:top;overflow-wrap:anywhere}thead{display:table-header-group}tr{break-inside:avoid}button,a{padding:8px;margin-right:8px;color:inherit}p{line-height:1.5}@media print{.actions{display:none}body{margin:0}@page{size:A4 landscape;margin:12mm}}</style></head>
<body><div class="actions"><button type="button" onclick="document.fonts.ready.then(() => window.print())">Cetak / Simpan PDF</button><a href="{{ route('admin.log-aktivitas') }}">Kembali ke log aktivitas</a></div>
<h1>Log aktivitas Klinik Pratama</h1><p>Disiapkan {{ $printedAt }} WIB · {{ $events->count() }} kegiatan. Permintaan cetak dicatat; keberhasilan printer tidak dapat diverifikasi aplikasi.</p>
<table><thead><tr><th>Waktu</th><th>Kegiatan</th><th>Operator / peran</th><th>Hasil</th><th>IP</th><th>No. kunjungan</th></tr></thead><tbody>
@forelse($events as $event)<tr><td>{{ $event->occurred_at }}</td><td>{{ $labels[$event->action] ?? $event->action }}</td><td>{{ $event->actor_name ?? 'Akun tidak tersedia' }}<br>{{ $event->actor_role ?? 'Peran historis belum tercatat' }}</td><td>{{ $event->outcome }}</td><td>{{ $event->ip_address }}</td><td>{{ $event->visit_number }}</td></tr>@empty<tr><td colspan="6">Tidak ada kegiatan pada filter ini.</td></tr>@endforelse
</tbody></table></body></html>
