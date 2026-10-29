export const normalTriageAnswers = {
    nyeri_dada: 'tidak',
    kejang: 'tidak',
    nadi_teraba: 'teraba',
    pola_pernapasan: 'normal',
    kesadaran: 'sadar',
    kondisi_psikiatri: 'normal',
    risiko_jatuh_visual: 'rendah',
} as const;

export type TriageAnswerField = keyof typeof normalTriageAnswers;
type TriageAnswers = Record<TriageAnswerField, string>;

const entries = Object.entries(normalTriageAnswers) as [TriageAnswerField, string][];

export function hasAbnormalTriageAnswer(answers: TriageAnswers): boolean {
    return entries.some(([field, normal]) => answers[field] !== '' && answers[field] !== normal);
}

export function allTriageAnswersNormal(answers: TriageAnswers): boolean {
    return entries.every(([field, normal]) => answers[field] === normal);
}

export function quickNormalTriagePatch(checked: boolean): TriageAnswers {
    return Object.fromEntries(entries.map(([field, normal]) => [field, checked ? normal : ''])) as TriageAnswers;
}
