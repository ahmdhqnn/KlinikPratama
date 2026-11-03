export interface DentalDiagnosisFinding {
    toothFdi: string;
    surface: string;
    findingCode: string;
    details?: Record<string, string> | null;
    detailSummary?: string | null;
    notes?: string | null;
}

export interface DentalDiagnosisCandidate { label: string; code: string; exact: boolean; findingCode?: string; }
export interface DentalDiagnosisGroup {
    toothFdi: string;
    surfaces: string[];
    findings: DentalDiagnosisFinding[];
    candidates: DentalDiagnosisCandidate[];
    warnings: string[];
}

export interface RecordedDentalDiagnosis { sourceFindingCode?: string | null; code: string | null; codeSystem?: string | null; name: string; toothFdi?: string | null; surfaces?: string[]; }

export const wholeToothFindingCodes = ['missing', 'unerupted', 'partial_erupted', 'impacted', 'retained_primary', 'malposition', 'anomaly', 'non_vital', 'root_canal', 'crown', 'bridge_pontic', 'bridge_abutment', 'implant', 'removable_denture'];

export function candidateFindings(group: DentalDiagnosisGroup, candidate: DentalDiagnosisCandidate): DentalDiagnosisFinding[] {
    return group.findings.filter(finding => !candidate.findingCode || diagnosisMatchesFindings({ toothFdi: group.toothFdi, sourceFindingCode: candidate.findingCode }, [finding]));
}

export function isDentalDiagnosisRecorded(group: DentalDiagnosisGroup, candidate: DentalDiagnosisCandidate, diagnoses: RecordedDentalDiagnosis[]): boolean {
    const surfaces = candidate.findingCode && !wholeToothFindingCodes.includes(candidate.findingCode) ? candidateFindings(group, candidate).map(finding => finding.surface) : ['W'];
    return diagnoses.some(diagnosis => diagnosis.toothFdi === group.toothFdi
        && (!candidate.exact || diagnosis.code === candidate.code || (!diagnosis.code && diagnosis.sourceFindingCode === candidate.findingCode))
        && Boolean(diagnosis.sourceFindingCode ? diagnosis.sourceFindingCode === (candidate.findingCode ?? (candidate.label.includes('apikal') ? 'periapical' : 'pulpal')) : diagnosis.code && (candidate.exact ? diagnosis.code === candidate.code : candidate.label.includes('apikal') ? /^K04\.[4-9]/.test(diagnosis.code) : candidate.label === 'Diagnosis pulpa' ? /^K04\.[0-3]/.test(diagnosis.code) : candidate.findingCode === 'fractured_crown' ? diagnosis.code.startsWith('S02.5') : false))
        && surfaces.every(surface => (diagnosis.surfaces ?? []).includes(surface)));
}

export function dentalSurfaceLabel(surfaces: string[]): string {
    const text = ['M', 'O', 'D', 'V', 'L', 'W'].filter(surface => surfaces.includes(surface)).join('');
    return text === 'OD' ? 'DO' : text === 'W' ? 'Seluruh gigi' : text;
}

export function dentalFindingDetails(findingCode: string, previous: Record<string, string>): Record<string, string> {
    const defaults: Record<string, Record<string, string>> = {
        partial_erupted: { eruption: 'partial' }, impacted: { eruption: 'impacted' }, retained_primary: { eruption: 'retained_primary' },
        non_vital: { endodontic: 'non_vital' }, root_canal: { endodontic: 'treated' }, crown: { prosthesis: 'crown' },
        bridge_pontic: { prosthesis: 'pontic' }, bridge_abutment: { prosthesis: 'abutment' }, implant: { prosthesis: 'implant' },
        temporary_restoration: { restoration_state: 'temporary' }, sealant: { restoration_state: 'sealant' },
    };
    const details = { ...previous, ...defaults[findingCode] };
    if (findingCode !== 'caries') for (const key of ['icdas', 'activity', 'site', 'depth', 'root_severity', 'black_class']) delete details[key];
    if (['implant', 'bridge_pontic', 'removable_denture', 'missing', 'unerupted'].includes(findingCode)) {
        for (const key of ['pulpal', 'periapical', 'endodontic', 'vitality', 'percussion', 'palpation', 'mobility']) delete details[key];
    }
    return details;
}

export function dentalDiagnosisDescription(diagnosis: { toothFdi?: string | null; note?: string | null }): string {
    if (!diagnosis.toothFdi) return diagnosis.note ?? '';
    return (diagnosis.note ?? '').replace(new RegExp(`^Gigi ${diagnosis.toothFdi}(?: \\([^)]*\\))?(?:\\s*[—.:-]\\s*)?`), '').trim();
}

