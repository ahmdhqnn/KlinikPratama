import { useEffect, useId, useRef, useState, type KeyboardEvent as ReactKeyboardEvent } from 'react';
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface DateRange {
    from: string;
    to: string;
}

function toDateKey(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function fromDateKey(value?: string): Date | null {
    if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return null;
    }

    const [year, month, day] = value.split('-').map(Number);
    const date = new Date(year, month - 1, day);

    return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day ? date : null;
}

function formatDate(value: string): string {
    const date = fromDateKey(value);

    return date ? new Intl.DateTimeFormat('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }).format(date) : '';
}

export function DateRangePicker({
    id,
    from,
    to,
    onChange,
    min,
    max,
    required = false,
    disabled = false,
    placeholder = 'Pilih rentang tanggal',
    className,
    'aria-describedby': ariaDescribedBy,
}: {
    id?: string;
    from: string;
    to: string;
    onChange: (range: DateRange) => void;
    min?: string;
    max?: string;
    required?: boolean;
    disabled?: boolean;
    placeholder?: string;
    className?: string;
    'aria-describedby'?: string;
}) {
    const [open, setOpen] = useState(false);
    const calendarId = useId();
    const containerRef = useRef<HTMLDivElement>(null);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const initialDate = fromDateKey(from) ?? fromDateKey(to) ?? new Date();
    const [visibleMonth, setVisibleMonth] = useState(() => new Date(initialDate.getFullYear(), initialDate.getMonth(), 1));
    const [activeDateKey, setActiveDateKey] = useState(from || to || toDateKey(new Date()));
    const [hoveredDateKey, setHoveredDateKey] = useState('');
    const firstOfMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth(), 1);
    const firstCalendarDay = new Date(firstOfMonth);
    firstCalendarDay.setDate(1 - ((firstOfMonth.getDay() + 6) % 7));
    const days = Array.from({ length: 42 }, (_, index) => {
        const day = new Date(firstCalendarDay);
        day.setDate(firstCalendarDay.getDate() + index);
        return day;
    });
    const weekdays = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
    const monthLabel = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' }).format(visibleMonth);
    const completeRange = Boolean(from && to);
    const previewTo = !completeRange && from && hoveredDateKey ? hoveredDateKey : to;
    const orderedStart = previewTo && previewTo < from ? previewTo : from;
    const orderedEnd = previewTo && previewTo < from ? from : previewTo;
    const dateLabel = completeRange ? `${formatDate(from)} – ${formatDate(to)}` : from ? `${formatDate(from)} – pilih tanggal akhir` : placeholder;

    useEffect(() => {
        if (!open) {
            return;
        }

        window.requestAnimationFrame(() => {
            containerRef.current?.querySelector<HTMLButtonElement>(`[data-date="${activeDateKey}"]`)?.focus();
        });

        function closeOutside(event: PointerEvent): void {
            if (!containerRef.current?.contains(event.target as Node)) {
                setOpen(false);
                setHoveredDateKey('');
            }
        }

        function closeOnEscape(event: KeyboardEvent): void {
            if (event.key === 'Escape') {
                setOpen(false);
                setHoveredDateKey('');
                triggerRef.current?.focus();
            }
        }

        document.addEventListener('pointerdown', closeOutside);
        document.addEventListener('keydown', closeOnEscape);

        return () => {
            document.removeEventListener('pointerdown', closeOutside);
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, [activeDateKey, open]);

    function moveMonth(offset: number): void {
        setVisibleMonth((month) => new Date(month.getFullYear(), month.getMonth() + offset, 1));
    }

    function chooseDate(date: Date): void {
        const nextValue = toDateKey(date);

        if (min && nextValue < min || max && nextValue > max) {
            return;
        }

        setActiveDateKey(nextValue);
        setHoveredDateKey('');

        if (!from || completeRange || nextValue < from) {
            onChange({ from: nextValue, to: '' });
            setVisibleMonth(new Date(date.getFullYear(), date.getMonth(), 1));
            return;
        }

        onChange({ from, to: nextValue });
        setOpen(false);
        triggerRef.current?.focus();
    }

    function moveFocus(event: ReactKeyboardEvent<HTMLButtonElement>, date: Date): void {
        const offsetByKey: Record<string, number> = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
        let nextDate: Date | null = null;

        if (event.key in offsetByKey) {
            nextDate = new Date(date);
            nextDate.setDate(date.getDate() + offsetByKey[event.key]);
        } else if (event.key === 'Home' || event.key === 'End') {
            nextDate = new Date(date);
            const mondayOffset = (date.getDay() + 6) % 7;
            nextDate.setDate(date.getDate() + (event.key === 'Home' ? -mondayOffset : 6 - mondayOffset));
        } else if (event.key === 'PageUp' || event.key === 'PageDown') {
            const monthOffset = event.key === 'PageUp' ? -1 : 1;
            const targetMonth = new Date(date.getFullYear(), date.getMonth() + monthOffset + 1, 0).getDate();
            nextDate = new Date(date.getFullYear(), date.getMonth() + monthOffset, Math.min(date.getDate(), targetMonth));
        }

        if (!nextDate) {
            return;
        }

        event.preventDefault();
        const nextValue = toDateKey(nextDate);
        setActiveDateKey(nextValue);
        if (nextDate.getMonth() !== visibleMonth.getMonth() || nextDate.getFullYear() !== visibleMonth.getFullYear()) {
            setVisibleMonth(new Date(nextDate.getFullYear(), nextDate.getMonth(), 1));
        }
        window.requestAnimationFrame(() => {
            containerRef.current?.querySelector<HTMLButtonElement>(`[data-date="${nextValue}"]`)?.focus();
        });
    }

    const today = toDateKey(new Date());
    const displayName = completeRange ? `Rentang tanggal ${formatDate(from)} sampai ${formatDate(to)}` : from ? `Mulai ${formatDate(from)}, pilih tanggal akhir` : placeholder;

    return (
        <div className="relative" ref={containerRef}>
            <input aria-hidden="true" className="pointer-events-none absolute size-px opacity-0" max={max} min={min} onChange={() => undefined} onInvalid={(event) => { event.preventDefault(); setOpen(true); triggerRef.current?.focus(); }} required={required} tabIndex={-1} type="date" value={from} />
            <input aria-hidden="true" className="pointer-events-none absolute size-px opacity-0" max={max} min={from || min} onChange={() => undefined} onInvalid={(event) => { event.preventDefault(); setOpen(true); triggerRef.current?.focus(); }} required={required} tabIndex={-1} type="date" value={to} />
            <button
                aria-describedby={ariaDescribedBy}
                aria-expanded={open}
                aria-controls={calendarId}
                aria-haspopup="dialog"
                aria-label={displayName}
                aria-required={required || undefined}
                className={cn('flex h-10 w-full items-center justify-between gap-3 rounded-lg border border-neutral-200 bg-surface px-3.5 py-2 text-left text-sm shadow-sm outline-none transition hover:border-neutral-300 focus-visible:border-neutral-500 focus-visible:ring-4 focus-visible:ring-neutral-500/10 disabled:cursor-not-allowed disabled:opacity-50', completeRange || from ? 'text-neutral-950' : 'text-neutral-400', className)}
                disabled={disabled}
                id={id}
                onClick={() => {
                    if (!open) {
                        const selectedDate = fromDateKey(from) ?? fromDateKey(to);
                        if (selectedDate) {
                            setVisibleMonth(new Date(selectedDate.getFullYear(), selectedDate.getMonth(), 1));
                            setActiveDateKey(toDateKey(selectedDate));
                        }
                    }
                    setOpen((current) => !current);
                }}
                ref={triggerRef}
                type="button"
            >
                <span className="truncate">{dateLabel}</span>
                <CalendarDays aria-hidden="true" className="size-4 shrink-0 text-neutral-500" />
            </button>
            {open && (
                <div aria-label="Pilih rentang tanggal" className="absolute left-0 top-full z-[85] mt-2 w-[min(22rem,calc(100vw-2rem))] rounded-xl border border-neutral-200 bg-surface p-3 shadow-xl shadow-inverse/15" id={calendarId} role="dialog">
                    <div className="mb-3 flex items-center justify-between gap-2">
                        <button aria-label="Bulan sebelumnya" className="inline-flex size-9 shrink-0 items-center justify-center rounded-lg text-neutral-600 hover:bg-neutral-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-500" onClick={() => moveMonth(-1)} type="button"><ChevronLeft aria-hidden="true" className="size-4" /></button>
                        <p aria-live="polite" className="text-sm font-semibold capitalize text-neutral-900">{monthLabel}</p>
                        <button aria-label="Bulan berikutnya" className="inline-flex size-9 shrink-0 items-center justify-center rounded-lg text-neutral-600 hover:bg-neutral-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-500" onClick={() => moveMonth(1)} type="button"><ChevronRight aria-hidden="true" className="size-4" /></button>
                    </div>
                    <div className="grid grid-cols-7 gap-1">
                        {weekdays.map((day) => <span aria-hidden="true" className="py-1 text-center text-[11px] font-medium text-neutral-400" key={day}>{day}</span>)}
                        {days.map((date) => {
                            const dateKey = toDateKey(date);
                            const inMonth = date.getMonth() === visibleMonth.getMonth();
                            const outsideBounds = Boolean((min && dateKey < min) || (max && dateKey > max) || (from && !completeRange && dateKey < from));
                            const isEndpoint = dateKey === from || dateKey === to;
                            const inRange = Boolean(orderedStart && orderedEnd && dateKey >= orderedStart && dateKey <= orderedEnd);

                            return (
                                <button
                                    aria-label={new Intl.DateTimeFormat('id-ID', { dateStyle: 'full' }).format(date)}
                                    aria-pressed={isEndpoint}
                                    className={cn('flex size-9 items-center justify-center rounded-lg text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-500', isEndpoint ? 'bg-neutral-700 font-semibold text-neutral-50 hover:bg-neutral-800' : inRange ? 'bg-neutral-100 text-neutral-900' : dateKey === today ? 'font-semibold text-neutral-700 hover:bg-neutral-50' : 'text-neutral-700 hover:bg-neutral-100', !inMonth && !isEndpoint && 'text-neutral-300', outsideBounds && 'cursor-not-allowed opacity-30')}
                                    data-date={dateKey}
                                    disabled={outsideBounds}
                                    key={dateKey}
                                    onClick={() => chooseDate(date)}
                                    onKeyDown={(event) => moveFocus(event, date)}
                                    onMouseEnter={() => setHoveredDateKey(dateKey)}
                                    tabIndex={dateKey === activeDateKey ? 0 : -1}
                                    type="button"
                                >
                                    {date.getDate()}
                                </button>
                            );
                        })}
                    </div>
                    <div aria-live="polite" className="mt-3 border-t border-neutral-100 pt-3 text-xs text-neutral-500">
                        {completeRange ? `${formatDate(from)} – ${formatDate(to)}` : from ? 'Pilih tanggal akhir untuk menyelesaikan periode.' : 'Pilih tanggal mulai, lalu tanggal akhir.'}
                    </div>
                </div>
            )}
        </div>
    );
}
