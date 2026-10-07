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
            richColors
            {...props}
            theme={theme}
            toastOptions={{
                ...toastOptions,
                classNames: {
                    ...toastOptions?.classNames,
                    toast: `font-sans shadow-lg ${toastOptions?.classNames?.toast ?? ''}`,
                    title: `text-sm font-semibold ${toastOptions?.classNames?.title ?? ''}`,
                    description: `text-sm ${toastOptions?.classNames?.description ?? ''}`,
                },
            }}
        />
    );
}
