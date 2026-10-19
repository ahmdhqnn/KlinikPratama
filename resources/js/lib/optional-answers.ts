interface OptionalAnswers {
    notRecorded: string;
}

export const optionalAnswers: OptionalAnswers = {
    notRecorded: 'Belum dicatat',
};

export function displayOptionalValue(value: string | number | null | undefined, fallback = optionalAnswers.notRecorded): string {
    return value === null || value === undefined || String(value).trim() === '' ? fallback : String(value);
}
