const DRUG_ALLERGY_FAMILIES: string[][] = [
    ['penisilin', 'penicillin', 'amoxicillin', 'amoksisilin', 'ampicillin', 'ampisilin', 'co-amoxiclav', 'amoxiclav', 'cefadroxil', 'sefadroksil'],
    ['sefalosporin', 'cephalosporin', 'cefadroxil', 'sefadroksil', 'cefixime', 'sefiksim', 'ceftriaxone', 'seftriakson'],
    ['nsaid', 'oains', 'ibuprofen', 'asam mefenamat', 'mefenamic', 'diklofenak', 'diclofenac', 'ketorolac', 'ketorolak', 'meloxicam', 'meloksikam', 'piroxicam', 'piroksikam', 'aspirin', 'asetosal', 'antalgin', 'metamizole', 'metampiron'],
    ['sulfa', 'sulfonamid', 'sulfonamide', 'cotrimoxazole', 'kotrimoksazol', 'sulfametoksazol', 'sulfamethoxazole', 'trimethoprim'],
    ['makrolida', 'macrolide', 'azithromycin', 'azitromisin', 'erythromycin', 'eritromisin', 'clarithromycin', 'klaritromisin'],
    ['kuinolon', 'quinolone', 'ciprofloxacin', 'siprofloksasin', 'levofloxacin', 'levofloksasin', 'ofloxacin'],
    ['tetrasiklin', 'tetracycline', 'doxycycline', 'doksisiklin'],
    ['paracetamol', 'parasetamol', 'acetaminophen', 'asetaminofen'],
    ['anestesi lokal', 'lidocaine', 'lidokain', 'articaine', 'artikain', 'mepivacaine', 'mepivakain', 'pehacain'],
];

export interface VitalsTriageEvaluation {
    emergency: boolean;
    priority: boolean;
    dentalHypertensionAlert: boolean;
    alerts: string[];
}

export function evaluateVitalsTriage(
    data: {
        td_sistole?: string | number | null;
        td_diastole?: string | number | null;
        nadi?: string | number | null;
        suhu?: string | number | null;
        respirasi?: string | number | null;
        spo2?: string | number | null;
        skala_nyeri?: string | number | null;
    },
    isDental: boolean,
): VitalsTriageEvaluation {
    const parseNum = (val: unknown): number | null => {
        if (val === '' || val === null || val === undefined) return null;
        const n = Number(val);
        return Number.isFinite(n) ? n : null;
    };

    const systolic = parseNum(data.td_sistole);
    const diastolic = parseNum(data.td_diastole);
    const pulse = parseNum(data.nadi);
    const temp = parseNum(data.suhu);
    const resp = parseNum(data.respirasi);
    const spo2 = parseNum(data.spo2);
    const pain = parseNum(data.skala_nyeri);

    let emergency = false;
    let priority = false;
    let dentalHypertensionAlert = false;
    const alerts: string[] = [];

    if (systolic !== null && (systolic >= 220 || systolic < 80)) {
        emergency = true;
        alerts.push(`Tekanan sistolik kritis (${systolic} mmHg)`);
    } else if (systolic !== null && (systolic >= 180 || systolic < 90)) {
        priority = true;
        alerts.push(`Tekanan sistolik perlu evaluasi segera (${systolic} mmHg)`);
    }

    if (diastolic !== null && diastolic >= 130) {
        emergency = true;
        alerts.push(`Tekanan diastolik kritis (${diastolic} mmHg)`);
    } else if (diastolic !== null && diastolic >= 110) {
        priority = true;
        alerts.push(`Tekanan diastolik perlu evaluasi segera (${diastolic} mmHg)`);
    }

    if (isDental && ((systolic !== null && systolic >= 180) || (diastolic !== null && diastolic >= 110))) {
        dentalHypertensionAlert = true;
        priority = true;
        alerts.push('Hipertensi >= 180/110 mmHg: tunda tindakan gigi invasif/ekstraksi & hindari anestesi lokal ber-adrenalin sebelum stabilisasi medis');
    }

    if (spo2 !== null && spo2 < 90) {
        emergency = true;
        alerts.push(`Hipoksemia berat (SpO₂ ${spo2}%)`);
    } else if (spo2 !== null && spo2 <= 93) {
        priority = true;
        alerts.push(`Saturasi oksigen menurun (SpO₂ ${spo2}%)`);
    }

    if (pulse !== null && (pulse >= 140 || pulse < 40)) {
        emergency = true;
        alerts.push(`Denyut nadi kritis (${pulse} kali/menit)`);
    } else if (pulse !== null && (pulse >= 115 || pulse <= 49)) {
        priority = true;
        alerts.push(`Denyut nadi abnormal (${pulse} kali/menit)`);
    }

    if (resp !== null && (resp >= 30 || resp < 8)) {
        emergency = true;
        alerts.push(`Frekuensi napas kritis (${resp} kali/menit)`);
    } else if (resp !== null && (resp >= 24 || resp <= 10)) {
        priority = true;
        alerts.push(`Frekuensi napas abnormal (${resp} kali/menit)`);
    }

    if (temp !== null && (temp >= 40.0 || temp < 35.0)) {
        emergency = true;
        alerts.push(`Suhu tubuh kritis (${temp} °C)`);
    } else if (temp !== null && temp >= 38.5) {
        priority = true;
        alerts.push(`Demam tinggi (${temp} °C)`);
    }

    if (pain !== null && pain >= 7) {
        priority = true;
        alerts.push(`Nyeri berat (skala ${pain}/10)`);
    }

    return { emergency, priority, dentalHypertensionAlert, alerts };
}

