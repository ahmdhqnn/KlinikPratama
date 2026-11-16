import { Moon, Sun } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';

export function ThemeToggle({ className }: { className?: string }) {
    const [isDarkMode, setIsDarkMode] = useState(false);
    const savedTheme = useRef<string | null>(null);

    useEffect(() => {
        const preference = window.matchMedia('(prefers-color-scheme: dark)');
        try { savedTheme.current = window.localStorage.getItem('clinic-theme'); } catch {}

        function syncTheme() {
            const dark = savedTheme.current === 'dark' || (savedTheme.current !== 'light' && preference.matches);
            document.documentElement.classList.toggle('dark', dark);
            setIsDarkMode(dark);
        }

        function onStorage(event: StorageEvent) {
            if (event.storageArea !== window.localStorage || (event.key !== null && event.key !== 'clinic-theme')) return;
            savedTheme.current = event.newValue;
            syncTheme();
        }

        syncTheme();
        preference.addEventListener('change', syncTheme);
        window.addEventListener('storage', onStorage);
        return () => {
            preference.removeEventListener('change', syncTheme);
            window.removeEventListener('storage', onStorage);
        };
    }, []);

    function toggleTheme() {
        const dark = !document.documentElement.classList.contains('dark');
        savedTheme.current = dark ? 'dark' : 'light';
        document.documentElement.classList.toggle('dark', dark);
        setIsDarkMode(dark);
        try { window.localStorage.setItem('clinic-theme', savedTheme.current); } catch {}
    }

    return (
        <Button
            aria-label={isDarkMode ? 'Aktifkan tema terang' : 'Aktifkan tema gelap'}
            aria-pressed={isDarkMode}
            className={className}
            onClick={toggleTheme}
            size="icon"
            title={isDarkMode ? 'Tema terang' : 'Tema gelap'}
            variant="ghost"
        >
            {isDarkMode ? <Sun aria-hidden="true" className="size-4" /> : <Moon aria-hidden="true" className="size-4" />}
        </Button>
    );
}
