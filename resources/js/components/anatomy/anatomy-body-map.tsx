import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Activity,
    Brain,
    Check,
    ChevronRight,
    Eye,
    HeartPulse,
    Plus,
    Search,
    ShieldAlert,
    Stethoscope,
    Utensils,
    Wind,
} from 'lucide-react';

export type BodySystemCode =
    | 'respiratory'
    | 'circulatory'
    | 'digestive'
    | 'nervous'
    | 'urinary'
    | 'musculoskeletal';

export interface BodyOrganMeta {
    id: string;
    name: string;
    latinName: string;
    system: BodySystemCode;
    systemName: string;
    physicalExamCategory: string;
    labelX: number;
    labelY: number;
    pointerX: number;
    pointerY: number;
    side: 'left' | 'right';
    description: string;
    commonSymptoms: string[];
    icd10Prompt: string;
    icd10Candidates: string[];
}

export interface AnatomyBodyMapProps {
    selectedOrganId?: string;
    onSelectOrgan: (organ: BodyOrganMeta) => void;
    onApplyToExam?: (organ: BodyOrganMeta, findingNote: string) => void;
    onQuickSearchIcd?: (query: string) => void;
    recordedFindings?: Record<string, string>;
    disabled?: boolean;
}

export const ORGAN_SYSTEMS: Record<BodySystemCode, {
    title: string;
    label: string;
    icon: typeof Wind;
    colorBadge: string;
    colorDarkBadge: string;
    description: string;
}> = {
    respiratory: {
        title: 'Sistem Respirasi',
        label: 'Pernapasan',
        icon: Wind,
        colorBadge: 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/60 dark:text-sky-300 dark:border-sky-800',
        colorDarkBadge: 'text-sky-400',
        description: 'Saluran napas atas/bawah, trakea, bronkus, dan parenkim paru-paru.',
    },
    circulatory: {
        title: 'Sistem Kardiovaskular',
        label: 'Sirkulasi & Jantung',
        icon: HeartPulse,
        colorBadge: 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800',
        colorDarkBadge: 'text-rose-400',
        description: 'Miopatologi, hemodinamik, pembuluh darah, dan ritme jantung.',
    },
    digestive: {
        title: 'Sistem Digestif',
        label: 'Pencernaan',
        icon: Utensils,
        colorBadge: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800',
        colorDarkBadge: 'text-amber-400',
        description: 'Gastrointestinal, hepar, gaster, pankreas, usus halus, dan kolon.',
    },
    nervous: {
        title: 'Sistem Saraf & THT',
        label: 'Saraf & Sensorik',
        icon: Brain,
        colorBadge: 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/60 dark:text-purple-300 dark:border-purple-800',
        colorDarkBadge: 'text-purple-400',
        description: 'Sistem saraf pusat, persepsi sensorik, kranium, mata, telinga, dan faring.',
    },
    urinary: {
        title: 'Sistem Urinarius',
        label: 'Perkemihan',
        icon: Activity,
        colorBadge: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800',
        colorDarkBadge: 'text-emerald-400',
        description: 'Traktus urinarius, filtrasi ginjal, ureter, dan vesika urinaria.',
    },
    musculoskeletal: {
        title: 'Muskuloskeletal & Kulit',
        label: 'Rangka & Kulit',
        icon: ShieldAlert,
        colorBadge: 'bg-neutral-100 text-neutral-700 border-neutral-300',
        colorDarkBadge: 'text-neutral-300',
        description: 'Integumen, persendian ekstremitas, kolumna vertebralis, dan jaringan lunak.',
    },
};

