<!DOCTYPE html>
<html lang="id" class="min-h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/webp" href="{{ asset('logo_kemenpar.webp') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        (() => {
            let savedTheme;
            try { savedTheme = localStorage.getItem('clinic-theme'); } catch {}
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', savedTheme === 'dark' || (savedTheme !== 'light' && prefersDark));
        })();
    </script>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="min-h-full font-sans antialiased">
    @inertia
    <div class="clinic-bootstrap mx-auto max-w-4xl space-y-5 p-4 print:hidden sm:p-8" role="status">
        <span class="sr-only">Memuat aplikasi…</span>
        <div aria-hidden="true" class="h-7 w-48 rounded-md bg-neutral-200 motion-safe:animate-pulse"></div>
        <div aria-hidden="true" class="grid gap-5 rounded-xl bg-surface p-5 sm:p-6">
            <div class="h-4 w-40 rounded-md bg-neutral-200 motion-safe:animate-pulse"></div>
            <div class="h-10 rounded-md bg-neutral-200 motion-safe:animate-pulse"></div>
            <div class="h-10 rounded-md bg-neutral-200 motion-safe:animate-pulse"></div>
        </div>
    </div>
    <noscript>
        <style>.clinic-bootstrap { display: none; }</style>
        <p class="p-4 text-neutral-900">Aktifkan JavaScript untuk membuka aplikasi klinik.</p>
    </noscript>
</body>
</html>