export function diagnosisMatchesFindings(diagnosis: { sourceFindingCode?: string | null; toothFdi?: string | null; surfaces?: string[] }, findings: DentalDiagnosisFinding[]): boolean {
    if (!diagnosis.toothFdi) return true;
    const source = findings.filter(finding => finding.toothFdi === diagnosis.toothFdi);
    const contexts: Record<string, [string, string[]]> = {
        partial_erupted: ['eruption', ['partial']], impacted: ['eruption', ['impacted']], retained_primary: ['eruption', ['retained_primary']],
        non_vital: ['endodontic', ['non_vital']], root_canal: ['endodontic', ['treated', 'in_progress']], crown: ['prosthesis', ['crown']],
        bridge_pontic: ['prosthesis', ['pontic']], bridge_abutment: ['prosthesis', ['abutment']], implant: ['prosthesis', ['implant']],
        removable_denture: ['prosthesis', ['partial_denture', 'complete_denture']], sealant: ['restoration_state', ['sealant']], temporary_restoration: ['restoration_state', ['temporary']],
    };
    const matches = !diagnosis.sourceFindingCode || source.some(finding => {
        const code = diagnosis.sourceFindingCode!;
        if (finding.findingCode === code) return true;
        if (['pulpal', 'periapical'].includes(code)) return Boolean(finding.details?.[code]);
        if (code === 'malposition') return Boolean(finding.details?.position);
        if (code === 'anomaly') return Boolean(finding.details?.morphology);
        const context = contexts[code];
        return Boolean(context && context[1].includes(finding.details?.[context[0]] ?? ''));
    });
    return matches && source.length > 0 && (diagnosis.surfaces ?? []).every(surface => surface === 'W' || source.some(finding => finding.surface === surface));
}

export function groupDentalDiagnoses(findings: DentalDiagnosisFinding[]): DentalDiagnosisGroup[] {
    const teeth = new Map<string, DentalDiagnosisFinding[]>();
    for (const finding of findings) {
        if (['sound', 'missing', 'unerupted'].includes(finding.findingCode)) continue;
        teeth.set(finding.toothFdi, [...(teeth.get(finding.toothFdi) ?? []), finding]);
    }
    return [...teeth].sort(([a], [b]) => Number(a) - Number(b)).map(([toothFdi, records]) => {
        const candidates: DentalDiagnosisCandidate[] = [];
        const caries = records.filter(finding => finding.findingCode === 'caries');
        if (caries.length) {
            const depths = new Set(caries.map(finding => finding.details?.depth));
            const uncertain = caries.some(finding => finding.details?.site === 'root') || [...depths].some(depth => !depth || depth === 'uncertain') || (depths.has('enamel') && depths.size > 1);
            candidates.push({ label: 'Karies', findingCode: 'caries', code: uncertain ? 'K02' : depths.size === 1 && depths.has('enamel') ? 'K02.0' : 'K02.1', exact: !uncertain });
        }
        if (records.some(finding => finding.findingCode === 'fractured_crown')) candidates.push({ label: 'Fraktur gigi', findingCode: 'fractured_crown', code: 'fracture tooth', exact: false });
        if (records.some(finding => finding.findingCode === 'root_remnant')) candidates.push({ label: 'Sisa akar', findingCode: 'root_remnant', code: 'K08.3', exact: true });
        const searches: Record<string, [string, string]> = {
            partial_erupted: ['Erupsi sebagian', 'eruption'], impacted: ['Impaksi', 'impacted teeth'], retained_primary: ['Persistensi sulung', 'retained deciduous'],
            malposition: ['Kelainan posisi', 'tooth position'], anomaly: ['Anomali gigi', 'tooth size form'], non_vital: ['Non-vital', 'K04'],
            root_canal: ['Riwayat PSA', 'endodontic'], crown: ['Mahkota tiruan', 'dental prosthetic'], bridge_pontic: ['Pontik bridge', 'dental prosthetic'],
            bridge_abutment: ['Penyangga bridge', 'dental prosthetic'], implant: ['Implan', 'dental implant'], removable_denture: ['Gigi tiruan lepasan', 'dental prosthetic'],
            sealant: ['Sealant', 'dental examination'], temporary_restoration: ['Tambalan sementara', 'dental restoration'], restored: ['Restorasi', 'dental restoration'],
        };
        for (const findingCode of new Set([...records.map(record => record.findingCode), ...Object.keys(searches).filter(code => records.some(finding => diagnosisMatchesFindings({ toothFdi, sourceFindingCode: code }, [finding])))])) {
            const search = searches[findingCode];
            if (search) candidates.push({ label: search[0], code: search[1], exact: false, findingCode });
        }
        const pulpal = [...new Set(records.map(finding => finding.details?.pulpal).filter(value => value && value !== 'normal' && value !== 'uncertain'))];
        const conflictingPulpalFindings = new Set(records.map(finding => finding.details?.pulpal).filter(value => value && value !== 'uncertain')).size > 1;
        if (pulpal.length) candidates.push({ label: 'Diagnosis pulpa', code: conflictingPulpalFindings ? 'K04' : pulpal.length === 1 && pulpal[0] === 'necrosis' ? 'K04.1' : pulpal.length === 1 && ['reversible', 'irreversible_symptomatic', 'irreversible_asymptomatic'].includes(pulpal[0]!) ? 'K04.0' : 'K04', exact: !conflictingPulpalFindings && pulpal.length === 1 && ['necrosis', 'reversible', 'irreversible_symptomatic', 'irreversible_asymptomatic'].includes(pulpal[0]!) });
        if (records.some(finding => finding.details?.periapical && !['normal', 'uncertain'].includes(finding.details.periapical))) candidates.push({ label: 'Tinjau diagnosis apikal', code: 'K04', exact: false });
        const warnings: string[] = [];
        if (conflictingPulpalFindings) warnings.push('Pilihan diagnosis pulpa berbeda antarpermukaan. Tinjau kembali temuan pada gigi ini.');
        if (records.some(finding => finding.details?.pulpal === 'reversible' && finding.details?.vitality === 'negative')) warnings.push('Respons termal tidak ada, sedangkan pulpa dicatat reversibel. Tinjau bersama hasil pemeriksaan lain sebelum menetapkan diagnosis.');
        return { toothFdi, surfaces: [...new Set(records.map(finding => finding.surface))], findings: records, candidates, warnings };
    }).filter(group => group.candidates.length > 0);
}

