import { Toaster as Sonner, type ToasterProps } from 'sonner';
import { useEffect, useState } from 'react';

export function Toaster({ toastOptions, ...props }: ToasterProps) {
    const [theme, setTheme] = useState<'light' | 'dark'>(() => typeof document !== 'undefined' && document.documentElement.classList.contains('dark') ? 'dark' : 'light');

    useEffect(() => {
        const observer = new MutationObserver(() => {
            setTheme(document.documentElement.classList.contains('dark') ? 'dark' : 'light');
        });

        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

        return () => observer.disconnect();
    }, []);

    return (
        <Sonner
            closeButton
            richColors={false}
            {...props}
            theme={theme}
            style={{ ...props.style, fontFamily: 'var(--font-sans)' }}
            toastOptions={{
                ...toastOptions,
                classNames: {
                    ...toastOptions?.classNames,
                    toast: `font-sans !border-neutral-200 !bg-surface !text-neutral-950 shadow-lg ${toastOptions?.classNames?.toast ?? ''}`,
                    error: '!border-red-300 dark:!border-red-800 [&_[data-icon]]:text-red-700 dark:[&_[data-icon]]:text-red-400',
                    warning: '!border-amber-300 dark:!border-amber-800 [&_[data-icon]]:text-amber-800 dark:[&_[data-icon]]:text-amber-300',
                    success: '[&_[data-icon]]:text-emerald-700 dark:[&_[data-icon]]:text-emerald-400',
                    info: '[&_[data-icon]]:text-blue-700 dark:[&_[data-icon]]:text-blue-400',
                    title: `text-sm font-semibold ${toastOptions?.classNames?.title ?? ''}`,
                    description: `text-sm !text-neutral-600 ${toastOptions?.classNames?.description ?? ''}`,
                },
            }}
        />
    );
}
