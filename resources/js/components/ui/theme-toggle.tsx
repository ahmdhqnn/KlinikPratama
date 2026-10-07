import { Moon, Sun } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';

export function ThemeToggle({ className }: { className?: string }) {
    const [isDarkMode, setIsDarkMode] = useState(() => typeof document !== 'undefined' && document.documentElement.classList.contains('dark'));

    useEffect(() => {
        document.documentElement.classList.toggle('dark', isDarkMode);
        window.localStorage.setItem('clinic-theme', isDarkMode ? 'dark' : 'light');
    }, [isDarkMode]);

    return (
        <Button
            aria-label={isDarkMode ? 'Aktifkan tema terang' : 'Aktifkan tema gelap'}
            aria-pressed={isDarkMode}
            className={className}
            onClick={() => setIsDarkMode((darkMode) => !darkMode)}
            size="icon"
            title={isDarkMode ? 'Tema terang' : 'Tema gelap'}
            variant="ghost"
        >
            {isDarkMode ? <Sun aria-hidden="true" className="size-4" /> : <Moon aria-hidden="true" className="size-4" />}
        </Button>
    );
}
