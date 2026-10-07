import { createContext, useContext, useId, type ComponentProps, type KeyboardEvent, type ReactNode } from 'react';
import { cn } from '@/lib/utils';

interface TabsContextValue {
    value: string;
    onValueChange: (value: string) => void;
    baseId: string;
}

const TabsContext = createContext<TabsContextValue | null>(null);

function useTabs(): TabsContextValue {
    const context = useContext(TabsContext);

    if (!context) {
        throw new Error('Tabs components must be used inside Tabs.');
    }

    return context;
}

export function Tabs({ value, onValueChange, className, children, ...props }: Omit<ComponentProps<'div'>, 'onChange'> & {
    value: string;
    onValueChange: (value: string) => void;
    children: ReactNode;
}) {
    const baseId = useId();

    return (
        <TabsContext.Provider value={{ value, onValueChange, baseId }}>
            <div className={className} {...props}>{children}</div>
        </TabsContext.Provider>
    );
}

export function TabsList({ className, ...props }: ComponentProps<'div'>) {
    return <div aria-orientation="horizontal" className={cn('flex overflow-x-auto border-b border-neutral-100', className)} role="tablist" {...props} />;
}

export function TabsTrigger({ value, className, onKeyDown, ...props }: ComponentProps<'button'> & { value: string }) {
    const tabs = useTabs();
    const selected = tabs.value === value;

    function handleKeyDown(event: KeyboardEvent<HTMLButtonElement>): void {
        onKeyDown?.(event);
        if (event.defaultPrevented || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
            return;
        }

        event.preventDefault();
        const triggers = Array.from(event.currentTarget.closest('[role="tablist"]')?.querySelectorAll<HTMLButtonElement>('[role="tab"]') ?? []);
        const currentIndex = triggers.indexOf(event.currentTarget);
        const nextIndex = event.key === 'Home'
            ? 0
            : event.key === 'End'
                ? triggers.length - 1
                : (currentIndex + (event.key === 'ArrowRight' ? 1 : triggers.length - 1)) % triggers.length;
        triggers[nextIndex]?.focus();
        const nextValue = triggers[nextIndex]?.dataset.value;
        if (nextValue) {
            tabs.onValueChange(nextValue);
        }
    }

    return (
        <button
            aria-controls={`${tabs.baseId}-panel-${value}`}
            aria-selected={selected}
            className={cn('inline-flex min-h-11 shrink-0 items-center justify-center gap-2 border-b-2 px-4 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-neutral-500', selected ? 'border-neutral-600 text-neutral-800' : 'border-transparent text-neutral-500 hover:text-neutral-900', className)}
            data-value={value}
            id={`${tabs.baseId}-tab-${value}`}
            onClick={() => tabs.onValueChange(value)}
            onKeyDown={handleKeyDown}
            role="tab"
            tabIndex={selected ? 0 : -1}
            type="button"
            {...props}
        />
    );
}

export function TabsContent({ value, className, ...props }: ComponentProps<'section'> & { value: string }) {
    const tabs = useTabs();

    return (
        <section
            aria-labelledby={`${tabs.baseId}-tab-${value}`}
            className={className}
            hidden={tabs.value !== value}
            id={`${tabs.baseId}-panel-${value}`}
            role="tabpanel"
            tabIndex={0}
            {...props}
        />
    );
}