export const BODY_ORGANS: BodyOrganMeta[] = [
    {
        id: 'brain',
        name: 'Otak & Kepala',
        latinName: 'Cerebrum & Encephalon',
        system: 'nervous',
        systemName: 'Sistem Saraf',
        physicalExamCategory: 'Kepala dan leher',
        labelX: 52,
        labelY: 96,
        pointerX: 250,
        pointerY: 96,
        side: 'left',
        description: 'Pusat regulasi kesadaran, motorik, sensorik, dan persarafan kranial.',
        commonSymptoms: ['Sakit kepala (cefalalgia)', 'Pusing berputar (vertigo)', 'Migrain berulang', 'Penurunan konsentrasi', 'Riwayat trauma kapitis'],
        icd10Prompt: 'Sakit kepala / Nyeri kapitis',
        icd10Candidates: ['R51', 'G43.9', 'R42', 'G44.2'],
    },
    {
        id: 'eyes',
        name: 'Mata & Penglihatan',
        latinName: 'Oculus Dexter et Sinister',
        system: 'nervous',
        systemName: 'Sensorik Khusus',
        physicalExamCategory: 'Kepala dan leher',
        labelX: 468,
        labelY: 104,
        pointerX: 272,
        pointerY: 104,
        side: 'right',
        description: 'Aparatus optik, sklera, konjungtiva, dan tajam penglihatan.',
        commonSymptoms: ['Mata merah & berair', 'Gatal konjungtiva', 'Penglihatan kabur', 'Kotoran mata purulen', 'Nyeri bola mata'],
        icd10Prompt: 'Konjungtivitis / Mata merah',
        icd10Candidates: ['H10.9', 'H52.1', 'H00.0', 'H57.9'],
    },
    {
        id: 'ent_throat',
        name: 'THT & Tenggorokan',
        latinName: 'Pharynx, Larynx & Trachea Cervicalis',
        system: 'respiratory',
        systemName: 'Saluran Napas Atas',
        physicalExamCategory: 'Kepala dan leher',
        labelX: 52,
        labelY: 154,
        pointerX: 254,
        pointerY: 158,
        side: 'left',
        description: 'Faring, tonsil, hidung, telinga tengah, pita suara, dan saluran pernapasan leher.',
        commonSymptoms: ['Nyeri menelan (odinofagia)', 'Tenggorokan kering & gatal', 'Hidung tersumbat / pilek', 'Nyeri telinga / berdenging', 'Suara serak'],
        icd10Prompt: 'Faringitis / Tonsilitis / ISPA',
        icd10Candidates: ['J02.9', 'J00', 'J03.9', 'J06.9'],
    },
    {
        id: 'lungs',
        name: 'Paru-paru & Bronkus',
        latinName: 'Pulmones (Dexter & Sinister)',
        system: 'respiratory',
        systemName: 'Sistem Respirasi',
        physicalExamCategory: 'Thorax',
        labelX: 52,
        labelY: 228,
        pointerX: 215,
        pointerY: 236,
        side: 'left',
        description: 'Parenkim paru bilateral, pertukaran gas, ventilasi, dan jalan napas intratorakal.',
        commonSymptoms: ['Batuk berdahak / kering', 'Sesak napas saat aktivitas', 'Nyeri dada saat menarik napas', 'Mengi / stridor (wheezing)', 'Ronkhi basah kasar/halus'],
        icd10Prompt: 'Pneumonia / Bronkitis / Asma',
        icd10Candidates: ['J18.9', 'J20.9', 'J45.9', 'J44.9'],
    },
    {
        id: 'heart',
        name: 'Jantung & Sirkulasi',
        latinName: 'Cor & Mediastinum',
        system: 'circulatory',
        systemName: 'Sistem Kardiovaskular',
        physicalExamCategory: 'Thorax',
        labelX: 468,
        labelY: 236,
        pointerX: 278,
        pointerY: 242,
        side: 'right',
        description: 'Pompa miokard, sirkulasi sistemik, katup jantung, dan tekanan darah.',
        commonSymptoms: ['Nyeri dada retrosternal', 'Berdebar-debar (palpitasi)', 'Sesak napas berbaring (ortopnea)', 'Kelelahan ekstrem', 'Tekanan darah tidak stabil'],
        icd10Prompt: 'Hipertensi / Nyeri dada / Angina',
        icd10Candidates: ['I10', 'R07.9', 'I20.9', 'R00.2'],
    },
    {
        id: 'liver',
        name: 'Hati & Kandung Empedu',
        latinName: 'Hepar & Vesica Biliaris',
        system: 'digestive',
        systemName: 'Sistem Digestif',
        physicalExamCategory: 'Abdomen',
        labelX: 52,
        labelY: 304,
        pointerX: 226,
        pointerY: 304,
        side: 'left',
        description: 'Metabolisme hepar, detoksifikasi, sekresi empedu, dan sintesis protein.',
        commonSymptoms: ['Nyeri perut kuadran kanan atas', 'Mata / kulit kuning (ikterik)', 'Mual setelah makan berlemak', 'Kembung kronis', 'Hepatomegali / perabaan hepar'],
        icd10Prompt: 'Penyakit hepar / Hepatitis / Kolesistitis',
        icd10Candidates: ['K76.9', 'K80.8', 'K75.9', 'R16.0'],
    },
    {
        id: 'stomach',
        name: 'Lambung & Epigastrium',
        latinName: 'Gaster / Ventriculus',
        system: 'digestive',
        systemName: 'Sistem Digestif',
        physicalExamCategory: 'Abdomen',
        labelX: 468,
        labelY: 304,
        pointerX: 288,
        pointerY: 310,
        side: 'right',
        description: 'Digesti asam lambung, motilitas kimus, dan barier mukosa lambung.',
        commonSymptoms: ['Nyeri ulu hati (epigastrik)', 'Sensasi terbakar di dada (heartburn)', 'Mual & sering muntah', 'Cepat kenyang & begah', 'Nyeri perih sebelum makan'],
        icd10Prompt: 'Gastritis / Dispepsia / GERD',
        icd10Candidates: ['K29.7', 'K30', 'K21.9', 'K25.9'],
    },
    {
        id: 'pancreas',
        name: 'Pankreas & Limpa',
        latinName: 'Pancreas & Lien',
        system: 'digestive',
        systemName: 'Sistem Digestif',
        physicalExamCategory: 'Abdomen',
        labelX: 468,
        labelY: 354,
        pointerX: 270,
        pointerY: 350,
        side: 'right',
        description: 'Enzim pencernaan eksokrin, sekresi insulin/glukagon, dan filtrasi limfoid.',
        commonSymptoms: ['Nyeri perut tembus ke punggung', 'Gangguan toleransi glukosa', 'Kotoran pucat / berlemak', 'Nyeri pasca makan berat', 'Penurunan berat badan mendadak'],
        icd10Prompt: 'Pankreatitis / Diabetes Mellitus',
        icd10Candidates: ['K85.9', 'E11.9', 'K86.1', 'R73.9'],
    },
    {
        id: 'kidneys',
        name: 'Ginjal Bilateral',
        latinName: 'Renes (Dexter & Sinister)',
        system: 'urinary',
        systemName: 'Sistem Urinarius',
        physicalExamCategory: 'Abdomen',
        labelX: 52,
        labelY: 366,
        pointerX: 218,
        pointerY: 366,
        side: 'left',
        description: 'Filtrasi cairan tubuh, homeostasis elektrolit, dan tekanan darah renal.',
        commonSymptoms: ['Nyeri ketok sudut kosto-vertebra (CVA)', 'Nyeri pinggang menjalar ke depan', 'Kencing berdarah (hematuria)', 'Bengkak periorbita/wajah', 'Oliguria / urin keruh'],
        icd10Prompt: 'Nefrolitiasis / Infeksi ginjal',
        icd10Candidates: ['N20.0', 'N10', 'N12', 'N03.9'],
    },
    {
        id: 'intestines',
        name: 'Usus Halus & Usus Besar',
        latinName: 'Intestinum Tenue & Colon',
        system: 'digestive',
        systemName: 'Sistem Digestif',
        physicalExamCategory: 'Abdomen',
        labelX: 468,
        labelY: 416,
        pointerX: 262,
        pointerY: 412,
        side: 'right',
        description: 'Penyerapan nutrisi, absorpsi air, pembentukan feses, dan motilitas usus.',
        commonSymptoms: ['Diare cair frekuensi >3x', 'BAB bercampur lendir/darah', 'Konstipasi kronis >3 hari', 'Kram melilit periumbilikal', 'Bising usus meningkat (borborigmi)'],
        icd10Prompt: 'Gastroenteritis / Diare / Kolitis',
        icd10Candidates: ['A09', 'K58.9', 'K59.0', 'K52.9'],
    },
    {
        id: 'appendix',
        name: 'Apendiks (Usus Buntu)',
        latinName: 'Appendix Vermiformis',
        system: 'digestive',
        systemName: 'Sistem Digestif',
        physicalExamCategory: 'Abdomen',
        labelX: 52,
        labelY: 440,
        pointerX: 236,
        pointerY: 444,
        side: 'left',
        description: 'Jaringan limfoid ileosekal kuadran kanan bawah (titik McBurney).',
        commonSymptoms: ['Nyeri perut kanan bawah terlokalisir', 'Nyeri tekan & nyeri lepas McBurney (+)', 'Demam subfebris', 'Mual & penurunan nafsu makan', 'Tanda Rovsing / Psoas positif'],
        icd10Prompt: 'Apendisitis akut',
        icd10Candidates: ['K35.8', 'K36', 'K37'],
    },
    {
        id: 'bladder',
        name: 'Kandung Kemih & Pelvis',
        latinName: 'Vesica Urinaria & Urethra',
        system: 'urinary',
        systemName: 'Sistem Urinarius',
        physicalExamCategory: 'Abdomen',
        labelX: 468,
        labelY: 480,
        pointerX: 260,
        pointerY: 476,
        side: 'right',
        description: 'Reservoir urin, sfingter uretra, dan organ pelvis suprapubik.',
        commonSymptoms: ['Nyeri perih saat buang air kecil (disuria)', 'Anyang-anyangan & frekuensi sering', 'Rasa tidak tuntas saat kencing', 'Nyeri tekan area suprapubik', 'Urin berbau menyengat'],
        icd10Prompt: 'Infeksi Saluran Kemih (ISK) / Sistitis',
        icd10Candidates: ['N39.0', 'N30.9', 'R30.0', 'R35'],
    },
    {
        id: 'upper_limbs',
        name: 'Bahu, Lengan & Tangan',
        latinName: 'Membrum Superius',
        system: 'musculoskeletal',
        systemName: 'Muskuloskeletal',
        physicalExamCategory: 'Ekstremitas',
        labelX: 52,
        labelY: 520,
        pointerX: 160,
        pointerY: 340,
        side: 'left',
        description: 'Sendi glenohumeral, siku, karpal, falang, dan integritas motorik tangan.',
        commonSymptoms: ['Kaku sendi bahu saat diangkat', 'Kesemutan / kebas telapak tangan (CTS)', 'Bengkak sendi jari tangan', 'Nyeri pasca cedera / terkilir', 'Kelemahan menggenggam'],
        icd10Prompt: 'Frozen shoulder / Sprain / CTS',
        icd10Candidates: ['M75.0', 'G56.0', 'S63.5', 'M65.4'],
    },
    {
        id: 'spine',
        name: 'Tulang Belakang & Punggung',
        latinName: 'Columna Vertebralis',
        system: 'musculoskeletal',
        systemName: 'Muskuloskeletal',
        physicalExamCategory: 'Neurologis',
        labelX: 468,
        labelY: 536,
        pointerX: 260,
        pointerY: 320,
        side: 'right',
        description: 'Regio servikal, torakal, lumbal, diskus intervertebra, dan otot paravertebra.',
        commonSymptoms: ['Nyeri pinggang bawah (Low Back Pain)', 'Nyeri leher tegang menjalar ke bahu', 'Kaku gerak membungkuk', 'Kesemutan menjalar ke tungkai (Iskialgia)', 'Spasme otot punggung'],
        icd10Prompt: 'Low Back Pain / Myalgia / Spondylosis',
        icd10Candidates: ['M54.5', 'M79.1', 'M54.2', 'M47.9'],
    },
    {
        id: 'lower_limbs',
        name: 'Panggul, Lutut & Kaki',
        latinName: 'Membrum Inferius',
        system: 'musculoskeletal',
        systemName: 'Muskuloskeletal',
        physicalExamCategory: 'Ekstremitas',
        labelX: 52,
        labelY: 620,
        pointerX: 220,
        pointerY: 570,
        side: 'left',
        description: 'Sendi panggul, genu (lutut), maleolus (engkel), arkus pedis, dan vaskular perifer.',
        commonSymptoms: ['Nyeri sendi lutut saat menaiki tangga (OA)', 'Bengkak kemerahan jempol kaki (Asam urat / Gout)', 'Edema pada kedua tungkai bawah', 'Kram betis di malam hari', 'Terkilir / sprain engkel'],
        icd10Prompt: 'Osteoarthritis / Gout / Edema tungkai',
        icd10Candidates: ['M17.9', 'M10.9', 'S93.4', 'R60.0'],
    },
    {
        id: 'skin',
        name: 'Kulit & Jaringan Lunak',
        latinName: 'Integumentum Commune',
        system: 'musculoskeletal',
        systemName: 'Integumen',
        physicalExamCategory: 'Kulit',
        labelX: 468,
        labelY: 620,
        pointerX: 340,
        pointerY: 520,
        side: 'right',
        description: 'Epidermis, dermis, kuku, kelenjar sebasea, dan kelainan dermatologis umum.',
        commonSymptoms: ['Ruam kemerahan (eritema)', 'Rasa gatal intens (pruritus)', 'Bintil berair / lenting (vesikel)', 'Bercak bersisik / jamur', 'Luka basah / ulkus superficial'],
        icd10Prompt: 'Dermatitis / Tinea / Alergi kulit',
        icd10Candidates: ['L20.9', 'L30.9', 'B35.9', 'L50.9'],
    },
];

