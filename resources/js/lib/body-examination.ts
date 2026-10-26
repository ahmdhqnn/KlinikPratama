import { normalizeClinicalAnswer } from '@/lib/clinical-answer';

export const physicalExamSystems = ['Kepala dan leher', 'Thorax', 'Abdomen', 'Ekstremitas', 'Neurologis', 'Kulit', 'Lainnya'] as const;
export type BodyView = 'front' | 'back';
export interface BodyLocation { id: string; label: string; category: string; side?: string; x: number; y: number; width: number; height: number; }
export interface BodyFindingDraft { locationId: string; locationLabel: string; category: string; view: string; side: string; detail: string; method: string; methodDetail?: string; symptom: string; finding: string; pain: string; }
export const emptyBodyFinding: BodyFindingDraft = { locationId: '', locationLabel: '', category: 'Thorax', view: '', side: '', detail: '', method: '', symptom: '', finding: '', pain: '' };
export function hasBodyFindingDraft(draft: BodyFindingDraft): boolean {
    return [draft.locationId === 'other' ? draft.locationLabel : '', draft.detail, draft.method, draft.methodDetail ?? '', draft.symptom, draft.finding, draft.pain].some((value) => value.trim() !== '');
}
export function appendBodyFinding(systems: Record<string, string>, draft: BodyFindingDraft): { systems: Record<string, string>; error?: string } {
    if (!draft.locationLabel || !physicalExamSystems.some((system) => system === draft.category)) return { systems, error: 'Pilih lokasi dan kategori pemeriksaan.' };
    if (!normalizeClinicalAnswer(draft.finding)) return { systems, error: 'Isi temuan pemeriksaan yang benar-benar dinilai.' };
    if (draft.pain !== '' && (!/^([0-9]|10)$/.test(draft.pain))) return { systems, error: 'Skala nyeri harus berupa angka bulat 0 sampai 10.' };
    const location = [draft.locationLabel, draft.view, draft.side && !draft.locationLabel.toLowerCase().includes(draft.side.toLowerCase()) ? draft.side : ''].filter(Boolean).join('; ');
    const note = ['[' + location + ']', draft.detail.trim() ? 'Lokasi rinci: ' + draft.detail.trim() + '.' : '', draft.method ? 'Metode: ' + draft.method + '.' : '', draft.symptom.trim() ? 'Keluhan lokal: ' + draft.symptom.trim() + '.' : '', draft.pain !== '' ? 'Skala nyeri: ' + draft.pain + '/10.' : '', 'Temuan: ' + draft.finding.trim()].filter(Boolean).join(' ');
    const previous = normalizeClinicalAnswer(systems[draft.category]) ?? '';
    const combined = previous ? previous + '\n- ' + note : '- ' + note;
    if (combined.length > 3000) return { systems, error: 'Catatan sistem ini melebihi 3.000 karakter. Ringkas catatan sebelum menambahkan temuan.' };
    return { systems: { ...systems, [draft.category]: combined } };
}

export function bodyLocations(view: BodyView): BodyLocation[] {
    const regions: BodyLocation[] = [
        { id: view + '-head', label: view === 'front' ? 'Kepala dan wajah' : 'Kepala belakang', category: 'Kepala dan leher', x: 169, y: 28, width: 62, height: 88 },
        { id: view + '-neck', label: view === 'front' ? 'Leher depan' : 'Leher belakang', category: 'Kepala dan leher', x: 180, y: 116, width: 40, height: 29 },
    ];
    const pairs = [
        ['shoulder', 'Bahu', 'Ekstremitas', 118, 144, 38, 42],
        ['upper-arm', 'Lengan atas', 'Ekstremitas', 105, 186, 38, 64],
        ['elbow', 'Siku', 'Ekstremitas', 97, 250, 33, 32],
        ['forearm', 'Lengan bawah', 'Ekstremitas', 81, 282, 36, 70],
        ['wrist', 'Pergelangan tangan', 'Ekstremitas', 72, 352, 30, 22],
        ['hand', view === 'front' ? 'Telapak tangan' : 'Punggung tangan', 'Ekstremitas', 50, 374, 45, 56],
        ['chest', view === 'front' ? 'Dada' : 'Punggung atas', 'Thorax', 150, 154, 50, 80],
        ['abdomen', view === 'front' ? 'Perut' : 'Pinggang', 'Abdomen', 154, 234, 46, 90],
        ['pelvis', view === 'front' ? 'Panggul / lipat paha' : 'Bokong', 'Ekstremitas', 143, 324, 57, 63],
        ['thigh', 'Paha', 'Ekstremitas', 143, 387, 51, 93],
        ['knee', view === 'front' ? 'Lutut' : 'Lipat lutut', 'Ekstremitas', 143, 480, 42, 35],
        ['lower-leg', view === 'front' ? 'Tungkai bawah' : 'Betis', 'Ekstremitas', 141, 515, 43, 133],
        ['ankle', 'Pergelangan kaki', 'Ekstremitas', 148, 648, 32, 30],
        ['foot', 'Kaki', 'Ekstremitas', 136, 678, 49, 40],
    ] as const;
    for (const [id, label, category, x, y, width, height] of pairs) {
        for (const side of ['kanan', 'kiri']) {
            const onLeft = view === 'front' ? side === 'kanan' : side === 'kiri';
            regions.push({ id: view + '-' + id + '-' + side, label: label + ' ' + side, side, category, x: onLeft ? x : 400 - x - width, y, width, height });
        }
    }
    if (view === 'back') regions.push({ id: 'back-spine', label: 'Tulang belakang', category: 'Neurologis', x: 191, y: 145, width: 18, height: 179 });
    return regions;
}