export function dentalTreatmentSearches(diagnoses: Array<{ code: string | null; name: string; toothFdi?: string | null }>, selectedTeeth: string[]) {
    const relevant = diagnoses.filter(diagnosis => selectedTeeth.length === 0 || Boolean(diagnosis.toothFdi && selectedTeeth.includes(diagnosis.toothFdi)));
    const suggestions = relevant.map(diagnosis => {
        const code = diagnosis.code ?? '';
        const name = diagnosis.name.toLowerCase();
        if (code.startsWith('K04') || /pulpa|pulpit|apikal|periapical/.test(name)) return { key: 'endodontik', title: 'Pulpa dan jaringan apikal', guidance: 'Tentukan penanganan setelah meninjau diagnosis pulpa, jaringan apikal, pemeriksaan penunjang, dan prognosis gigi.', query: 'root canal', label: 'Cari prosedur endodontik' };
        if (code.startsWith('K02') || /caries|karies/.test(name)) return { key: 'karies', title: 'Karies', guidance: 'Pilih penanganan sesuai kedalaman lesi, kondisi pulpa, dan rencana perawatan. Restorasi bukan satu-satunya pilihan untuk semua lesi.', query: '23.2', label: 'Cari prosedur restorasi' };
        if (code.startsWith('K05') || /gingivit|periodont/.test(name)) return { key: 'periodontal', title: 'Gingiva dan periodontal', guidance: 'Tinjau kondisi jaringan periodontal dan kebutuhan perawatan sebelum memilih prosedur.', query: 'periodontal', label: 'Cari prosedur periodontal' };
        if (code.startsWith('S02.5') || /fractur|fraktur/.test(name)) return { key: 'fraktur', title: 'Fraktur gigi', guidance: 'Tinjau jenis fraktur, kondisi pulpa, dan kemungkinan mempertahankan gigi. Pilih prosedur sesuai hasil evaluasi dokter.', query: 'tooth', label: 'Cari prosedur gigi' };
        return { key: 'lain', title: 'Kondisi gigi atau mulut lainnya', guidance: 'Gunakan hasil pemeriksaan dan diagnosis sebagai dasar pemilihan tindakan. Bila belum memerlukan prosedur, catat perawatan, edukasi, evaluasi, atau rujukan yang sesuai.', query: 'oral', label: 'Cari prosedur mulut' };
    });
    return [...new Map(suggestions.map(suggestion => [suggestion.key, suggestion])).values()];
}