export function AnatomyBodyMap({
    selectedOrganId = 'lungs',
    onSelectOrgan,
    onApplyToExam,
    onQuickSearchIcd,
    recordedFindings = {},
    disabled = false,
}: AnatomyBodyMapProps) {
    const [hoveredOrganId, setHoveredOrganId] = useState<string | null>(null);
    const [activeSystemFilter, setActiveSystemFilter] = useState<BodySystemCode | null>(null);
    const [viewMode, setViewMode] = useState<'organs' | 'full_body'>('organs');

    const selectedOrgan = BODY_ORGANS.find((o) => o.id === selectedOrganId) ?? BODY_ORGANS[3];
    const hoveredOrgan = BODY_ORGANS.find((o) => o.id === hoveredOrganId);
    const activeOrgan = hoveredOrgan ?? selectedOrgan;

    function handleOrganClick(organ: BodyOrganMeta) {
        if (!disabled) {
            onSelectOrgan(organ);
        }
    }

    return (
        <div className="anatomy-body-map space-y-4">
            {/* Layout Utama 2 Kolom Seimbang: 7 Kolom Kiri Peta Anatomi Tubuh, 5 Kolom Kanan Sistem & Panduan */}
            <div className="grid gap-5 xl:grid-cols-12">
                {/* Panel Kiri: Peta Anatomi & Organ Tubuh SVG - 7 Kolom */}
                <div className="rounded-2xl border border-neutral-200/90 bg-surface p-4 shadow-xs transition-colors sm:p-5 xl:col-span-7">
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-neutral-100 pb-3">
                        <div>
                            <div className="flex items-center gap-2">
                                <h4 className="text-base font-bold tracking-tight text-neutral-900">
                                    Peta Anatomi & Lokasi Keluhan Tubuh
                                </h4>
                                <span className="rounded-md bg-neutral-100 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-neutral-700">
                                    ICD-10 Mapped
                                </span>
                            </div>
                            <p className="mt-1 text-sm leading-6 text-neutral-600">
                                Klik organ atau area tubuh untuk menautkan temuan klinis, gejala terarah, dan kode terminologi. Sisi kiri peta = sisi kanan pasien.
                            </p>
                        </div>
                        <div className="flex items-center gap-1.5">
                            <Button
                                onClick={() => setViewMode('organs')}
                                size="sm"
                                type="button"
                                variant={viewMode === 'organs' ? 'default' : 'secondary'}
                                className="h-7 text-xs"
                            >
                                Organ Toraks-Abdomen
                            </Button>
                            <Button
                                onClick={() => setViewMode('full_body')}
                                size="sm"
                                type="button"
                                variant={viewMode === 'full_body' ? 'default' : 'secondary'}
                                className="h-7 text-xs"
                            >
                                Seluruh Tubuh
                            </Button>
                        </div>
                    </div>

                    {/* SVG Diagram Anatomi Tubuh & Organ */}
                    <div className="relative mx-auto flex items-center justify-center overflow-hidden">
                        <svg
                            aria-label="Peta anatomi tubuh dan organ manusia untuk pemeriksaan fisik dokter umum"
                            className="h-auto w-full max-w-[530px] select-none"
                            role="group"
                            viewBox="0 0 520 720"
                        >
                            {/* Garis Pembagi Orientasi Medis (Sagital Midline & Bidang Torako-Abdominal) */}
                            <line className="pointer-line" strokeDasharray="4 4" x1="260" y1="40" x2="260" y2="680" />
                            <line className="pointer-line" strokeDasharray="4 4" x1="120" y1="260" x2="400" y2="260" />
                            <line className="pointer-line" strokeDasharray="4 4" x1="120" y1="460" x2="400" y2="460" />

                            {/* Label Orientasi Medis */}
                            <g className="pointer-text text-[11px] font-bold" fontSize="11" textAnchor="middle">
                                <text x="260" y="24">SUPERIOR · KRANIAL</text>
                                <text x="260" y="708">INFERIOR · KAUDAL</text>
                                <text x="110" y="254">KANAN (DEXTER)</text>
                                <text x="410" y="254">KIRI (SINISTER)</text>
                            </g>

                            {/* Siluet Anatomi Tubuh Manusia (Kontur Luar Proporsional) */}
                            <path
                                className="body-silhouette"
                                d={
                                    viewMode === 'organs'
                                        ? 'M 220 50 C 220 30, 240 22, 260 22 C 280 22, 300 30, 300 50 C 300 80, 292 98, 282 108 L 282 124 C 330 134, 385 152, 405 200 C 420 235, 415 320, 400 380 C 390 420, 375 460, 360 500 L 335 520 C 315 540, 290 545, 260 545 C 230 545, 205 540, 185 520 L 160 500 C 145 460, 130 420, 120 380 C 105 320, 100 235, 115 200 C 135 152, 190 134, 238 124 L 238 108 C 228 98, 220 80, 220 50 Z'
                                        : 'M 235 50 C 235 32, 246 24, 260 24 C 274 24, 285 32, 285 50 C 285 75, 278 88, 272 96 L 272 110 C 310 118, 350 132, 365 170 L 390 280 L 402 370 C 404 382, 396 390, 388 388 L 372 320 L 358 240 L 350 340 C 344 410, 336 460, 332 500 L 336 600 L 340 665 C 342 674, 334 680, 324 680 L 296 680 C 288 680, 284 672, 284 662 L 282 560 L 274 480 C 268 470, 252 470, 246 480 L 238 560 L 236 662 C 236 672, 232 680, 224 680 L 196 680 C 186 680, 178 674, 180 665 L 184 600 L 188 500 C 184 460, 176 410, 170 340 L 162 240 L 148 320 L 132 388 C 124 390, 116 382, 118 370 L 130 280 L 155 170 C 170 132, 210 118, 248 110 L 248 96 C 242 88, 235 75, 235 50 Z'
                                }
                            />

                            {/* Tulang Rangka Halus (Clavicle, Sternum, Ribs, Pelvis) Sebagai Panduan */}
                            <g className="body-skeleton">
                                {/* Clavicle Kiri & Kanan */}
                                <path d="M 260 128 Q 220 124 175 142 M 260 128 Q 300 124 345 142" />
                                {/* Sternum (Tulang Dada) */}
                                <line x1="260" y1="140" x2="260" y2="245" strokeWidth="2.5" />
                                {/* Lengkung Iga (Ribs Contour) */}
                                <path d="M 252 165 C 215 168 185 190 180 220" />
                                <path d="M 268 165 C 305 168 335 190 340 220" />
                                <path d="M 252 205 C 205 215 180 245 178 280" />
                                <path d="M 268 205 C 315 215 340 245 342 280" />
                                <path d="M 252 245 C 210 260 185 300 185 340" />
                                <path d="M 268 245 C 310 260 335 300 335 340" />
                                {/* Batas Pelvis / Panggul */}
                                <path d="M 195 450 C 220 480 260 485 260 485 C 260 485 300 480 325 450" />
                            </g>

                            {/* 1. OTAK (Brain) */}
                            <g
                                className={`organ-shape organ-nervous ${selectedOrganId === 'brain' ? 'is-selected' : ''}`}
                                onClick={() => handleOrganClick(BODY_ORGANS[0])}
                                onMouseEnter={() => setHoveredOrganId('brain')}
                                onMouseLeave={() => setHoveredOrganId(null)}
                            >
                                <path d="M 240 50 C 235 60 235 75 242 85 C 250 88 270 88 278 85 C 285 75 285 60 280 50 C 275 38 245 38 240 50 Z" />
                                {/* Sulkus & Girus Serebrum */}
                                <path fill="none" opacity="0.6" stroke="currentColor" strokeWidth="1" d="M 245 55 Q 260 52 275 55 M 242 66 Q 260 62 278 66 M 244 76 Q 260 74 276 76 M 260 45 L 260 84" />
                            </g>

                            {/* 2. MATA (Eyes) */}
                            <g
                                className={`organ-shape organ-nervous ${selectedOrganId === 'eyes' ? 'is-selected' : ''}`}
                                onClick={() => handleOrganClick(BODY_ORGANS[1])}
                                onMouseEnter={() => setHoveredOrganId('eyes')}
                                onMouseLeave={() => setHoveredOrganId(null)}
                            >
                                <ellipse cx="250" cy="62" rx="4.5" ry="3" />
                                <ellipse cx="270" cy="62" rx="4.5" ry="3" />
                                <circle cx="250" cy="62" r="1.5" fill="currentColor" />
                                <circle cx="270" cy="62" r="1.5" fill="currentColor" />
                            </g>

                            {/* 3. TENGGOROKAN & TRAKEA (ENT & Throat) */}
                            <g
                                className={`organ-shape organ-respiratory ${selectedOrganId === 'ent_throat' ? 'is-selected' : ''}`}
                                onClick={() => handleOrganClick(BODY_ORGANS[2])}
                                onMouseEnter={() => setHoveredOrganId('ent_throat')}
                                onMouseLeave={() => setHoveredOrganId(null)}
                            >
                                <rect x="254" y="98" width="12" height="42" rx="3" />
                                {/* Cincin Kartilago Trakea */}
                                <line x1="255" y1="108" x2="265" y2="108" stroke="currentColor" strokeWidth="1" opacity="0.6" />
                                <line x1="255" y1="118" x2="265" y2="118" stroke="currentColor" strokeWidth="1" opacity="0.6" />
                                <line x1="255" y1="128" x2="265" y2="128" stroke="currentColor" strokeWidth="1" opacity="0.6" />
                            </g>

                            {/* 4. PARU-PARU (Lungs - Kiri & Kanan) */}
                            <g
                                className={`organ-shape organ-respiratory ${selectedOrganId === 'lungs' ? 'is-selected' : ''}`}
                                onClick={() => handleOrganClick(BODY_ORGANS[3])}
                                onMouseEnter={() => setHoveredOrganId('lungs')}
                                onMouseLeave={() => setHoveredOrganId(null)}
                            >
                                {/* Paru Kanan Pasien (Sisi Kiri Diagram) */}
                                <path d="M 248 145 C 235 146 200 160 190 190 C 180 220 180 265 192 280 C 205 285 242 278 248 262 Z" />
                                {/* Paru Kiri Pasien (Sisi Kanan Diagram dengan Cardiac Notch) */}
                                <path d="M 272 145 C 285 146 320 160 330 190 C 340 220 340 265 328 280 C 315 285 285 280 274 250 C 270 235 270 200 272 145 Z" />
                                {/* Bronkus Bifurkasi */}
                                <path fill="none" opacity="0.65" stroke="currentColor" strokeWidth="1.3" d="M 260 140 L 235 180 M 260 140 L 285 180" />
                            </g>

                            {/* 5. JANTUNG (Heart) */}
                            <g
                                className={`organ-shape organ-circulatory ${selectedOrganId === 'heart' ? 'is-selected' : ''}`}
                                onClick={() => handleOrganClick(BODY_ORGANS[4])}
                                onMouseEnter={() => setHoveredOrganId('heart')}
                                onMouseLeave={() => setHoveredOrganId(null)}
                            >
                                {/* Arkus Aorta di Atas */}
                                <path d="M 256 182 C 252 170 272 165 274 182 Z" opacity="0.8" />
                                {/* Ventrikel & Atrium Jantung Mengarah Miring ke Kiri Pasien (Sisi Kanan) */}
                                <path d="M 252 188 C 242 195 240 215 252 230 C 264 245 278 252 284 250 C 294 240 295 210 286 195 C 280 188 262 184 252 188 Z" />
                            </g>

                            {/* 6. HATI (Liver / Hepar) */}
                            <g
                                className={`organ-shape organ-digestive ${selectedOrganId === 'liver' ? 'is-selected' : ''}`}
                                onClick={() => handleOrganClick(BODY_ORGANS[5])}
                                onMouseEnter={() => setHoveredOrganId('liver')}
                                onMouseLeave={() => setHoveredOrganId(null)}
                            >
                                <path d="M 205 288 C 215 278 250 278 268 285 C 265 315 255 332 242 334 C 220 334 200 310 205 288 Z" />
                                {/* Kandung Empedu Kecil di Bawah Hepar */}
                                <ellipse cx="236" cy="328" rx="4" ry="6" fill="#10b981" opacity="0.8" />
                            </g>

                            {/* 7. LAMBUNG (Stomach / Gaster) */}
                            <g
                                className={`organ-shape organ-digestive ${selectedOrganId === 'stomach' ? 'is-selected' : ''}`}
                                onClick={() => handleOrganClick(BODY_ORGANS[6])}
                                onMouseEnter={() => setHoveredOrganId('stomach')}
                                onMouseLeave={() => setHoveredOrganId(null)}
                            >
                                <path d="M 268 286 C 282 284 314 290 318 310 C 320 328 300 342 278 340 C 264 338 262 315 268 286 Z" />
                            </g>

                            {/* 8. PANKREAS & LIMPA (Pancreas) */}
                            <g
                                className={`organ-shape organ-digestive ${selectedOrganId === 'pancreas' ? 'is-selected' : ''}`}
                                onClick={() => handleOrganClick(BODY_ORGANS[7])}
                                onMouseEnter={() => setHoveredOrganId('pancreas')}
                                onMouseLeave={() => setHoveredOrganId(null)}
                            >
                                <path d="M 252 342 C 265 336 292 338 306 344 C 300 350 270 352 252 342 Z" />
                                {/* Limpa di Kuadran Kiri Atas Belakang Lambung */}
                                <ellipse cx="324" cy="315" rx="7" ry="10" opacity="0.75" />
                            </g>

                            {/* 9. GINJAL BILATERAL (Kidneys / Renes) */}
                            <g
                                className={`organ-shape organ-urinary ${selectedOrganId === 'kidneys' ? 'is-selected' : ''}`}
                                onClick={() => handleOrganClick(BODY_ORGANS[8])}
                                onMouseEnter={() => setHoveredOrganId('kidneys')}
                                onMouseLeave={() => setHoveredOrganId(null)}
                            >
                                {/* Ginjal Kanan Pasien */}
                                <path d="M 215 348 C 224 345 228 360 225 372 C 220 382 210 380 208 370 C 206 358 210 349 215 348 Z" />
                                {/* Ginjal Kiri Pasien (Sedikit Lebih Tinggi) */}
                                <path d="M 305 344 C 314 342 318 357 315 368 C 310 378 300 376 298 366 C 296 354 300 345 305 344 Z" />
                                {/* Ureter Bilateral Turun ke Pelvis */}
                                <path fill="none" opacity="0.45" stroke="currentColor" strokeDasharray="2 2" strokeWidth="1" d="M 218 375 C 225 410 245 440 255 465 M 307 372 C 298 410 275 440 265 465" />
                            </g>

                            {/* 10. USUS BESAR (Large Intestine / Colon) */}
                            <g
                                className={`organ-shape organ-digestive ${selectedOrganId === 'intestines' ? 'is-selected' : ''}`}
                                onClick={() => handleOrganClick(BODY_ORGANS[9])}
                                onMouseEnter={() => setHoveredOrganId('intestines')}
                                onMouseLeave={() => setHoveredOrganId(null)}
                            >
                                {/* Kolon Membingkai Abdomen (Ascending, Transverse, Descending) */}
                                <path
                                    d="M 226 438 L 226 360 C 226 350 240 348 260 348 C 280 348 294 350 294 360 L 294 438 L 282 438 L 282 364 L 238 364 L 238 438 Z"
                                    opacity="0.9"
                                />
                                {/* 11. USUS HALUS (Small Intestine di Bagian Tengah) */}
                                <path
                                    d="M 238 375 C 248 370 272 370 282 375 C 285 390 285 415 282 430 C 270 436 248 436 238 430 Z"
                                    opacity="0.8"
                                />
                                <path fill="none" opacity="0.5" stroke="currentColor" strokeWidth="1.2" d="M 244 388 Q 260 382 276 388 M 242 402 Q 260 396 278 402 M 244 416 Q 260 410 276 416" />
                            </g>

                            {/* 12. APENDIKS / USUS BUNTU (Appendix) */}
                            <g
                                className={`organ-shape organ-digestive ${selectedOrganId === 'appendix' ? 'is-selected' : ''}`}
                                onClick={() => handleOrganClick(BODY_ORGANS[10])}
                                onMouseEnter={() => setHoveredOrganId('appendix')}
                                onMouseLeave={() => setHoveredOrganId(null)}
                            >
                                <path d="M 225 440 C 225 452 232 456 230 460 C 228 463 222 460 222 452 Z" />
                                <circle cx="227" cy="452" r="3" fill="#ef4444" opacity="0.8" />
                            </g>

                            {/* 13. KANDUNG KEMIH (Urinary Bladder) */}
                            <g
                                className={`organ-shape organ-urinary ${selectedOrganId === 'bladder' ? 'is-selected' : ''}`}
                                onClick={() => handleOrganClick(BODY_ORGANS[11])}
                                onMouseEnter={() => setHoveredOrganId('bladder')}
                                onMouseLeave={() => setHoveredOrganId(null)}
                            >
                                <path d="M 248 466 C 248 458 272 458 272 466 C 274 480 268 492 260 496 C 252 492 246 480 248 466 Z" />
                            </g>

                            {/* 14. EKSTREMITAS ATAS & BAWAH HOTSPOTS (Bahu, Tangan, Lutut, Kaki) */}
                            <g
                                className={`organ-shape organ-musculoskeletal ${selectedOrganId === 'upper_limbs' ? 'is-selected' : ''}`}
                                onClick={() => handleOrganClick(BODY_ORGANS[12])}
                                onMouseEnter={() => setHoveredOrganId('upper_limbs')}
                                onMouseLeave={() => setHoveredOrganId(null)}
                            >
                                <ellipse cx="160" cy="190" rx="14" ry="14" fill="currentColor" fillOpacity="0.08" stroke="currentColor" strokeDasharray="3 2" />
                                <ellipse cx="360" cy="190" rx="14" ry="14" fill="currentColor" fillOpacity="0.08" stroke="currentColor" strokeDasharray="3 2" />
                                <circle cx="160" cy="190" r="3.5" fill="currentColor" />
                                <circle cx="360" cy="190" r="3.5" fill="currentColor" />
                            </g>

                            <g
                                className={`organ-shape organ-musculoskeletal ${selectedOrganId === 'lower_limbs' ? 'is-selected' : ''}`}
                                onClick={() => handleOrganClick(BODY_ORGANS[14])}
                                onMouseEnter={() => setHoveredOrganId('lower_limbs')}
                                onMouseLeave={() => setHoveredOrganId(null)}
                            >
                                <ellipse cx="225" cy="560" rx="15" ry="18" fill="currentColor" fillOpacity="0.08" stroke="currentColor" strokeDasharray="3 2" />
                                <ellipse cx="295" cy="560" rx="15" ry="18" fill="currentColor" fillOpacity="0.08" stroke="currentColor" strokeDasharray="3 2" />
                                <circle cx="225" cy="560" r="3.5" fill="currentColor" />
                                <circle cx="295" cy="560" r="3.5" fill="currentColor" />
                            </g>

                            {/* GARIS PENUNJUK & LABEL ANATOMIS (Sesuai Referensi Gambar) */}
                            {BODY_ORGANS.map((organ) => {
                                const isSelected = selectedOrganId === organ.id;
                                const isHovered = hoveredOrganId === organ.id;
                                const hasFinding = Boolean(recordedFindings[organ.id] || recordedFindings[organ.physicalExamCategory]);
                                const isMatch = activeSystemFilter ? organ.system === activeSystemFilter : true;

                                return (
                                    <g
                                        key={organ.id}
                                        className={`transition-opacity ${isMatch ? 'opacity-100' : 'opacity-25'}`}
                                    >
                                        {/* Titik Penunjuk pada Organ */}
                                        <circle
                                            className="pointer-dot"
                                            cx={organ.pointerX}
                                            cy={organ.pointerY}
                                            r={isSelected ? 4 : 2.5}
                                        />

                                        {/* Garis Hubung Menuju Teks Label Samping */}
                                        <polyline
                                            className="pointer-line"
                                            points={
                                                organ.side === 'left'
                                                    ? `${organ.pointerX},${organ.pointerY} ${organ.labelX + 90},${organ.labelY} ${organ.labelX + 80},${organ.labelY}`
                                                    : `${organ.pointerX},${organ.pointerY} ${organ.labelX - 90},${organ.labelY} ${organ.labelX - 80},${organ.labelY}`
                                            }
                                        />

                                        {/* Teks Label Organ Samping */}
                                        <text
                                            className={`pointer-text ${isSelected || isHovered ? 'is-active' : ''} cursor-pointer`}
                                            textAnchor={organ.side === 'left' ? 'end' : 'start'}
                                            x={organ.side === 'left' ? organ.labelX + 75 : organ.labelX - 75}
                                            y={organ.labelY + 4}
                                            onClick={() => handleOrganClick(organ)}
                                            onMouseEnter={() => setHoveredOrganId(organ.id)}
                                            onMouseLeave={() => setHoveredOrganId(null)}
                                        >
                                            {organ.name}
                                        </text>

                                        {/* Indikator Temuan Terpilih / Tercatat */}
                                        {hasFinding && (
                                            <circle
                                                cx={organ.side === 'left' ? organ.labelX + 85 : organ.labelX - 85}
                                                cy={organ.labelY}
                                                r="3.5"
                                                fill="#f59e0b"
                                                className="animate-pulse"
                                            />
                                        )}
                                    </g>
                                );
                            })}
                        </svg>
                    </div>

                    {/* Catatan Kaki Diagram */}
                    <div className="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-neutral-200 pt-3 text-sm leading-5 text-neutral-600">
                        <span>Pilih organ untuk melihat panduan keluhan & saran ICD-10</span>
                        <span>Klik kategori di panel kanan untuk filter sistem organ</span>
                    </div>
                </div>

                {/* Panel Kanan: Kategori Sistem Fisiologis (Atas) & Detail Organ Terpilih (Bawah) - 5 Kolom */}
                <div className="flex flex-col gap-5 xl:col-span-5">
                    {/* Bagian Atas: 6 Kategori Sistem Organ Tubuh */}
                    <div className="rounded-2xl border border-neutral-200/90 bg-surface p-4 shadow-xs sm:p-5">
                        <div className="mb-3 flex items-center justify-between border-b border-neutral-100 pb-2.5">
                            <div>
                                <h4 className="text-sm font-bold tracking-tight text-neutral-900">
                                    Klasifikasi Sistem Organ
                                </h4>
                                <p className="text-xs text-neutral-500">
                                    6 kelompok fisiologis sesuai referensi Cambridge Human Body
                                </p>
                            </div>
                            {activeSystemFilter && (
                                <Button
                                    onClick={() => setActiveSystemFilter(null)}
                                    size="sm"
                                    type="button"
                                    variant="ghost"
                                    className="h-6 text-[11px] text-neutral-600"
                                >
                                    Reset
                                </Button>
                            )}
                        </div>

                        {/* Grid Tombol Sistem Organ */}
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            {(Object.entries(ORGAN_SYSTEMS) as [BodySystemCode, typeof ORGAN_SYSTEMS[BodySystemCode]][]).map(([code, meta]) => {
                                const Icon = meta.icon;
                                const isFilterActive = activeSystemFilter === code;
                                const isCurrentSelected = activeOrgan.system === code;
                                const count = BODY_ORGANS.filter((o) => o.system === code).length;

                                return (
                                    <button
                                        key={code}
                                        type="button"
                                        onClick={() => setActiveSystemFilter((cur) => (cur === code ? null : code))}
                                        className={`group flex flex-col items-start rounded-xl border p-2.5 text-left transition-all ${
                                            isFilterActive
                                                ? 'border-neutral-900 bg-neutral-100 shadow-xs ring-1 ring-neutral-900'
                                                : isCurrentSelected
                                                  ? 'border-neutral-400 bg-neutral-100/60'
                                                  : 'border-neutral-200/80 bg-neutral-50/50 hover:border-neutral-300 hover:bg-neutral-100/70'
                                        }`}
                                    >
                                        <div className="flex w-full items-center justify-between">
                                            <Icon className="size-4 text-neutral-700" />
                                            <span className="font-mono text-[10px] text-neutral-400">
                                                {count} organ
                                            </span>
                                        </div>
                                        <span className="mt-1.5 font-sans text-xs font-bold text-neutral-900">
                                            {meta.label}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {/* Bagian Bawah: Panduan Klinis & Temuan Organ Terpilih */}
                    <div className="flex-1 rounded-2xl border border-neutral-200/90 bg-surface p-4 shadow-xs sm:p-5">
                        <div className="mb-3 flex items-center justify-between border-b border-neutral-100 pb-2.5">
                            <div>
                                <h4 className="text-sm font-bold tracking-tight text-neutral-900">
                                    Panduan Klinis: {activeOrgan.name}
                                </h4>
                                <p className="font-mono text-xs italic text-neutral-500">
                                    {activeOrgan.latinName}
                                </p>
                            </div>
                            <span className="rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] font-medium text-neutral-700">
                                {activeOrgan.systemName}
                            </span>
                        </div>

                        <p className="text-xs leading-5 text-neutral-600">
                            {activeOrgan.description}
                        </p>

                        {/* Daftar Gejala Klinis Khas */}
                        <div className="mt-3 space-y-2">
                            <span className="text-[11px] font-semibold text-neutral-500">
                                Gejala / Keluhan Sering Ditemukan:
                            </span>
                            <div className="flex flex-wrap gap-1.5">
                                {activeOrgan.commonSymptoms.map((symptom) => (
                                    <button
                                        key={symptom}
                                        type="button"
                                        onClick={() => onApplyToExam?.(activeOrgan, symptom)}
                                        className="rounded-lg border border-neutral-200 bg-neutral-50 px-2 py-1 text-left text-xs font-medium text-neutral-700 hover:border-neutral-400 hover:bg-neutral-100"
                                        title="Klik untuk memasukkan ke pemeriksaan fisik"
                                    >
                                        + {symptom}
                                    </button>
                                ))}
                            </div>
                        </div>

                        {/* Kandidat ICD-10 Cepat */}
                        <div className="mt-4 rounded-xl border border-neutral-200 bg-neutral-50/70 p-3">
                            <div className="flex items-center justify-between">
                                <span className="text-xs font-semibold text-neutral-800">
                                    Pencarian Kode ICD-10 Terkait:
                                </span>
                                {onQuickSearchIcd && (
                                    <Button
                                        onClick={() => onQuickSearchIcd(activeOrgan.icd10Prompt)}
                                        size="sm"
                                        type="button"
                                        variant="ghost"
                                        className="h-6 text-xs text-neutral-700 hover:text-neutral-900"
                                    >
                                        <Search className="mr-1 size-3" />
                                        Cari di katalog
                                    </Button>
                                )}
                            </div>
                            <div className="mt-2 flex flex-wrap gap-1.5">
                                {activeOrgan.icd10Candidates.map((code) => (
                                    <button
                                        key={code}
                                        type="button"
                                        onClick={() => onQuickSearchIcd?.(code)}
                                        className="inline-flex items-center gap-1 rounded-md bg-surface px-2 py-0.5 font-mono text-xs font-semibold text-neutral-800 shadow-2xs hover:bg-neutral-100"
                                        title={`Cari kode ${code}`}
                                    >
                                        {code}
                                        <ChevronRight className="size-3 text-neutral-400" />
                                    </button>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Panel Ringkasan Organ Terpilih (Active Detail Banner) */}
            <div className="flex flex-col justify-between gap-3 rounded-xl border border-neutral-200/90 bg-neutral-50/70 p-3.5 transition-colors sm:flex-row sm:items-center">
                <div className="flex items-center gap-3">
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-neutral-900 font-mono text-base font-black text-neutral-50">
                        {activeOrgan.name.slice(0, 2).toUpperCase()}
                    </div>
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="text-sm font-bold text-neutral-900">
                                {activeOrgan.name}
                            </span>
                            <Badge variant="default" className="font-mono text-xs">
                                {activeOrgan.latinName}
                            </Badge>
                            <span className="text-xs text-neutral-500">
                                · Kategori {activeOrgan.physicalExamCategory}
                            </span>
                        </div>
                        <p className="mt-0.5 text-xs text-neutral-600">
                            {recordedFindings[activeOrgan.id] ? (
                                <span className="font-medium text-neutral-800">
                                    Catatan: {recordedFindings[activeOrgan.id]}
                                </span>
                            ) : (
                                <span>Pilih organ pada diagram tubuh untuk menautkan catatan pemeriksaan.</span>
                            )}
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-2 sm:self-center">
                    {onApplyToExam && (
                        <Button
                            onClick={() => onApplyToExam(activeOrgan, `Pemeriksaan ${activeOrgan.name}: dalam batas normal, tidak ada kelainan bermakna.`)}
                            size="sm"
                            type="button"
                            variant="secondary"
                            className="h-8 text-xs"
                        >
                            <Check className="mr-1 size-3.5" />
                            Tandai Normal
                        </Button>
                    )}
                </div>
            </div>

            {/* Legenda Status Anatomi - Warna Netral dan Proporsional */}
            <div aria-label="Legenda sistem organ anatomi" className="flex flex-wrap items-center justify-center gap-2 text-sm">
                {[
                    { label: 'Sistem Pernapasan (Respirasi)', class: 'bg-sky-100 border-sky-500 dark:bg-sky-950 dark:border-sky-400' },
                    { label: 'Sistem Kardiovaskular (Jantung)', class: 'bg-rose-100 border-rose-500 dark:bg-rose-950 dark:border-rose-400' },
                    { label: 'Sistem Pencernaan (Digestif)', class: 'bg-amber-100 border-amber-500 dark:bg-amber-950 dark:border-amber-400' },
                    { label: 'Sistem Saraf & Sensorik', class: 'bg-purple-100 border-purple-500 dark:bg-purple-950 dark:border-purple-400' },
                    { label: 'Sistem Perkemihan (Urinarius)', class: 'bg-emerald-100 border-emerald-500 dark:bg-emerald-950 dark:border-emerald-400' },
                    { label: 'Muskuloskeletal & Ekstremitas', class: 'bg-neutral-100 border-neutral-500' },
                ].map((item) => (
                    <span
                        key={item.label}
                        className="inline-flex items-center gap-2 rounded-full border border-neutral-200 bg-surface px-3 py-1.5 text-xs font-medium text-neutral-700 shadow-2xs"
                    >
                        <span aria-hidden="true" className={`size-2.5 rounded-full border ${item.class}`} />
                        {item.label}
                    </span>
                ))}
            </div>
        </div>
    );
}
