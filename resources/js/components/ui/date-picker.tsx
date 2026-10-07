import { useEffect, useId, useMemo, useRef, useState, type KeyboardEvent as ReactKeyboardEvent, type MouseEvent } from 'react';
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface DatePickerChangeEvent {
    target: { value: string };
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

    return new Date(year, month - 1, day);
}

export function DatePicker({
    id,
    value = '',
    onChange,
    min,
    max,
    required = false,
    disabled = false,
    readOnly = false,
    placeholder = 'Pilih tanggal',
    className,
    name,
    'aria-label': ariaLabel,
    'aria-describedby': ariaDescribedBy,
}: {
    id?: string;
    value?: string;
    onChange?: (event: DatePickerChangeEvent) => void;
    min?: string;
    max?: string;
    required?: boolean;
    disabled?: boolean;
    readOnly?: boolean;
    placeholder?: string;
    className?: string;
    name?: string;
    'aria-label'?: string;
    'aria-describedby'?: string;
}) {
    const [open, setOpen] = useState(false);
    const calendarId = useId();
    const containerRef = useRef<HTMLDivElement>(null);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const selectedDate = useMemo(() => fromDateKey(value), [value]);
    const [activeDateKey, setActiveDateKey] = useState(value || toDateKey(new Date()));
    const [visibleMonth, setVisibleMonth] = useState(() => {
        const initialDate = fromDateKey(value) ?? new Date();
        return new Date(initialDate.getFullYear(), initialDate.getMonth(), 1);
    });
    const monthLabel = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' }).format(visibleMonth);
    const dateLabel = selectedDate
        ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'long' }).format(selectedDate)
        : placeholder;
    const firstOfMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth(), 1);
    const firstCalendarDay = new Date(firstOfMonth);
    firstCalendarDay.setDate(1 - ((firstOfMonth.getDay() + 6) % 7));
    const days = Array.from({ length: 42 }, (_, index) => {
        const day = new Date(firstCalendarDay);
        day.setDate(firstCalendarDay.getDate() + index);
        return day;
    });
    const weekdays = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

    useEffect(() => {
        if (value) {
            setActiveDateKey(value);
        }
    }, [value]);

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
            }
        }

        function closeOnEscape(event: KeyboardEvent): void {
            if (event.key === 'Escape') {
                setOpen(false);
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

    function changeMonth(event: MouseEvent<HTMLButtonElement>, offset: number): void {
        event.preventDefault();
        setVisibleMonth((month) => new Date(month.getFullYear(), month.getMonth() + offset, 1));
    }

    function chooseDate(date: Date): void {
        const nextValue = toDateKey(date);
        setActiveDateKey(nextValue);
        onChange?.({ target: { value: nextValue } });
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

    return (
        <div className="relative" ref={containerRef}>
            {name && <input name={name} type="hidden" value={value} />}
            <input
                aria-hidden="true"
                className="pointer-events-none absolute size-px opacity-0"
                max={max}
                min={min}
                onChange={() => undefined}
                onInvalid={(event) => {
                    event.preventDefault();
                    setOpen(true);
                    triggerRef.current?.focus();
                }}
                required={required}
                tabIndex={-1}
                type="date"
                value={value}
            />
            <button
                aria-describedby={ariaDescribedBy}
                aria-expanded={open}
                aria-controls={calendarId}
                aria-haspopup="dialog"
                aria-label={ariaLabel ?? dateLabel}
                aria-required={required || undefined}
                className={cn('flex h-11 w-full items-center justify-between gap-3 rounded-lg border border-neutral-200 bg-surface px-3.5 py-2 text-left text-sm shadow-sm outline-none transition hover:border-neutral-300 focus-visible:border-neutral-500 focus-visible:ring-4 focus-visible:ring-neutral-500/10 disabled:cursor-not-allowed disabled:opacity-50', value ? 'text-neutral-950' : 'text-neutral-400', className)}
                disabled={disabled || readOnly}
                id={id}
                onClick={() => {
                    if (!open && selectedDate) {
                        setVisibleMonth(new Date(selectedDate.getFullYear(), selectedDate.getMonth(), 1));
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
                <div aria-label="Pilih tanggal" className="absolute right-0 top-full z-[85] mt-2 w-[min(20rem,calc(100vw-2rem))] rounded-xl border border-neutral-200 bg-surface p-3 shadow-xl shadow-inverse/15 sm:left-0 sm:right-auto" id={calendarId} role="dialog">
                    <div className="mb-3 flex items-center justify-between gap-2">
                        <button aria-label="Bulan sebelumnya" className="inline-flex size-9 items-center justify-center rounded-lg text-neutral-600 hover:bg-neutral-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-500" onClick={(event) => changeMonth(event, -1)} type="button"><ChevronLeft aria-hidden="true" className="size-4" /></button>
                        <p aria-live="polite" className="text-sm font-semibold capitalize text-neutral-900">{monthLabel}</p>
                        <button aria-label="Bulan berikutnya" className="inline-flex size-9 items-center justify-center rounded-lg text-neutral-600 hover:bg-neutral-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-500" onClick={(event) => changeMonth(event, 1)} type="button"><ChevronRight aria-hidden="true" className="size-4" /></button>
                    </div>
                    <div className="grid grid-cols-7 gap-1">
                        {weekdays.map((day) => <span aria-hidden="true" className="py-1 text-center text-[11px] font-medium text-neutral-400" key={day}>{day}</span>)}
                        {days.map((date) => {
                            const dateKey = toDateKey(date);
                            const inMonth = date.getMonth() === visibleMonth.getMonth();
                            const outsideBounds = (min && dateKey < min) || (max && dateKey > max);
                            const selected = dateKey === value;

                            return (
                                <button
                                    aria-label={new Intl.DateTimeFormat('id-ID', { dateStyle: 'full' }).format(date)}
                                    aria-pressed={selected}
                                    className={cn('flex size-9 items-center justify-center rounded-lg text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-500', selected ? 'bg-neutral-600 font-semibold text-neutral-50 hover:bg-neutral-700' : dateKey === today ? 'font-semibold text-neutral-700 hover:bg-neutral-50' : 'text-neutral-700 hover:bg-neutral-100', !inMonth && !selected && 'text-neutral-300', outsideBounds && 'cursor-not-allowed opacity-30')}
                                    disabled={Boolean(outsideBounds)}
                                    data-date={dateKey}
                                    key={dateKey}
                                    onClick={() => chooseDate(date)}
                                    onKeyDown={(event) => moveFocus(event, date)}
                                    tabIndex={dateKey === activeDateKey ? 0 : -1}
                                    type="button"
                                >
                                    {date.getDate()}
                                </button>
                            );
                        })}
                    </div>
                    <div className="mt-3 border-t border-neutral-100 pt-2 text-right">
                        <button className="rounded px-2 py-1 text-xs font-medium text-neutral-700 hover:bg-neutral-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-500" onClick={() => chooseDate(new Date())} type="button">Hari ini</button>
                    </div>
                </div>
            )}
        </div>
    );
}
