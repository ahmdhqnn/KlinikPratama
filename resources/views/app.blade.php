<!DOCTYPE html>
<html lang="id" class="min-h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/webp" href="{{ asset('logo_kemenpar.webp') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.theme-init')
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="min-h-full font-sans antialiased">
    @inertia
    <div class="clinic-bootstrap mx-auto max-w-[1600px] space-y-6 p-4 print:hidden sm:p-6 lg:p-8" role="status">
        <span class="sr-only">Memuat aplikasi…</span>
        <div aria-hidden="true" class="flex flex-wrap items-end justify-between gap-4">
            <div class="space-y-2">
                <div class="h-4 w-32 rounded-md bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
                <div class="h-8 w-56 rounded-md bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse sm:w-72"></div>
                <div class="h-4 w-72 max-w-full rounded-md bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse sm:w-96"></div>
            </div>
            <div class="h-9 w-36 rounded-lg bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
        </div>
        <div aria-hidden="true" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="flex min-h-32 items-center justify-between gap-4 rounded-2xl bg-surface p-5 sm:p-6">
                <div class="min-w-0 flex-1 space-y-2.5">
                    <div class="h-4 w-28 rounded-md bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
                    <div class="h-7 w-16 rounded-md bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
                    <div class="h-3.5 w-4/5 rounded-md bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
                </div>
                <div class="size-10 shrink-0 rounded-xl bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
            </div>
            <div class="flex min-h-32 items-center justify-between gap-4 rounded-2xl bg-surface p-5 sm:p-6">
                <div class="min-w-0 flex-1 space-y-2.5">
                    <div class="h-4 w-24 rounded-md bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
                    <div class="h-7 w-14 rounded-md bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
                    <div class="h-3.5 w-3/4 rounded-md bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
                </div>
                <div class="size-10 shrink-0 rounded-xl bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
            </div>
            <div class="hidden min-h-32 items-center justify-between gap-4 rounded-2xl bg-surface p-5 sm:flex sm:p-6">
                <div class="min-w-0 flex-1 space-y-2.5">
                    <div class="h-4 w-32 rounded-md bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
                    <div class="h-7 w-16 rounded-md bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
                    <div class="h-3.5 w-4/5 rounded-md bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
                </div>
                <div class="size-10 shrink-0 rounded-xl bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
            </div>
            <div class="hidden min-h-32 items-center justify-between gap-4 rounded-2xl bg-surface p-5 xl:flex sm:p-6">
                <div class="min-w-0 flex-1 space-y-2.5">
                    <div class="h-4 w-28 rounded-md bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
                    <div class="h-7 w-14 rounded-md bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
                    <div class="h-3.5 w-3/4 rounded-md bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
                </div>
                <div class="size-10 shrink-0 rounded-xl bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
            </div>
        </div>
        <div aria-hidden="true" class="overflow-hidden rounded-2xl bg-surface">
            <div class="flex items-start gap-3 border-b border-neutral-100 p-5 sm:p-6">
                <div class="size-10 shrink-0 rounded-xl bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
                <div class="min-w-0 flex-1 space-y-2">
                    <div class="h-5 w-44 rounded-md bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
                    <div class="h-4 w-64 max-w-full rounded-md bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
                </div>
            </div>
            <div class="divide-y divide-neutral-100">
                <div class="grid grid-cols-[2fr_1fr] items-center gap-4 px-5 py-4 sm:grid-cols-[2fr_1fr_1fr_auto] sm:px-6">
                    <div class="h-4 w-4/5 rounded-md bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
                    <div class="h-4 w-3/4 rounded-md bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
                    <div class="hidden h-6 w-20 rounded-full bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse sm:block"></div>
                    <div class="hidden size-8 rounded-lg bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse sm:block"></div>
                </div>
                <div class="grid grid-cols-[2fr_1fr] items-center gap-4 px-5 py-4 sm:grid-cols-[2fr_1fr_1fr_auto] sm:px-6">
                    <div class="h-4 w-3/4 rounded-md bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
                    <div class="h-4 w-2/3 rounded-md bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
                    <div class="hidden h-6 w-20 rounded-full bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse sm:block"></div>
                    <div class="hidden size-8 rounded-lg bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse sm:block"></div>
                </div>
                <div class="grid grid-cols-[2fr_1fr] items-center gap-4 px-5 py-4 sm:grid-cols-[2fr_1fr_1fr_auto] sm:px-6">
                    <div class="h-4 w-2/3 rounded-md bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
                    <div class="h-4 w-3/4 rounded-md bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse"></div>
                    <div class="hidden h-6 w-20 rounded-full bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse sm:block"></div>
                    <div class="hidden size-8 rounded-lg bg-neutral-200/75 dark:bg-neutral-100 motion-safe:animate-pulse sm:block"></div>
                </div>
            </div>
        </div>
    </div>
    <noscript>
        <style>.clinic-bootstrap { display: none; }</style>
        <p class="p-4 text-neutral-900">Aktifkan JavaScript untuk membuka aplikasi klinik.</p>
    </noscript>
</body>
</html>
