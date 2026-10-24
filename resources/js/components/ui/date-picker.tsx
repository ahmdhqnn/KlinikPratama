import { fieldControlInvalidClassName, useFieldControl } from '@/components/ui/field';
import { calendarNavigationDate, clampDate, fromDateKey, isDateWithinBounds, isMonthWithinBounds, toDateKey } from '@/lib/calendar';
import { useEffect, useId, useMemo, useRef, useState, type KeyboardEvent as ReactKeyboardEvent, type MouseEvent } from 'react';
import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react';
import { Select } from '@/components/ui/select';
import { cn } from '@/lib/utils';

export interface DatePickerChangeEvent {
    target: { value: string };
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
    calendarMode = 'default',
    className,
    name,
    'aria-label': ariaLabel,
    'aria-describedby': ariaDescribedBy,
    'aria-invalid': ariaInvalid,
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
    calendarMode?: 'default' | 'date-of-birth';
    className?: string;
    name?: string;
    'aria-label'?: string;
    'aria-describedby'?: string;
    'aria-invalid'?: boolean | 'true' | 'false' | 'grammar' | 'spelling';
}) {
    const accessibility = useFieldControl({ 'aria-describedby': ariaDescribedBy, 'aria-invalid': ariaInvalid, 'aria-required': required || undefined });
    const [open, setOpen] = useState(false);
    const [inlineCalendar, setInlineCalendar] = useState(false);
    const calendarId = useId();
    const containerRef = useRef<HTMLDivElement>(null);
    const triggerRef = useRef<HTMLButtonElement>(null);
    const selectedDate = useMemo(() => fromDateKey(value), [value]);
    const initialDate = clampDate(fromDateKey(value) ?? new Date(), min, calendarMode === 'date-of-birth' ? (max && max < toDateKey(new Date()) ? max : toDateKey(new Date())) : max);
    const [activeDateKey, setActiveDateKey] = useState(toDateKey(initialDate));
    const [visibleMonth, setVisibleMonth] = useState(() => {
        return new Date(initialDate.getFullYear(), initialDate.getMonth(), 1);
    });
    const isDateOfBirthPicker = calendarMode === 'date-of-birth';
    const today = toDateKey(new Date());
    const effectiveMax = isDateOfBirthPicker ? (max && max < today ? max : today) : max;
    const currentYear = new Date().getFullYear();
    const firstSelectableYear = Number(min?.slice(0, 4)) || currentYear - 120;
    const lastSelectableYear = Number(effectiveMax?.slice(0, 4)) || currentYear;
    const yearOptions = Array.from({ length: Math.max(1, lastSelectableYear - firstSelectableYear + 1) }, (_, index) => lastSelectableYear - index);
    const monthOptions = Array.from({ length: 12 }, (_, index) => index);
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

        function closeOutside(event: Event): void {
            const target = event.target as Element | null;
            const isCalendarControl = target?.closest('[data-radix-popper-content-wrapper]');

            if (!containerRef.current?.contains(event.target as Node) && !isCalendarControl) {
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
        document.addEventListener('focusin', closeOutside);
        document.addEventListener('keydown', closeOnEscape);

        return () => {
            document.removeEventListener('pointerdown', closeOutside);
            document.removeEventListener('focusin', closeOutside);
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, [activeDateKey, open]);

    function changeMonth(event: MouseEvent<HTMLButtonElement>, offset: number): void {
        event.preventDefault();
        showMonth(new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() + offset, 1));
    }

    function showMonth(month: Date): void {
        const focusDate = clampDate(month, min, effectiveMax);
        setVisibleMonth(new Date(focusDate.getFullYear(), focusDate.getMonth(), 1));
        setActiveDateKey(toDateKey(focusDate));
    }

    function chooseDate(date: Date): void {
        if (disabled || readOnly || !isDateWithinBounds(date, min, effectiveMax)) {
            return;
        }
        const nextValue = toDateKey(date);
        setActiveDateKey(nextValue);
        onChange?.({ target: { value: nextValue } });
        setOpen(false);
        triggerRef.current?.focus();
    }

    function moveFocus(event: ReactKeyboardEvent<HTMLButtonElement>, date: Date): void {
        const proposedDate = calendarNavigationDate(date, event.key);
        if (!proposedDate) {
            return;
        }
        const nextDate = clampDate(proposedDate, min, effectiveMax);

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

    return (
        <div className="relative min-w-0 w-full" ref={containerRef}>
            {name && <input disabled={disabled || readOnly} name={name} type="hidden" value={value} />}
            <input
                aria-hidden="true"
                disabled={disabled || readOnly}
                className="pointer-events-none absolute size-px opacity-0"
                max={effectiveMax}
                min={min}
                onChange={() => undefined}
                onInvalid={(event) => {
                    event.preventDefault();
                    setInlineCalendar(Boolean(containerRef.current?.closest('[role="dialog"]')));
                    setOpen(true);
                    triggerRef.current?.focus();
                }}
                required={required}
                tabIndex={-1}
                type="date"
                value={value}
            />
            <button
                {...accessibility}
                aria-expanded={open}
                aria-controls={calendarId}
                aria-haspopup="dialog"
                aria-label={ariaLabel ?? (id ? undefined : dateLabel)}
                className={cn('flex h-10 min-w-0 w-full items-center justify-between gap-3 rounded-lg border border-neutral-200 bg-surface px-3.5 py-2 text-left text-sm shadow-sm outline-none transition hover:border-neutral-300 focus-visible:border-neutral-500 focus-visible:ring-4 focus-visible:ring-neutral-500/10 disabled:cursor-not-allowed disabled:opacity-50', value ? 'text-neutral-950' : 'text-neutral-400', fieldControlInvalidClassName, className)}
                disabled={disabled || readOnly}
                id={id}
                onClick={() => {
                    setInlineCalendar(Boolean(containerRef.current?.closest('[role="dialog"]')));
                    if (!open) {
                        const focusDate = clampDate(selectedDate ?? new Date(), min, effectiveMax);
                        setVisibleMonth(new Date(focusDate.getFullYear(), focusDate.getMonth(), 1));
                        setActiveDateKey(toDateKey(focusDate));
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
                <div aria-label="Pilih tanggal" className={cn('z-[85] mt-2 rounded-xl border border-neutral-200 bg-surface p-3 shadow-xl shadow-inverse/15', inlineCalendar ? 'relative w-full max-w-80' : 'absolute right-0 top-full w-[min(20rem,calc(100vw-2rem))] sm:left-0 sm:right-auto')} id={calendarId} role="dialog">
                    <div className="mb-3 flex items-center justify-between gap-2">
                        <button disabled={!isMonthWithinBounds(new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() - 1, 1), min, effectiveMax)} aria-label="Bulan sebelumnya" className="inline-flex size-9 shrink-0 items-center justify-center rounded-lg text-neutral-600 hover:bg-neutral-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-500 disabled:cursor-not-allowed disabled:opacity-40" onClick={(event) => changeMonth(event, -1)} type="button"><ChevronLeft aria-hidden="true" className="size-4" /></button>
                        {isDateOfBirthPicker ? (
                            <div aria-live="polite" className="grid min-w-0 flex-1 grid-cols-[minmax(0,1fr)_6rem] gap-2">
                                <Select aria-label="Pilih bulan lahir" className="h-9 min-w-0 rounded-md px-2 text-xs" onChange={(event) => showMonth(new Date(visibleMonth.getFullYear(), Number(event.target.value), 1))} value={visibleMonth.getMonth()}>
                                    {monthOptions.map((month) => <option key={month} value={month}>{new Intl.DateTimeFormat('id-ID', { month: 'long' }).format(new Date(2000, month, 1))}</option>)}
                                </Select>
                                <Select aria-label="Pilih tahun lahir" className="h-9 rounded-md px-2 text-xs" onChange={(event) => showMonth(new Date(Number(event.target.value), visibleMonth.getMonth(), 1))} value={visibleMonth.getFullYear()}>
                                    {yearOptions.map((year) => <option key={year} value={year}>{year}</option>)}
                                </Select>
                            </div>
                        ) : <p aria-live="polite" className="text-sm font-semibold capitalize text-neutral-900">{monthLabel}</p>}
                        <button disabled={!isMonthWithinBounds(new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() + 1, 1), min, effectiveMax)} aria-label="Bulan berikutnya" className="inline-flex size-9 shrink-0 items-center justify-center rounded-lg text-neutral-600 hover:bg-neutral-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-500 disabled:cursor-not-allowed disabled:opacity-40" onClick={(event) => changeMonth(event, 1)} type="button"><ChevronRight aria-hidden="true" className="size-4" /></button>
                    </div>
                    <div className="grid grid-cols-7 gap-1">
                        {weekdays.map((day) => <span aria-hidden="true" className="py-1 text-center text-[11px] font-medium text-neutral-400" key={day}>{day}</span>)}
                        {days.map((date) => {
                            const dateKey = toDateKey(date);
                            const inMonth = date.getMonth() === visibleMonth.getMonth();
                            const outsideBounds = (min && dateKey < min) || (effectiveMax && dateKey > effectiveMax);
                            const selected = dateKey === value;

                            return (
                                <button
                                    aria-label={new Intl.DateTimeFormat('id-ID', { dateStyle: 'full' }).format(date)}
                                    aria-pressed={selected}
                                    className={cn('flex aspect-square w-full items-center justify-center rounded-lg text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-500', selected ? 'bg-neutral-700 font-semibold text-neutral-50 hover:bg-neutral-800' : dateKey === today ? 'font-semibold text-neutral-700 hover:bg-neutral-50' : 'text-neutral-700 hover:bg-neutral-100', !inMonth && !selected && 'text-neutral-300', outsideBounds && 'cursor-not-allowed opacity-30')}
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
                    <div className="mt-3 flex items-center justify-between border-t border-neutral-100 pt-2">
                        {!required && value ? <button className="rounded px-2 py-1 text-xs font-medium text-neutral-600 hover:bg-neutral-100" onClick={() => { onChange?.({ target: { value: '' } }); setOpen(false); triggerRef.current?.focus(); }} type="button">Hapus tanggal</button> : <span />}
                        <button className="rounded px-2 py-1 text-xs font-medium text-neutral-700 hover:bg-neutral-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-500 disabled:cursor-not-allowed disabled:opacity-40" disabled={!isDateWithinBounds(new Date(), min, effectiveMax)} onClick={() => chooseDate(new Date())} type="button">Hari ini</button>
                    </div>
                </div>
            )}
        </div>
    );
}

export function DateOfBirthPicker(props: Omit<Parameters<typeof DatePicker>[0], 'calendarMode'>) {
    return <DatePicker {...props} calendarMode="date-of-birth" />;
}
