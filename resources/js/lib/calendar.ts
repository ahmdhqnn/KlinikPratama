export function toDateKey(date: Date): string {
    return [String(date.getFullYear()).padStart(4, '0'), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
}

export function fromDateKey(value?: string): Date | null {
    if (!value || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return null;
    }
    const [year, month, day] = value.split('-').map(Number);
    const date = new Date(0);
    date.setHours(0, 0, 0, 0);
    date.setFullYear(year, month - 1, day);

    return year > 0 && date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day ? date : null;
}

export function clampDate(date: Date, min?: string, max?: string): Date {
    const lower = fromDateKey(min);
    const upper = fromDateKey(max);
    if (lower && date < lower) {
        return lower;
    }
    if (upper && date > upper) {
        return upper;
    }

    return date;
}

export function isDateWithinBounds(date: Date, min?: string, max?: string): boolean {
    const key = toDateKey(date);

    return (!min || key >= min) && (!max || key <= max);
}

export function calendarNavigationDate(date: Date, key: string): Date | null {
    const offsets: Record<string, number> = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
    const next = new Date(date);
    if (key in offsets) {
        next.setDate(date.getDate() + offsets[key]);
    } else if (key === 'Home' || key === 'End') {
        const mondayOffset = (date.getDay() + 6) % 7;
        next.setDate(date.getDate() + (key === 'Home' ? -mondayOffset : 6 - mondayOffset));
    } else if (key === 'PageUp' || key === 'PageDown') {
        const offset = key === 'PageUp' ? -1 : 1;
        const lastDay = new Date(date.getFullYear(), date.getMonth() + offset + 1, 0).getDate();
        next.setFullYear(date.getFullYear(), date.getMonth() + offset, Math.min(date.getDate(), lastDay));
    } else {
        return null;
    }

    return next;
}

export function isMonthWithinBounds(month: Date, min?: string, max?: string): boolean {
    const first = toDateKey(new Date(month.getFullYear(), month.getMonth(), 1));
    const last = toDateKey(new Date(month.getFullYear(), month.getMonth() + 1, 0));

    return (!min || last >= min) && (!max || first <= max);
}