export function classifyAsiaPacificBmi(bmi: number | null, ageYears: number | null = null): string | null {
    if (bmi === null || !Number.isFinite(bmi) || bmi <= 0) return null;
    if (ageYears !== null && ageYears < 18) return 'IMT anak/remaja (rujuk kurva IMT/U Kemenkes)';
    if (bmi < 17.0) return 'Sangat kurus (Kekurangan BB berat)';
    if (bmi < 18.5) return 'Kurus (Kekurangan BB ringan)';
    if (bmi <= 22.9) return 'Normal (Ideal Asia-Pasifik)';
    if (bmi <= 24.9) return 'Berat badan lebih (Berisiko)';
    if (bmi <= 29.9) return 'Obesitas tingkat I';
    return 'Obesitas tingkat II';
}

function isNegativeAllergy(text: string | null | undefined): boolean {
    if (!text || !text.trim()) return true;
    return /^(tidak ada|pasien menyangkal|menyangkal|belum diketahui|belum dapat|tidak diketahui|none|nkda|nihil|-)\b/iu.test(text.trim());
}

export function detectClientAllergyMatch(
    allergyText: string | null | undefined,
    medicine: { name: string; ingredients?: string | null } | null | undefined,
    externalDrugName?: string,
): string | null {
    const corpus = [medicine?.name, medicine?.ingredients, externalDrugName].filter(Boolean).join(' ').toLowerCase().trim();
    if (!corpus || isNegativeAllergy(allergyText)) return null;

    const chunks = String(allergyText)
        .split(/[,;\n/]+/u)
        .map((chunk) => chunk.replace(/\b(alergi|obat|riwayat|terhadap|reaksi|ruam|gatal|bengkak|sesak)\b/giu, ' ').replace(/\s+/g, ' ').trim())
        .filter((chunk) => chunk.length >= 4 && !isNegativeAllergy(chunk));

    for (const term of chunks) {
        const lower = term.toLowerCase();
        if (corpus.includes(lower)) return term;
        for (const token of lower.split(/[\s()-]+/u)) {
            if (token.length >= 5 && corpus.includes(token)) return term;
        }
        for (const family of DRUG_ALLERGY_FAMILIES) {
            if (family.some((member) => lower.includes(member)) && family.some((member) => corpus.includes(member))) {
                return term;
            }
        }
    }

    return null;
}

function ingredientTokens(ingredients: string | null | undefined): string[] {
    if (!ingredients || !ingredients.trim()) return [];
    return ingredients
        .toLowerCase()
        .split(/[,;+/\n]+/u)
        .map((part) => part.replace(/\b\d+(?:[.,]\d+)?\s*(?:mg|ml|mcg|gr|g|iu|%)\b/giu, '').replace(/\s+/g, ' ').trim())
        .filter((part) => part.length >= 4);
}

export function detectClientDuplicateIngredient(
    medicine: { id: number; name: string; ingredients?: string | null } | null | undefined,
    existingItems: Array<{ medicineId?: number | null; name: string; ingredients?: string | null }>,
): string | null {
    if (!medicine) return null;
    const targetTokens = ingredientTokens(medicine.ingredients);
    for (const item of existingItems) {
        if (item.medicineId && item.medicineId === medicine.id) {
            return item.name;
        }
        if (targetTokens.length > 0) {
            const otherTokens = ingredientTokens(item.ingredients);
            if (targetTokens.some((token) => otherTokens.includes(token))) {
                return item.name;
            }
        }
    }
    return null;
}
