import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';

interface Security { userId: number; expiresAt: number; serverNow: number; idleMinutes: number; mustChangePassword: boolean }

export function SessionSecurity() {
    const security = usePage().props.sessionSecurity as Security | null | undefined;
    const [warning, setWarning] = useState(false);
    const [lockState, setLockState] = useState<'checking' | 'offline' | null>(null);
    const verifySession = useRef<() => void>(() => undefined);
    const continueSession = useRef<() => void>(() => undefined);
    useEffect(() => {
        if (!security) return;
        let deadline = Date.now() + Math.max(0, security.expiresAt - security.serverNow);
        let lastSent = 0;
        let pending = false;
        let expired = false;
        let disposed = false;
        let checking = false;
        const previousVisibility = document.body.style.visibility;
        const channel = typeof BroadcastChannel !== 'undefined' ? new BroadcastChannel('clinic-session') : null;
        function expire() {
            if (expired || disposed) return;
            expired = true;
            document.body.style.visibility = 'hidden';
            channel?.postMessage({ type: 'expired', userId: security!.userId });
            // The server validates expiry on navigation. Never POST logout from a stale
            // tab: its cookie may now belong to a different operator.
            window.location.assign('/login');
        }
        async function activity(event?: Event) {
            if (event && !event.isTrusted) return;
            if (Date.now() >= deadline) { expire(); return; }
            if (pending || Date.now() - lastSent < 60_000) return;
            pending = true;
            try {
                const cookie = document.cookie.split('; ').find((value) => value.startsWith('XSRF-TOKEN='));
                const token = cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
                const sentAt = Date.now();
                const response = await fetch('/session/activity', { method: 'POST', credentials: 'same-origin', headers: { Accept: 'application/json', 'X-XSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' } });
                if ([401, 403, 419].includes(response.status)) { expire(); return; }
                if (response.ok) {
                    const result = await response.json() as Security;
                    if (disposed) return;
                    if (result.userId !== security!.userId) { switchAccount(); return; }
                    lastSent = Date.now();
                    deadline = lastSent + Math.max(0, result.expiresAt - result.serverNow - (lastSent - sentAt));
                    setWarning(false);
                    channel?.postMessage({ type: 'activity', deadline, userId: security!.userId });
                }
            } catch { setWarning(true); } finally { pending = false; }
        }
        function switchAccount() {
            if (disposed) return;
            expired = true;
            document.body.style.visibility = 'hidden';
            window.location.assign('/');
        }
        async function verifyIdentity() {
            if (checking || expired || disposed) return;
            checking = true;
            document.body.style.visibility = 'hidden';
            setLockState('checking');
            const sentAt = Date.now();
            try {
                const response = await fetch('/session/status', { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
                if (disposed) return;
                if ([401, 403, 419].includes(response.status)) { expire(); return; }
                if (!response.ok) throw new Error('Session verification unavailable');
                const result = await response.json() as Security;
                if (disposed) return;
                if (result.userId !== security!.userId) { switchAccount(); return; }
                deadline = Date.now() + Math.max(0, result.expiresAt - result.serverNow - (Date.now() - sentAt));
                if (Date.now() >= deadline) { expire(); return; }
                document.body.style.visibility = previousVisibility;
                setLockState(null);
                tick();
            } catch { if (!disposed) setLockState('offline'); } finally { checking = false; }
        }
        verifySession.current = () => { void verifyIdentity(); };
        function pageHide() { document.body.style.visibility = 'hidden'; }
        function pageShow(event: PageTransitionEvent) { if (event.persisted) void verifyIdentity(); }
        function visibilityChange() { if (document.visibilityState === 'visible') void verifyIdentity(); else tick(); }
        continueSession.current = () => { void activity(); };
        function tick() { if (Date.now() >= deadline) expire(); else setWarning(deadline - Date.now() <= 60_000); }
        channel && (channel.onmessage = (event) => {
            if (event.data?.type === 'account' && typeof event.data.userId === 'number' && event.data.userId !== security!.userId) { switchAccount(); return; }
            if (event.data?.userId !== security!.userId) return;
            if (['expired', 'signed-out'].includes(event.data?.type)) { expired = true; document.body.style.visibility = 'hidden'; window.location.assign('/login'); }
            if (event.data?.type === 'activity' && typeof event.data.deadline === 'number') deadline = event.data.deadline;
        });
        channel?.postMessage({ type: 'account', userId: security.userId });
        window.addEventListener('pagehide', pageHide);
        window.addEventListener('pageshow', pageShow);
        const events = ['pointerdown', 'keydown', 'scroll'] as const;
        for (const event of events) window.addEventListener(event, activity, { passive: true });
        document.addEventListener('visibilitychange', visibilityChange);
        const timer = window.setInterval(tick, 1000);
        return () => { disposed = true; if (!expired) document.body.style.visibility = previousVisibility; clearInterval(timer); channel?.close(); for (const event of events) window.removeEventListener(event, activity); document.removeEventListener('visibilitychange', visibilityChange); window.removeEventListener('pagehide', pageHide); window.removeEventListener('pageshow', pageShow); };
    }, [security?.userId, security?.expiresAt, security?.serverNow, security?.idleMinutes]);

    return <>{lockState && <div style={{ visibility: 'visible' }} className="fixed inset-0 z-[100] flex items-center justify-center bg-neutral-100 p-6"><Alert className="max-w-lg"><AlertTitle>{lockState === 'checking' ? 'Memverifikasi sesi' : 'Sesi belum dapat diverifikasi'}</AlertTitle><AlertDescription>{lockState === 'checking' ? 'Memeriksa akun dan masa aktif sebelum menampilkan kembali halaman.' : 'Periksa koneksi, lalu coba kembali. Isi halaman disembunyikan untuk melindungi informasi pasien; draf di halaman tetap dipertahankan.'}</AlertDescription>{lockState === 'offline' && <Button className="mt-4" variant="secondary" onClick={() => verifySession.current()}>Periksa ulang sesi</Button>}</Alert></div>}{security?.mustChangePassword && <Alert className="mb-4"><AlertTitle>Ubah kata sandi sementara</AlertTitle><AlertDescription>Gunakan kata sandi pribadi yang baru melalui bagian Kata sandi sebelum melanjutkan pelayanan.</AlertDescription></Alert>}{warning && <div className="fixed inset-x-4 bottom-4 z-50 mx-auto max-w-lg"><Alert><AlertTitle>Sesi segera berakhir</AlertTitle><AlertDescription>Sesi berakhir setelah 15 menit tanpa aktivitas. Simpan pekerjaan yang sedang diisi.</AlertDescription><Button className="mt-3" onClick={() => continueSession.current()} variant="secondary">Lanjutkan bekerja</Button></Alert></div>}</>;
}