export const organLocations: (BodyLocation & { path: string; color: string })[] = [
    { id: 'brain', label: 'Otak dan kepala', category: 'Kepala dan leher', x: 200, y: 63, width: 0, height: 0, color: '#a78bfa', path: 'M178 59 C175 42 187 36 199 40 C214 33 226 47 222 62 C229 76 213 82 201 77 C186 85 173 74 178 59Z M200 41 L200 76 M185 49 Q196 45 195 57 M207 48 Q220 49 214 60 M182 65 Q194 60 194 72 M207 64 Q218 66 211 73' },
    { id: 'eyes', label: 'Mata', category: 'Kepala dan leher', x: 200, y: 87, width: 0, height: 0, color: '#7dd3fc', path: 'M181 86 Q188 78 195 86 Q188 93 181 86Z M205 86 Q212 78 219 86 Q212 93 205 86Z' },
    { id: 'ent_throat', label: 'THT dan tenggorokan', category: 'Kepala dan leher', x: 200, y: 131, width: 0, height: 0, color: '#7dd3fc', path: 'M195 117 Q200 113 205 117 L204 161 L217 174 L213 179 L200 167 L187 179 L183 174 L196 161Z' },
    { id: 'lungs', label: 'Paru-paru dan bronkus', category: 'Thorax', x: 200, y: 192, width: 0, height: 0, color: '#7dd3fc', path: 'M192 156 C181 154 160 175 158 195 C153 208 158 225 166 228 L191 221Z M208 156 C220 156 241 177 242 196 C247 213 241 226 234 228 L222 222 C224 206 207 204 208 190Z' },
    { id: 'heart', label: 'Jantung', category: 'Thorax', x: 211, y: 209, width: 0, height: 0, color: '#fb7185', path: 'M202 191 C205 182 214 184 216 191 C224 192 227 200 225 209 L215 232 C202 224 193 214 195 203 C195 197 197 193 202 191Z' },
    { id: 'liver', label: 'Hati dan kandung empedu', category: 'Abdomen', x: 181, y: 246, width: 0, height: 0, color: '#c08457', path: 'M159 239 C175 228 198 231 216 237 C208 249 190 252 179 265 C166 266 156 254 159 239Z' },
    { id: 'stomach', label: 'Lambung', category: 'Abdomen', x: 220, y: 253, width: 0, height: 0, color: '#fda4af', path: 'M212 236 L216 245 C222 235 237 238 240 251 C245 267 229 280 215 270 L203 263 L208 257 C220 265 224 253 217 250Z' },
    { id: 'kidneys', label: 'Ginjal', category: 'Abdomen', x: 200, y: 280, width: 0, height: 0, color: '#e879a0', path: 'M171 270 C158 268 155 291 165 298 C174 303 181 290 174 285 C168 282 181 276 171 270Z M229 265 C242 263 245 286 235 293 C226 298 219 285 226 280 C232 277 219 271 229 265Z' },
    { id: 'pancreas', label: 'Pankreas', category: 'Abdomen', x: 204, y: 277, width: 0, height: 0, color: '#facc15', path: 'M178 275 Q195 267 221 272 L231 277 Q202 286 182 283Z' },
    { id: 'intestines', label: 'Usus', category: 'Abdomen', x: 200, y: 311, width: 0, height: 0, color: '#f0ab89', path: 'M173 326 L171 292 Q172 287 179 287 L223 287 Q230 288 229 295 L226 326 Q223 335 207 335 L204 329 Q218 330 219 321 L221 296 L180 296 L182 326Z M185 301 Q200 293 215 302 L215 323 Q202 331 186 323Z M188 306 Q200 302 211 307 M188 315 Q200 311 211 316' },
    { id: 'appendix', label: 'Apendiks', category: 'Abdomen', x: 175, y: 333, width: 0, height: 0, color: '#fdba74', path: 'M173 323 Q166 334 173 340 Q182 344 183 335 L178 332 L178 324Z' },
    { id: 'bladder', label: 'Kandung kemih', category: 'Abdomen', x: 200, y: 350, width: 0, height: 0, color: '#6ee7b7', path: 'M187 345 Q200 336 213 345 Q215 358 203 365 L203 372 L197 372 L197 365 Q185 359 187 345Z' },
];
