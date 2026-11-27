import { Toaster as Sonner, type ToasterProps } from 'sonner';
import { useEffect, useState } from 'react';

const toastTheme = {
    fontFamily: 'var(--font-sans)',
    '--normal-bg': 'var(--clinic-surface)',
    '--normal-bg-hover': 'var(--clinic-neutral-100)',
    '--normal-border': 'var(--clinic-neutral-200)',
    '--normal-border-hover': 'var(--clinic-neutral-300)',
    '--normal-text': 'var(--clinic-neutral-950)',
};

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
            {...props}
            closeButton
            richColors={false}
            theme={theme}
            style={{ ...toastTheme, ...props.style }}
            toastOptions={{
                ...toastOptions,
                classNames: {
                    ...toastOptions?.classNames,
                    error: `[&_[data-icon]]:text-red-700 dark:[&_[data-icon]]:text-red-400 ${toastOptions?.classNames?.error ?? ''}`,
                    warning: `[&_[data-icon]]:text-amber-800 dark:[&_[data-icon]]:text-amber-300 ${toastOptions?.classNames?.warning ?? ''}`,
                    success: `[&_[data-icon]]:text-emerald-700 dark:[&_[data-icon]]:text-emerald-400 ${toastOptions?.classNames?.success ?? ''}`,
                    info: `[&_[data-icon]]:text-blue-700 dark:[&_[data-icon]]:text-blue-400 ${toastOptions?.classNames?.info ?? ''}`,
                    title: `text-sm font-semibold ${toastOptions?.classNames?.title ?? ''}`,
                    description: `text-sm !text-neutral-600 ${toastOptions?.classNames?.description ?? ''}`,
                },
            }}
        />
    );
}
