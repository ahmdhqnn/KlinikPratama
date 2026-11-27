<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="referrer" content="no-referrer">
    <title>Keputusan tindakan pasien</title>
    @include('partials.theme-init')
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-canvas px-4 py-8 text-neutral-950">
<main class="mx-auto max-w-xl space-y-5 rounded-2xl border border-neutral-200 bg-surface p-5 sm:p-8">
    <header class="flex items-center gap-3">
        <img src="{{ asset('logo_kemenpar.webp') }}" alt="" class="size-10 object-contain">
        <div>
            <p class="text-sm text-neutral-600">Klinik Pratama Kementerian Pariwisata</p>
            <h1 class="text-xl font-semibold">Keputusan tindakan pasien</h1>
        </div>
    </header>
    @if ($submitted)
        <p role="status">Tanda tangan diterima. Beri tahu perawat saksi agar bukti dapat diselesaikan.</p>
    @elseif (! $snapshot)
        <form class="grid gap-3" action="{{ route('procedure-signature.pair', $token) }}" method="post">
            @csrf
            <label for="pair-code">Masukkan kode pendamping 6 digit dari dokter</label>
            <input id="pair-code" name="pair_code" type="text" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" required class="rounded-lg border border-neutral-300 px-3 py-2">
            @error('pair_code')<p role="alert" class="text-sm text-red-700">{{ $message }}</p>@enderror
            <button class="rounded-lg bg-neutral-900 px-4 py-2 font-semibold text-neutral-50">Lihat penjelasan</button>
        </form>
    @else
        <div class="rounded-lg border border-neutral-200 p-4">
            <p class="font-semibold">{{ $snapshot['plan']['name'] }} {{ $snapshot['plan']['tooth'] ? '· Gigi '.$snapshot['plan']['tooth'] : '' }}</p>
            <p class="text-sm">Jumlah {{ $snapshot['plan']['quantity'] }}{{ $snapshot['plan']['code'] ? ' · Kode '.$snapshot['plan']['code'] : '' }}</p>
            <p class="text-sm">Pasien {{ $snapshot['patient'] }} · Penanda tangan {{ $snapshot['signer_name'] }}</p>
        </div>
        <dl class="space-y-3">
            @foreach (['indication' => 'Diagnosis dan indikasi', 'method' => 'Cara dan tahapan', 'benefits' => 'Tujuan dan manfaat', 'risks' => 'Risiko dan komplikasi', 'alternatives' => 'Alternatif dan risikonya', 'without_procedure' => 'Konsekuensi bila tidak dilakukan', 'prognosis' => 'Prognosis'] as $key => $label)
                <div><dt class="text-sm font-semibold">{{ $label }}</dt><dd class="break-words whitespace-pre-wrap text-sm text-neutral-700">{{ $snapshot['explanation'][$key] }}</dd></div>
            @endforeach
        </dl>
        <p class="text-sm font-medium">Keputusan yang akan dicatat adalah <strong>{{ ['accepted' => 'setuju', 'refused' => 'menolak', 'withdrawn' => 'mencabut persetujuan'][$snapshot['decision']] }}</strong>. Jika berbeda dengan pilihan Anda, beri tahu dokter.</p>
        <p class="break-words whitespace-pre-wrap text-sm text-neutral-700">{{ $snapshot['reason'] }}</p>
        <form id="signature-form" class="space-y-3 border-t border-neutral-200 pt-4" action="{{ route('procedure-signature.sign', $token) }}" method="post">
            @csrf
            <label class="flex items-start gap-2"><input class="mt-1" type="checkbox" name="confirmed" value="1" required> Saya telah mendapat penjelasan dan mengonfirmasi keputusan di atas.</label>
            <p id="canvas-label" class="text-sm font-semibold">Bubuhkan tanda tangan Anda</p>
            <canvas id="signature-pad" aria-labelledby="canvas-label" width="600" height="200" class="w-full touch-none rounded-lg border border-neutral-400 bg-white"></canvas>
            <input type="hidden" id="signature" name="signature">
            <p id="signature-error" role="alert" class="text-sm text-red-700">@error('signature'){{ $message }}@enderror</p>
            <div class="flex flex-wrap justify-between gap-2">
                <button type="button" id="clear-signature" class="rounded-lg border border-neutral-300 px-4 py-2">Hapus goresan</button>
                <button type="submit" class="rounded-lg bg-neutral-900 px-4 py-2 font-semibold text-neutral-50">Kirim keputusan</button>
            </div>
        </form>
        <script>
            const pad = document.getElementById('signature-pad');
            const ctx = pad.getContext('2d');
            const strokes = [];
            let activePointer = null;
            function point(event) {
                const rect = pad.getBoundingClientRect();
                return {
                    x: Math.max(0, Math.min(599, Math.round((event.clientX - rect.left) * 600 / rect.width))),
                    y: Math.max(0, Math.min(199, Math.round((event.clientY - rect.top) * 200 / rect.height))),
                };
            }
            pad.addEventListener('pointerdown', (event) => {
                if (activePointer !== null || strokes.length >= 40) return;
                activePointer = event.pointerId;
                pad.setPointerCapture(activePointer);
                strokes.push([point(event)]);
            });
            pad.addEventListener('pointermove', (event) => {
                if (event.pointerId !== activePointer) return;
                const stroke = strokes.at(-1);
                if (stroke.length >= 250) return;
                const previous = stroke.at(-1);
                const next = point(event);
                if (Math.hypot(next.x - previous.x, next.y - previous.y) < 2) return;
                stroke.push(next);
                ctx.beginPath();
                ctx.moveTo(previous.x, previous.y);
                ctx.lineTo(next.x, next.y);
                ctx.lineWidth = 3;
                ctx.lineCap = 'round';
                ctx.stroke();
            });
            for (const type of ['pointerup', 'pointercancel', 'lostpointercapture']) {
                pad.addEventListener(type, (event) => { if (event.pointerId === activePointer) activePointer = null; });
            }
            document.getElementById('clear-signature').addEventListener('click', () => {
                strokes.length = 0;
                activePointer = null;
                ctx.clearRect(0, 0, 600, 200);
                document.getElementById('signature-error').textContent = '';
            });
            document.getElementById('signature-form').addEventListener('submit', (event) => {
                const lines = strokes.filter((stroke) => stroke.length > 1);
                if (new Set(lines.flat().map(({x,y}) => x + ':' + y)).size < 4) {
                    event.preventDefault();
                    document.getElementById('signature-error').textContent = 'Bubuhkan tanda tangan terlebih dahulu.';
                    return;
                }
                document.getElementById('signature').value = JSON.stringify(lines);
                event.submitter.disabled = true;
            });
        </script>
    @endif
</main>
</body>
</html>
