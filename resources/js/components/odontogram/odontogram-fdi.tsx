import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Check } from 'lucide-react';

export interface DentalFinding {
    toothFdi: string;
    surface: string;
    findingCode: string;
    notes: string | null;
}

export interface OdontogramProps {
    dentition: 'adult' | 'primary';
    findings: DentalFinding[];
    selectedTooth: string;
    disabled?: boolean;
    onSelect: (tooth: string) => void;
    findingLabels?: Record<string, string>;
    surfaceLabels?: Record<string, string>;
}

export type ToothType = 'incisor' | 'canine' | 'premolar' | 'molar';

export interface ToothMeta {
    fdi: string;
    universal?: number;
    name: string;
    latinName: string;
    type: ToothType;
    quadrant: number;
    quadrantLabel: string;
    archX: number;
    archY: number;
    archRot: number;
    smileX?: number;
}

// 32 Gigi Dewasa (Permanen)
export const ADULT_TEETH: ToothMeta[] = [
    // Kuadran 1: Rahang Atas Kanan (Maxilla Kanan - Sisi Kiri Diagram)
    { fdi: '18', universal: 1, name: 'Molar 3 Atas Kanan', latinName: 'Dens Molaris 3 Superior Dexter', type: 'molar', quadrant: 1, quadrantLabel: 'Rahang Atas Kanan', archX: 90, archY: 330, archRot: -95 },
    { fdi: '17', universal: 2, name: 'Molar 2 Atas Kanan', latinName: 'Dens Molaris 2 Superior Dexter', type: 'molar', quadrant: 1, quadrantLabel: 'Rahang Atas Kanan', archX: 92, archY: 282, archRot: -82 },
    { fdi: '16', universal: 3, name: 'Molar 1 Atas Kanan', latinName: 'Dens Molaris 1 Superior Dexter', type: 'molar', quadrant: 1, quadrantLabel: 'Rahang Atas Kanan', archX: 100, archY: 234, archRot: -68 },
    { fdi: '15', universal: 4, name: 'Premolar 2 Atas Kanan', latinName: 'Dens Praemolaris 2 Superior Dexter', type: 'premolar', quadrant: 1, quadrantLabel: 'Rahang Atas Kanan', archX: 115, archY: 188, archRot: -54 },
    { fdi: '14', universal: 5, name: 'Premolar 1 Atas Kanan', latinName: 'Dens Praemolaris 1 Superior Dexter', type: 'premolar', quadrant: 1, quadrantLabel: 'Rahang Atas Kanan', archX: 136, archY: 146, archRot: -40 },
    { fdi: '13', universal: 6, name: 'Caninus Atas Kanan', latinName: 'Dens Caninus Superior Dexter', type: 'canine', quadrant: 1, quadrantLabel: 'Rahang Atas Kanan', archX: 165, archY: 110, archRot: -26 },
    { fdi: '12', universal: 7, name: 'Incisivus 2 Atas Kanan', latinName: 'Dens Incisivus Lateralis Superior Dexter', type: 'incisor', quadrant: 1, quadrantLabel: 'Rahang Atas Kanan', archX: 202, archY: 82, archRot: -13 },
    { fdi: '11', universal: 8, name: 'Incisivus 1 Atas Kanan', latinName: 'Dens Incisivus Centralis Superior Dexter', type: 'incisor', quadrant: 1, quadrantLabel: 'Rahang Atas Kanan', archX: 243, archY: 66, archRot: 0 },

    // Kuadran 2: Rahang Atas Kiri (Maxilla Kiri - Sisi Kanan Diagram)
    { fdi: '21', universal: 9, name: 'Incisivus 1 Atas Kiri', latinName: 'Dens Incisivus Centralis Superior Sinister', type: 'incisor', quadrant: 2, quadrantLabel: 'Rahang Atas Kiri', archX: 277, archY: 66, archRot: 0 },
    { fdi: '22', universal: 10, name: 'Incisivus 2 Atas Kiri', latinName: 'Dens Incisivus Lateralis Superior Sinister', type: 'incisor', quadrant: 2, quadrantLabel: 'Rahang Atas Kiri', archX: 318, archY: 82, archRot: 13 },
    { fdi: '23', universal: 11, name: 'Caninus Atas Kiri', latinName: 'Dens Caninus Superior Sinister', type: 'canine', quadrant: 2, quadrantLabel: 'Rahang Atas Kiri', archX: 355, archY: 110, archRot: 26 },
    { fdi: '24', universal: 12, name: 'Premolar 1 Atas Kiri', latinName: 'Dens Praemolaris 1 Superior Sinister', type: 'premolar', quadrant: 2, quadrantLabel: 'Rahang Atas Kiri', archX: 384, archY: 146, archRot: 40 },
    { fdi: '25', universal: 13, name: 'Premolar 2 Atas Kiri', latinName: 'Dens Praemolaris 2 Superior Sinister', type: 'premolar', quadrant: 2, quadrantLabel: 'Rahang Atas Kiri', archX: 405, archY: 188, archRot: 54 },
    { fdi: '26', universal: 14, name: 'Molar 1 Atas Kiri', latinName: 'Dens Molaris 1 Superior Sinister', type: 'molar', quadrant: 2, quadrantLabel: 'Rahang Atas Kiri', archX: 420, archY: 234, archRot: 68 },
    { fdi: '27', universal: 15, name: 'Molar 2 Atas Kiri', latinName: 'Dens Molaris 2 Superior Sinister', type: 'molar', quadrant: 2, quadrantLabel: 'Rahang Atas Kiri', archX: 428, archY: 282, archRot: 82 },
    { fdi: '28', universal: 16, name: 'Molar 3 Atas Kiri', latinName: 'Dens Molaris 3 Superior Sinister', type: 'molar', quadrant: 2, quadrantLabel: 'Rahang Atas Kiri', archX: 430, archY: 330, archRot: 95 },

    // Kuadran 3: Rahang Bawah Kiri (Mandibula Kiri - Sisi Kanan Diagram)
    { fdi: '38', universal: 17, name: 'Molar 3 Bawah Kiri', latinName: 'Dens Molaris 3 Inferior Sinister', type: 'molar', quadrant: 3, quadrantLabel: 'Rahang Bawah Kiri', archX: 430, archY: 390, archRot: 85 },
    { fdi: '37', universal: 18, name: 'Molar 2 Bawah Kiri', latinName: 'Dens Molaris 2 Inferior Sinister', type: 'molar', quadrant: 3, quadrantLabel: 'Rahang Bawah Kiri', archX: 428, archY: 438, archRot: 98 },
    { fdi: '36', universal: 19, name: 'Molar 1 Bawah Kiri', latinName: 'Dens Molaris 1 Inferior Sinister', type: 'molar', quadrant: 3, quadrantLabel: 'Rahang Bawah Kiri', archX: 420, archY: 486, archRot: 112 },
    { fdi: '35', universal: 20, name: 'Premolar 2 Bawah Kiri', latinName: 'Dens Praemolaris 2 Inferior Sinister', type: 'premolar', quadrant: 3, quadrantLabel: 'Rahang Bawah Kiri', archX: 405, archY: 532, archRot: 126 },
    { fdi: '34', universal: 21, name: 'Premolar 1 Bawah Kiri', latinName: 'Dens Praemolaris 1 Inferior Sinister', type: 'premolar', quadrant: 3, quadrantLabel: 'Rahang Bawah Kiri', archX: 384, archY: 574, archRot: 140 },
    { fdi: '33', universal: 22, name: 'Caninus Bawah Kiri', latinName: 'Dens Caninus Inferior Sinister', type: 'canine', quadrant: 3, quadrantLabel: 'Rahang Bawah Kiri', archX: 355, archY: 610, archRot: 154 },
    { fdi: '32', universal: 23, name: 'Incisivus 2 Bawah Kiri', latinName: 'Dens Incisivus Lateralis Inferior Sinister', type: 'incisor', quadrant: 3, quadrantLabel: 'Rahang Bawah Kiri', archX: 318, archY: 638, archRot: 167 },
    { fdi: '31', universal: 24, name: 'Incisivus 1 Bawah Kiri', latinName: 'Dens Incisivus Centralis Inferior Sinister', type: 'incisor', quadrant: 3, quadrantLabel: 'Rahang Bawah Kiri', archX: 277, archY: 654, archRot: 180 },

    // Kuadran 4: Rahang Bawah Kanan (Mandibula Kanan - Sisi Kiri Diagram)
    { fdi: '41', universal: 25, name: 'Incisivus 1 Bawah Kanan', latinName: 'Dens Incisivus Centralis Inferior Dexter', type: 'incisor', quadrant: 4, quadrantLabel: 'Rahang Bawah Kanan', archX: 243, archY: 654, archRot: 180 },
    { fdi: '42', universal: 26, name: 'Incisivus 2 Bawah Kanan', latinName: 'Dens Incisivus Lateralis Inferior Dexter', type: 'incisor', quadrant: 4, quadrantLabel: 'Rahang Bawah Kanan', archX: 202, archY: 638, archRot: 193 },
    { fdi: '43', universal: 27, name: 'Caninus Bawah Kanan', latinName: 'Dens Caninus Inferior Dexter', type: 'canine', quadrant: 4, quadrantLabel: 'Rahang Bawah Kanan', archX: 165, archY: 610, archRot: 206 },
    { fdi: '44', universal: 28, name: 'Premolar 1 Bawah Kanan', latinName: 'Dens Praemolaris 1 Inferior Dexter', type: 'premolar', quadrant: 4, quadrantLabel: 'Rahang Bawah Kanan', archX: 136, archY: 574, archRot: 220 },
    { fdi: '45', universal: 29, name: 'Premolar 2 Bawah Kanan', latinName: 'Dens Praemolaris 2 Inferior Dexter', type: 'premolar', quadrant: 4, quadrantLabel: 'Rahang Bawah Kanan', archX: 115, archY: 532, archRot: 234 },
    { fdi: '46', universal: 30, name: 'Molar 1 Bawah Kanan', latinName: 'Dens Molaris 1 Inferior Dexter', type: 'molar', quadrant: 4, quadrantLabel: 'Rahang Bawah Kanan', archX: 100, archY: 486, archRot: 248 },
    { fdi: '47', universal: 31, name: 'Molar 2 Bawah Kanan', latinName: 'Dens Molaris 2 Inferior Dexter', type: 'molar', quadrant: 4, quadrantLabel: 'Rahang Bawah Kanan', archX: 92, archY: 438, archRot: 262 },
    { fdi: '48', universal: 32, name: 'Molar 3 Bawah Kanan', latinName: 'Dens Molaris 3 Inferior Dexter', type: 'molar', quadrant: 4, quadrantLabel: 'Rahang Bawah Kanan', archX: 90, archY: 390, archRot: 275 },
];

// 20 Gigi Anak (Desidui / Susu)
export const PRIMARY_TEETH: ToothMeta[] = [
    // Kuadran 5: Rahang Atas Kanan
    { fdi: '55', name: 'Molar 2 Susu Atas Kanan', latinName: 'Dens Deciduus Molaris 2 Sup. Dex.', type: 'molar', quadrant: 5, quadrantLabel: 'Rahang Atas Kanan', archX: 105, archY: 280, archRot: -80 },
    { fdi: '54', name: 'Molar 1 Susu Atas Kanan', latinName: 'Dens Deciduus Molaris 1 Sup. Dex.', type: 'molar', quadrant: 5, quadrantLabel: 'Rahang Atas Kanan', archX: 125, archY: 210, archRot: -55 },
    { fdi: '53', name: 'Caninus Susu Atas Kanan', latinName: 'Dens Deciduus Caninus Sup. Dex.', type: 'canine', quadrant: 5, quadrantLabel: 'Rahang Atas Kanan', archX: 160, archY: 145, archRot: -30 },
    { fdi: '52', name: 'Incisivus 2 Susu Atas Kanan', latinName: 'Dens Deciduus Incisivus Lat. Sup. Dex.', type: 'incisor', quadrant: 5, quadrantLabel: 'Rahang Atas Kanan', archX: 205, archY: 100, archRot: -15 },
    { fdi: '51', name: 'Incisivus 1 Susu Atas Kanan', latinName: 'Dens Deciduus Incisivus Med. Sup. Dex.', type: 'incisor', quadrant: 5, quadrantLabel: 'Rahang Atas Kanan', archX: 245, archY: 80, archRot: 0 },

    // Kuadran 6: Rahang Atas Kiri
    { fdi: '61', name: 'Incisivus 1 Susu Atas Kiri', latinName: 'Dens Deciduus Incisivus Med. Sup. Sin.', type: 'incisor', quadrant: 6, quadrantLabel: 'Rahang Atas Kiri', archX: 275, archY: 80, archRot: 0 },
    { fdi: '62', name: 'Incisivus 2 Susu Atas Kiri', latinName: 'Dens Deciduus Incisivus Lat. Sup. Sin.', type: 'incisor', quadrant: 6, quadrantLabel: 'Rahang Atas Kiri', archX: 315, archY: 100, archRot: 15 },
    { fdi: '63', name: 'Caninus Susu Atas Kiri', latinName: 'Dens Deciduus Caninus Sup. Sin.', type: 'canine', quadrant: 6, quadrantLabel: 'Rahang Atas Kiri', archX: 360, archY: 145, archRot: 30 },
    { fdi: '64', name: 'Molar 1 Susu Atas Kiri', latinName: 'Dens Deciduus Molaris 1 Sup. Sin.', type: 'molar', quadrant: 6, quadrantLabel: 'Rahang Atas Kiri', archX: 395, archY: 210, archRot: 55 },
    { fdi: '65', name: 'Molar 2 Susu Atas Kiri', latinName: 'Dens Deciduus Molaris 2 Sup. Sin.', type: 'molar', quadrant: 6, quadrantLabel: 'Rahang Atas Kiri', archX: 415, archY: 280, archRot: 80 },

    // Kuadran 7: Rahang Bawah Kiri
    { fdi: '75', name: 'Molar 2 Susu Bawah Kiri', latinName: 'Dens Deciduus Molaris 2 Inf. Sin.', type: 'molar', quadrant: 7, quadrantLabel: 'Rahang Bawah Kiri', archX: 415, archY: 440, archRot: 100 },
    { fdi: '74', name: 'Molar 1 Susu Bawah Kiri', latinName: 'Dens Deciduus Molaris 1 Inf. Sin.', type: 'molar', quadrant: 7, quadrantLabel: 'Rahang Bawah Kiri', archX: 395, archY: 510, archRot: 125 },
    { fdi: '73', name: 'Caninus Susu Bawah Kiri', latinName: 'Dens Deciduus Caninus Inf. Sin.', type: 'canine', quadrant: 7, quadrantLabel: 'Rahang Bawah Kiri', archX: 360, archY: 575, archRot: 150 },
    { fdi: '72', name: 'Incisivus 2 Susu Bawah Kiri', latinName: 'Dens Deciduus Incisivus Lat. Inf. Sin.', type: 'incisor', quadrant: 7, quadrantLabel: 'Rahang Bawah Kiri', archX: 315, archY: 620, archRot: 165 },
    { fdi: '71', name: 'Incisivus 1 Susu Bawah Kiri', latinName: 'Dens Deciduus Incisivus Med. Inf. Sin.', type: 'incisor', quadrant: 7, quadrantLabel: 'Rahang Bawah Kiri', archX: 275, archY: 640, archRot: 180 },

    // Kuadran 8: Rahang Bawah Kanan
    { fdi: '81', name: 'Incisivus 1 Susu Bawah Kanan', latinName: 'Dens Deciduus Incisivus Med. Inf. Dex.', type: 'incisor', quadrant: 8, quadrantLabel: 'Rahang Bawah Kanan', archX: 245, archY: 640, archRot: 180 },
    { fdi: '82', name: 'Incisivus 2 Susu Bawah Kanan', latinName: 'Dens Deciduus Incisivus Lat. Inf. Dex.', type: 'incisor', quadrant: 8, quadrantLabel: 'Rahang Bawah Kanan', archX: 205, archY: 620, archRot: 195 },
    { fdi: '83', name: 'Caninus Susu Bawah Kanan', latinName: 'Dens Deciduus Caninus Inf. Dex.', type: 'canine', quadrant: 8, quadrantLabel: 'Rahang Bawah Kanan', archX: 160, archY: 575, archRot: 210 },
    { fdi: '84', name: 'Molar 1 Susu Bawah Kanan', latinName: 'Dens Deciduus Molaris 1 Inf. Dex.', type: 'molar', quadrant: 8, quadrantLabel: 'Rahang Bawah Kanan', archX: 125, archY: 510, archRot: 235 },
    { fdi: '85', name: 'Molar 2 Susu Bawah Kanan', latinName: 'Dens Deciduus Molaris 2 Inf. Dex.', type: 'molar', quadrant: 8, quadrantLabel: 'Rahang Bawah Kanan', archX: 105, archY: 440, archRot: 260 },
];

export const TOOTH_TYPES_INFO: Record<ToothType, {
    title: string;
    latin: string;
    countAdult: number;
    description: string;
}> = {
    incisor: {
        title: 'Gigi Seri (Incisivus)',
        latin: 'Dentes Incisivi',
        countAdult: 8,
        description: 'Bentuk pahat tajam berakar tunggal untuk memotong dan menggigit makanan.',
    },
    canine: {
        title: 'Gigi Taring (Caninus)',
        latin: 'Dentes Canini',
        countAdult: 4,
        description: 'Bentuk runcing dengan akar terpanjang untuk merobek dan menahan makanan.',
    },
    premolar: {
        title: 'Geraham Depan (Premolar)',
        latin: 'Dentes Praemolares',
        countAdult: 8,
        description: 'Memiliki 2 tonjolan (bikuspid) dan akar ganda/tunggal untuk meremukkan makanan.',
    },
    molar: {
        title: 'Geraham Belakang (Molar)',
        latin: 'Dentes Molares',
        countAdult: 12,
        description: 'Permukaan oklusal luas dengan 4-5 tonjolan dan 2-3 akar untuk menggiling makanan.',
    },
};

export const FINDING_CLASSES: Record<string, string> = {
    sound: 'tooth-sound',
    caries: 'tooth-caries',
    missing: 'tooth-missing',
    unerupted: 'tooth-unerupted',
    fractured_crown: 'tooth-fractured_crown',
    root_remnant: 'tooth-root_remnant',
    restored: 'tooth-restored',
};

export function OdontogramFdi({
    dentition,
    findings,
    selectedTooth,
    disabled = false,
    onSelect,
    findingLabels = {},
    surfaceLabels = {},
}: OdontogramProps) {
    const [hoveredTooth, setHoveredTooth] = useState<string | null>(null);
    const [activeTypeFilter, setActiveTypeFilter] = useState<ToothType | null>(null);
    const teethList = dentition === 'adult' ? ADULT_TEETH : PRIMARY_TEETH;
    const selectedMeta = teethList.find((t) => t.fdi === selectedTooth);
    const hoveredMeta = teethList.find((t) => t.fdi === hoveredTooth);
    const activeMeta = hoveredMeta ?? selectedMeta;
    const activeFindings = activeMeta ? findings.filter((finding) => finding.toothFdi === activeMeta.fdi) : [];

    function getToothFinding(fdi: string): DentalFinding | undefined {
        const priority: Record<string, number> = { missing: 6, root_remnant: 5, fractured_crown: 4, caries: 3, restored: 2, unerupted: 1, sound: 0 };

        return findings
            .filter((item) => item.toothFdi === fdi)
            .sort((first, second) => (priority[second.findingCode] ?? 0) - (priority[first.findingCode] ?? 0))[0];
    }

    function getToothClass(fdi: string, type: ToothType): string {
        const finding = getToothFinding(fdi);
        const baseClass = finding ? FINDING_CLASSES[finding.findingCode] ?? 'tooth-unrecorded' : 'tooth-unrecorded';
        const isSelected = selectedTooth === fdi;
        const isHovered = hoveredTooth === fdi;
        const isTypeMatch = activeTypeFilter === type;
        const isDimmed = activeTypeFilter !== null && !isTypeMatch;

        return `${baseClass} ${isSelected ? 'is-selected' : ''} ${isHovered ? 'is-hovered' : ''} ${isTypeMatch ? 'is-type-match' : ''} ${isDimmed ? 'opacity-35' : 'opacity-100'}`;
    }

    function handleToothClick(fdi: string) {
        if (!disabled) {
            onSelect(fdi);
        }
    }

    return (
        <div className="odontogram-fdi space-y-4">
            {/* Layout Utama: 2 Kolom Seimbang (Lengkung Gigi di Kiri, Visualisasi Tipe & Oklusi di Kanan) */}
            <div className="grid gap-5 xl:grid-cols-12">
                {/* Panel Kiri: Peta Lengkung Gigi FDI (Dental Arch) - 7 Kolom */}
                <div className="rounded-2xl border border-neutral-200/90 bg-surface p-4 shadow-xs transition-colors sm:p-5 xl:col-span-7">
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-neutral-100 pb-3">
                        <div>
                            <div className="flex items-center gap-2">
                                <h4 className="text-base font-bold tracking-tight text-neutral-900">
                                    Lengkung Oklusal FDI ({dentition === 'adult' ? '32 Gigi Permanen' : '20 Gigi Desidui'})
                                </h4>
                                <span className="rounded-md bg-neutral-100 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-neutral-700">
                                    ISO 3950
                                </span>
                            </div>
                            <p className="mt-1 text-sm leading-6 text-neutral-600">
                                Klik gigi untuk menautkan temuan diagnosis dan tindakan medis. Sisi kiri peta = sisi kanan pasien.
                            </p>
                        </div>
                        {activeTypeFilter && (
                            <Button
                                onClick={() => setActiveTypeFilter(null)}
                                size="sm"
                                type="button"
                                variant="ghost"
                                className="h-7 text-xs text-neutral-600 hover:text-neutral-900"
                            >
                                Reset filter ({activeTypeFilter})
                            </Button>
                        )}
                    </div>

                    {/* SVG Diagram Lengkung Gigi */}
                    <div className="relative mx-auto flex items-center justify-center overflow-hidden">
                        <svg
                            aria-label={`Peta odontogram FDI ${dentition === 'adult' ? 'permanen 32 gigi' : 'desidui 20 gigi'}`}
                            className="odontogram-fdi-map h-auto w-full max-w-[530px] select-none"
                            role="group"
                            viewBox="0 0 520 720"
                        >
                            {/* Garis Pembagi Kuadran (Midline & Bidang Oklusal) */}
                            <line className="arch-divider" x1="260" y1="50" x2="260" y2="670" />
                            <line className="arch-divider" x1="70" y1="360" x2="450" y2="360" />

                            {/* Label Kuadran Medis */}
                            <g className="arch-label" fontSize="13" fontWeight="700" textAnchor="middle">
                                <text x="260" y="28">RAHANG ATAS · MAXILLA</text>
                                <text x="260" y="704">RAHANG BAWAH · MANDIBULA</text>
                                <text x="135" y="354">KANAN · DEXTER</text>
                                <text x="385" y="354">KIRI · SINISTER</text>
                            </g>

                            {/* Lengkung Panduan dan Panah Sirkulasi Arah Jarum Jam Sesuai Referensi Gambar */}
                            <path
                                className="arch-loop-path"
                                d="M 140 330 C 140 215, 192 125, 260 125 C 328 125, 380 215, 380 330 L 380 390 C 380 505, 328 595, 260 595 C 192 595, 140 505, 140 390"
                            />
                            {/* Ujung Panah (Arrowhead) di Lengkung Kiri Mengarah ke Atas */}
                            <path
                                className="arch-arrowhead"
                                d="M 134 397 L 140 376 L 146 397 Z"
                            />

                            {/* Elemen Setiap Gigi pada Lengkung */}
                            {teethList.map((tooth) => {
                                const isSelected = selectedTooth === tooth.fdi;
                                const toothFindings = findings.filter((item) => item.toothFdi === tooth.fdi);
                                const toothClassName = getToothClass(tooth.fdi, tooth.type);
                                const findingSummary = toothFindings.length
                                    ? toothFindings.map((finding) => `${findingLabels[finding.findingCode] ?? finding.findingCode} (${surfaceLabels[finding.surface] ?? finding.surface})`).join('; ')
                                    : 'Belum ada temuan';

                                // Hitung posisi teks nomor FDI di luar gigi secara proporsional
                                const dx = tooth.archX - 260;
                                const dy = tooth.archY - 360;
                                const len = Math.sqrt(dx * dx + dy * dy) || 1;
                                const nx = dx / len;
                                const ny = dy / len;
                                const numDist = tooth.fdi.length > 2 ? 38 : 34;
                                const numX = tooth.archX + nx * numDist;
                                const numY = tooth.archY + ny * numDist + 1;

                                return (
                                    <g
                                        aria-label={`Gigi FDI ${tooth.fdi} (${tooth.name}): ${findingSummary}`}
                                        aria-pressed={isSelected}
                                        className={`tooth-interactive ${toothClassName} ${disabled ? 'cursor-default' : 'cursor-pointer'}`}
                                        key={tooth.fdi}
                                        onClick={() => handleToothClick(tooth.fdi)}
                                        onMouseEnter={() => setHoveredTooth(tooth.fdi)}
                                        onMouseLeave={() => setHoveredTooth(null)}
                                        onKeyDown={(e) => {
                                            if (!disabled && (e.key === 'Enter' || e.key === ' ')) {
                                                e.preventDefault();
                                                handleToothClick(tooth.fdi);
                                            }
                                        }}
                                        role="button"
                                        tabIndex={disabled ? -1 : 0}
                                    >
                                        <title>{`Gigi FDI ${tooth.fdi} · ${tooth.name} · ${findingSummary}`}</title>

                                        {/* Highlight Ring Saat Dipilih */}
                                        {isSelected && (
                                            <circle
                                                cx={tooth.archX}
                                                cy={tooth.archY}
                                                r="25"
                                                fill="none"
                                                stroke="currentColor"
                                                strokeWidth="2.2"
                                                strokeDasharray="4 3"
                                                className="animate-pulse text-neutral-900"
                                            />
                                        )}

                                        {/* Bentuk Anatomi Oklusal Gigi */}
                                        <g transform={`translate(${tooth.archX} ${tooth.archY}) rotate(${tooth.archRot})`}>
                                            <ArchToothShape type={tooth.type} />
                                        </g>

                                        {/* Nomor Gigi FDI di Luar Lengkung */}
                                        <text
                                            className={`fdi-number ${isSelected ? 'is-active' : ''}`}
                                            x={numX}
                                            y={numY}
                                        >
                                            {tooth.fdi}
                                        </text>
                                    </g>
                                );
                            })}
                        </svg>
                    </div>

                    {/* Catatan Kaki Lengkung */}
                    <div className="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-neutral-200 pt-3 text-sm leading-5 text-neutral-600">
                        <span>Arah panah: Loop universal kuadran 1 → 2 → 3 → 4</span>
                        <span>Klik jenis gigi pada panel kanan untuk highlight</span>
                    </div>
                </div>

                {/* Panel Kanan: Visualisasi Tipe Gigi (Atas) & Oklusi Rahang Mulut (Bawah) - 5 Kolom */}
                <div className="flex flex-col gap-5 xl:col-span-5">
                    {/* Bagian Kanan Atas: 4 Tipe Anatomi Gigi (Akar & Mahkota) Sesuai Referensi Gambar */}
                    <div className="rounded-2xl border border-neutral-200/90 bg-surface p-4 shadow-xs sm:p-5">
                        <div className="mb-3 flex items-center justify-between border-b border-neutral-100 pb-2.5">
                            <div>
                                <h4 className="text-sm font-bold tracking-tight text-neutral-900">
                                    Morfologi Tipe Gigi (Akar & Mahkota)
                                </h4>
                                <p className="text-xs text-neutral-500">
                                    Struktur anatomis 4 kelompok gigi sesuai referensi
                                </p>
                            </div>
                            <span className="text-[11px] font-medium text-neutral-400">
                                Interaktif
                            </span>
                        </div>

                        {/* 4 Kolom Ilustrasi Anatomi Gigi */}
                        <div className="grid grid-cols-4 gap-2">
                            {(['incisor', 'canine', 'premolar', 'molar'] as ToothType[]).map((type) => {
                                const info = TOOTH_TYPES_INFO[type];
                                const isCurrentSelectedType = selectedMeta?.type === type;
                                const isFilterActive = activeTypeFilter === type;

                                return (
                                    <button
                                        key={type}
                                        type="button"
                                        onClick={() => setActiveTypeFilter((cur) => (cur === type ? null : type))}
                                        className={`group flex flex-col items-center rounded-xl border p-2 transition-all ${
                                            isFilterActive
                                                ? 'border-neutral-900 bg-neutral-100 shadow-xs ring-1 ring-neutral-900'
                                                : isCurrentSelectedType
                                                  ? 'border-neutral-400 bg-neutral-100/60'
                                                  : 'border-neutral-200/80 bg-neutral-50/50 hover:border-neutral-300 hover:bg-neutral-100/70'
                                        }`}
                                        title={`Klik untuk menyorot seluruh ${info.title} pada peta`}
                                    >
                                        <div className="flex h-24 w-full items-center justify-center">
                                            <ToothTypeIllustration type={type} />
                                        </div>
                                        <span className="mt-1 text-center font-sans text-xs font-bold text-neutral-900">
                                            {type === 'incisor' ? 'Seri' : type === 'canine' ? 'Taring' : type === 'premolar' ? 'Premolar' : 'Molar'}
                                        </span>
                                        <span className="font-mono text-[10px] text-neutral-500">
                                            {info.countAdult} gigi
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {/* Bagian Kanan Bawah: Tampilan Senyum / Oklusi Rahang & Gigi Sesuai Referensi Gambar */}
                    <div className="flex-1 rounded-2xl border border-neutral-200/90 bg-surface p-4 shadow-xs sm:p-5">
                        <div className="mb-2 flex items-center justify-between border-b border-neutral-100 pb-2.5">
                            <div>
                                <h4 className="text-sm font-bold tracking-tight text-neutral-900">
                                    Tampilan Oklusi Rahang & Gingiva
                                </h4>
                                <p className="text-xs text-neutral-500">
                                    Visual anterior tampak depan. Sinkron otomatis dengan status temuan klinis.
                                </p>
                            </div>
                            <span className="rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] font-medium text-neutral-700">
                                Gingiva & Gigi
                            </span>
                        </div>

                        {/* SVG Ilustrasi Lengkap Mulut / Oklusi Rahang Atas & Bawah */}
                        <div className="relative mx-auto flex items-center justify-center overflow-hidden py-1">
                            <svg
                                aria-label="Visual oklusi gigi dan gingiva rahang tampak anterior"
                                className="h-auto w-full max-w-[460px] select-none"
                                viewBox="0 0 540 330"
                            >
                                {/* Gingiva Rahang Atas (Gusi Atas) */}
                                <path
                                    className="gingiva-tissue"
                                    d="M 30 75 C 90 20, 180 15, 270 15 C 360 15, 450 20, 510 75 C 525 95, 515 120, 485 115 C 460 112, 440 125, 420 126 C 395 124, 375 120, 350 118 C 330 114, 305 108, 270 106 C 235 108, 210 114, 190 118 C 165 120, 145 124, 120 126 C 100 125, 80 112, 55 115 C 25 120, 15 95, 30 75 Z"
                                />
                                {/* Kontur Lipatan Gingiva Atas */}
                                <path className="gingiva-contour" d="M 120 65 Q 270 35 420 65" />
                                <path className="gingiva-contour" d="M 180 85 Q 270 65 360 85" />
                                <path className="gingiva-contour" d="M 270 20 L 270 65" />

                                {/* Gigi Rahang Atas (18 s/d 28) */}
                                {dentition === 'adult' ? (
                                    <g>
                                        <SmileUpperTooth fdi="18" d="M 87 130 C 89 123, 106 123, 108 130 L 108 145 L 97 146 L 87 145 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileUpperTooth fdi="17" d="M 109 127 C 111 120, 130 120, 132 127 L 132 146 L 120 148 L 109 146 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileUpperTooth fdi="16" d="M 133 125 C 135 117, 153 117, 155 125 L 155 148 L 144 151 L 133 148 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileUpperTooth fdi="15" d="M 156 122 C 158 114, 174 114, 176 122 L 176 150 L 166 154 L 156 150 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileUpperTooth fdi="14" d="M 177 118 C 180 110, 196 110, 198 118 L 198 152 L 187 156 L 177 152 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileUpperTooth fdi="13" d="M 199 114 C 202 105, 220 105, 222 114 L 222 154 L 210 162 L 199 154 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileUpperTooth fdi="12" d="M 223 110 C 226 102, 241 102, 244 110 L 244 156 C 244 158, 223 158, 223 156 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileUpperTooth fdi="11" d="M 245 106 C 248 98, 267 98, 270 106 L 270 159 C 270 161, 245 161, 245 159 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />

                                        <SmileUpperTooth fdi="21" d="M 271 106 C 274 98, 292 98, 295 106 L 295 159 C 295 161, 271 161, 271 159 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileUpperTooth fdi="22" d="M 296 110 C 299 102, 314 102, 317 110 L 317 156 C 317 158, 296 158, 296 156 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileUpperTooth fdi="23" d="M 318 114 C 320 105, 338 105, 341 114 L 341 154 L 330 162 L 318 154 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileUpperTooth fdi="24" d="M 342 118 C 344 110, 360 110, 363 118 L 363 152 L 353 156 L 342 152 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileUpperTooth fdi="25" d="M 364 122 C 366 114, 382 114, 384 122 L 384 150 L 374 154 L 364 150 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileUpperTooth fdi="26" d="M 385 125 C 387 117, 405 117, 407 125 L 407 148 L 396 151 L 385 148 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileUpperTooth fdi="27" d="M 408 127 C 410 120, 429 120, 431 127 L 431 146 L 420 148 L 408 146 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileUpperTooth fdi="28" d="M 432 130 C 434 123, 451 123, 453 130 L 453 145 L 442 146 L 432 145 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                    </g>
                                ) : (
                                    <g>
                                        <SmileUpperTooth fdi="55" d="M 133 125 C 135 117, 160 117, 162 125 L 162 149 L 147 152 L 133 149 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileUpperTooth fdi="54" d="M 165 120 C 168 112, 195 112, 198 120 L 198 153 L 181 156 L 165 153 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileUpperTooth fdi="53" d="M 201 114 C 204 105, 222 105, 224 114 L 224 154 L 212 162 L 201 154 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileUpperTooth fdi="52" d="M 226 110 C 229 102, 244 102, 247 110 L 247 156 C 247 158, 226 158, 226 156 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileUpperTooth fdi="51" d="M 248 106 C 251 98, 269 98, 270 106 L 270 159 L 248 159 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />

                                        <SmileUpperTooth fdi="61" d="M 271 106 C 272 98, 290 98, 293 106 L 293 159 L 271 159 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileUpperTooth fdi="62" d="M 295 110 C 298 102, 313 102, 316 110 L 316 156 C 316 158, 295 158, 295 156 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileUpperTooth fdi="63" d="M 318 114 C 320 105, 338 105, 341 114 L 341 154 L 330 162 L 318 154 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileUpperTooth fdi="64" d="M 344 120 C 347 112, 374 112, 377 120 L 377 153 L 361 156 L 344 153 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileUpperTooth fdi="65" d="M 380 125 C 382 117, 407 117, 409 125 L 409 149 L 394 152 L 380 149 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                    </g>
                                )}

                                {/* Gigi Rahang Bawah (48 s/d 38) */}
                                {dentition === 'adult' ? (
                                    <g>
                                        <SmileLowerTooth fdi="48" d="M 103 148 L 114 145 L 125 148 L 125 188 C 122 196, 106 196, 103 188 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileLowerTooth fdi="47" d="M 126 150 L 137 146 L 148 150 L 148 190 C 145 198, 129 198, 126 190 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileLowerTooth fdi="46" d="M 149 152 L 160 148 L 170 152 L 170 192 C 167 200, 152 200, 149 192 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileLowerTooth fdi="45" d="M 171 154 L 181 150 L 191 154 L 191 195 C 188 203, 174 203, 171 195 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileLowerTooth fdi="44" d="M 192 156 L 202 152 L 212 156 L 212 198 C 209 206, 195 206, 192 198 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileLowerTooth fdi="43" d="M 213 158 L 223 153 L 232 158 L 232 202 C 229 210, 216 210, 213 202 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileLowerTooth fdi="42" d="M 233 159 L 251 159 L 251 204 C 248 212, 236 212, 233 204 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileLowerTooth fdi="41" d="M 252 161 L 270 161 L 270 206 C 267 214, 255 214, 252 206 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />

                                        <SmileLowerTooth fdi="31" d="M 271 161 L 289 161 L 289 206 C 286 214, 274 214, 271 206 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileLowerTooth fdi="32" d="M 290 159 L 308 159 L 308 204 C 305 212, 293 212, 290 204 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileLowerTooth fdi="33" d="M 309 158 L 319 153 L 328 158 L 328 202 C 325 210, 312 210, 309 202 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileLowerTooth fdi="34" d="M 329 156 L 339 152 L 349 156 L 349 198 C 346 206, 332 206, 329 198 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileLowerTooth fdi="35" d="M 350 154 L 360 150 L 370 154 L 370 195 C 367 203, 353 203, 350 195 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileLowerTooth fdi="36" d="M 371 152 L 382 148 L 392 152 L 392 192 C 389 200, 374 200, 371 192 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileLowerTooth fdi="37" d="M 393 150 L 404 146 L 415 150 L 415 190 C 412 198, 396 198, 393 190 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileLowerTooth fdi="38" d="M 416 148 L 427 145 L 438 148 L 438 188 C 435 196, 419 196, 416 188 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                    </g>
                                ) : (
                                    <g>
                                        <SmileLowerTooth fdi="85" d="M 133 152 L 148 148 L 163 152 L 163 192 C 160 200, 136 200, 133 192 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileLowerTooth fdi="84" d="M 166 155 L 182 151 L 198 155 L 198 196 C 195 204, 169 204, 166 196 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileLowerTooth fdi="83" d="M 201 158 L 213 153 L 224 158 L 224 202 C 221 210, 204 210, 201 202 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileLowerTooth fdi="82" d="M 226 159 L 247 159 L 247 204 C 244 212, 229 212, 226 204 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileLowerTooth fdi="81" d="M 249 161 L 270 161 L 270 206 C 267 214, 252 214, 249 206 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />

                                        <SmileLowerTooth fdi="71" d="M 271 161 L 292 161 L 292 206 C 289 214, 274 214, 271 206 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileLowerTooth fdi="72" d="M 294 159 L 315 159 L 315 204 C 312 212, 297 212, 294 204 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileLowerTooth fdi="73" d="M 317 158 L 328 153 L 340 158 L 340 202 C 337 210, 320 210, 317 202 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileLowerTooth fdi="74" d="M 343 155 L 359 151 L 375 155 L 375 196 C 372 204, 346 204, 343 196 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                        <SmileLowerTooth fdi="75" d="M 378 152 L 393 148 L 408 152 L 408 192 C 405 200, 381 200, 378 192 Z" getToothClass={getToothClass} onClick={handleToothClick} onHover={setHoveredTooth} selectedTooth={selectedTooth} disabled={disabled} />
                                    </g>
                                )}

                                {/* Gingiva Rahang Bawah (Gusi Bawah) */}
                                <path
                                    className="gingiva-tissue"
                                    d="M 55 205 C 80 205, 100 194, 120 192 C 145 194, 165 198, 190 200 C 210 204, 235 210, 270 212 C 305 210, 330 204, 350 200 C 375 198, 395 194, 420 192 C 440 194, 460 205, 485 205 C 515 200, 525 225, 510 245 C 450 300, 360 310, 270 310 C 180 310, 90 300, 30 245 C 15 225, 25 200, 55 205 Z"
                                />
                                {/* Kontur Lipatan Gingiva Bawah */}
                                <path className="gingiva-contour" d="M 120 250 Q 270 280 420 250" />
                                <path className="gingiva-contour" d="M 180 230 Q 270 250 360 230" />
                                <path className="gingiva-contour" d="M 270 250 L 270 295" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            {/* Panel Ringkasan Gigi Terpilih (Active Tooth Detail Banner) */}
            <div className="flex flex-col justify-between gap-3 rounded-xl border border-neutral-200/90 bg-neutral-50/70 p-3.5 transition-colors sm:flex-row sm:items-center">
                <div className="flex items-center gap-3">
                    <div className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-neutral-900 font-mono text-base font-black text-neutral-50">
                        {activeMeta ? activeMeta.fdi : '—'}
                    </div>
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="text-sm font-bold text-neutral-900">
                                {activeMeta ? activeMeta.name : 'Pilih gigi pada diagram atau senyum rahang'}
                            </span>
                            {activeMeta?.universal && (
                                <Badge variant="default" className="font-mono text-xs">
                                    Universal #{activeMeta.universal}
                                </Badge>
                            )}
                            {activeMeta && (
                                <span className="text-xs text-neutral-500">
                                    · {activeMeta.quadrantLabel}
                                </span>
                            )}
                        </div>
                        <p className="mt-0.5 text-xs text-neutral-600">
                            {activeFindings.length > 0 ? (
                                <span className="flex flex-wrap items-center gap-x-2 gap-y-1 font-medium text-neutral-800">
                                    <strong className="font-semibold">{activeFindings.length} temuan pada gigi ini:</strong>
                                    {activeFindings.map((finding) => (
                                        <span className="rounded-md bg-neutral-100 px-2 py-0.5 text-neutral-700" key={`${finding.surface}-${finding.findingCode}`}>
                                            {findingLabels[finding.findingCode] ?? finding.findingCode} · {surfaceLabels[finding.surface] ?? finding.surface}{finding.notes ? ` · ${finding.notes}` : ''}
                                        </span>
                                    ))}
                                </span>
                            ) : (
                                <span>{activeMeta ? 'Belum ada temuan tercatat pada gigi ini.' : 'Pilih gigi pada peta untuk melihat rincian temuan.'}</span>
                            )}
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-2 sm:self-center">
                    {selectedMeta && (
                        <span className="inline-flex items-center gap-1 rounded-md bg-neutral-100 px-2 py-1 text-xs font-semibold text-neutral-700">
                            <Check className="size-3.5" />
                            Gigi {selectedMeta.fdi} aktif di form
                        </span>
                    )}
                </div>
            </div>

            {/* Legenda status memakai warna netral; merah menandai karies. */}
            <div aria-label="Legenda status klinis odontogram" className="flex flex-wrap items-center justify-center gap-2 text-sm">
                {[
                    { label: 'Belum dicatat', class: 'bg-surface border-neutral-300 dark:bg-[#262626]' },
                    { label: 'Sehat (Sound)', class: 'bg-surface border-neutral-400 dark:bg-[#262626]' },
                    { label: 'Karies (Caries)', class: 'bg-red-100 border-red-600 dark:bg-[#3b1414] dark:border-red-500' },
                    { label: 'Restorasi (Restored)', class: 'bg-neutral-200 border-neutral-600 dark:bg-[#404040]' },
                    { label: 'Hilang (Missing)', class: 'bg-transparent border-dashed border-neutral-400 dark:bg-transparent' },
                    { label: 'Belum erupsi', class: 'bg-neutral-50 border-dotted border-neutral-400 dark:bg-[#1c1c1f]' },
                    { label: 'Fraktur mahkota', class: 'bg-neutral-100 border-dashed border-neutral-700 dark:bg-[#262626]' },
                    { label: 'Sisa akar', class: 'bg-neutral-300 border-neutral-700 dark:bg-[#3f3f46]' },
                ].map((item) => (
                    <span
                        key={item.label}
                        className="inline-flex items-center gap-2 rounded-full border border-neutral-200 bg-surface px-3 py-1.5 text-sm font-medium text-neutral-700 shadow-2xs"
                    >
                        <span aria-hidden="true" className={`size-2.5 rounded-full border ${item.class}`} />
                        {item.label}
                    </span>
                ))}
            </div>
        </div>
    );
}

// Sub-komponen Anatomi Oklusal untuk Diagram Lengkung (Left Arch)
function ArchToothShape({ type }: { type: ToothType }) {
    switch (type) {
        case 'molar':
            return (
                <g>
                    {/* Bentuk Mahkota Luar Molar */}
                    <path
                        className="tooth-shape"
                        d="M -16 -13 C -13 -16, 13 -16, 16 -13 C 18 -9, 18 9, 16 13 C 13 16, -13 16, -16 13 C -18 9, -18 -9, -16 -13 Z"
                    />
                    {/* Garis Fissura Oklusal / Fossa Molar */}
                    <path
                        className="tooth-fissure"
                        d="M -10 0 C -4 -1, 4 1, 10 0 M 0 -9 C 1 -4, -1 4, 0 9 M -6 -5 C -3 -2, 0 0, 0 0 M 6 -5 C 3 -2, 0 0, 0 0 M -6 5 C -3 2, 0 0, 0 0 M 6 5 C 3 2, 0 0, 0 0"
                    />
                </g>
            );
        case 'premolar':
            return (
                <g>
                    {/* Bentuk Mahkota Luar Premolar (Bikuspid) */}
                    <path
                        className="tooth-shape"
                        d="M -12 -11 C -7 -14, 7 -14, 12 -11 C 15 -6, 15 6, 12 11 C 7 14, -7 14, -12 11 C -15 6, -15 -6, -12 -11 Z"
                    />
                    {/* Fissura Sentral Premolar */}
                    <path
                        className="tooth-fissure"
                        d="M -7 0 L 7 0 M -6 -3 L -3 0 L -6 3 M 6 -3 L 3 0 L 6 3"
                    />
                </g>
            );
        case 'canine':
            return (
                <g>
                    {/* Bentuk Mahkota Caninus (Runcing Almond/Perisai) */}
                    <path
                        className="tooth-shape"
                        d="M 0 -12 C 7 -10, 11 -4, 11 2 C 10 9, 5 12, 0 13 C -5 12, -10 9, -11 2 C -11 -4, -7 -10, 0 -12 Z"
                    />
                    {/* Puncak Kuspid Caninus */}
                    <path
                        className="tooth-fissure"
                        d="M 0 -9 L 0 5 M -6 1 Q 0 6 6 1"
                    />
                </g>
            );
        case 'incisor':
        default:
            return (
                <g>
                    {/* Bentuk Mahkota Incisivus (Tepi Pahat Melengkung) */}
                    <path
                        className="tooth-shape"
                        d="M -12 -7 C -5 -9, 5 -9, 12 -7 C 13 -3, 11 5, 9 8 C 4 9, -4 9, -9 8 C -11 5, -13 -3, -12 -7 Z"
                    />
                    {/* Tepi Insisal Incisivus */}
                    <path
                        className="tooth-fissure"
                        d="M -8 -3 Q 0 -5 8 -3"
                    />
                </g>
            );
    }
}

// Sub-komponen Ilustrasi 4 Tipe Gigi (Akar + Mahkota) Sesuai Referensi Gambar Kanan Atas
function ToothTypeIllustration({ type }: { type: ToothType }) {
    switch (type) {
        case 'incisor':
            // Incisor: Mahkota pahat lurus di atas, akar tunggal meruncing ke bawah
            return (
                <svg className="size-full max-h-20 max-w-14" viewBox="0 0 70 120">
                    {/* Mahkota Gigi */}
                    <path
                        className="tooth-shape tooth-unrecorded"
                        d="M 20 18 C 26 12, 44 12, 50 18 C 53 28, 52 42, 48 50 C 44 54, 38 55, 35 55 C 32 55, 26 54, 22 50 C 18 42, 17 28, 20 18 Z"
                    />
                    {/* Akar Tunggal Panjang Meruncing */}
                    <path
                        className="tooth-shape tooth-unrecorded"
                        d="M 23 50 C 27 70, 32 95, 34 114 C 35 116, 36 116, 37 114 C 40 95, 44 70, 47 50 Z"
                    />
                    {/* Garis Shading / Pulpa */}
                    <path className="tooth-fissure" d="M 35 22 L 35 45 M 35 55 L 35 100" />
                </svg>
            );
        case 'canine':
            // Canine: Mahkota runcing konus di atas, akar tunggal sangat kokoh ke bawah
            return (
                <svg className="size-full max-h-20 max-w-14" viewBox="0 0 70 120">
                    {/* Mahkota Gigi Taring */}
                    <path
                        className="tooth-shape tooth-unrecorded"
                        d="M 35 12 C 43 20, 52 30, 50 48 C 47 53, 42 55, 35 55 C 28 55, 23 53, 20 48 C 18 30, 27 20, 35 12 Z"
                    />
                    {/* Akar Tunggal Kokoh */}
                    <path
                        className="tooth-shape tooth-unrecorded"
                        d="M 22 50 C 27 72, 33 97, 36 116 C 36.5 117.5, 38 117.5, 38.5 116 C 41 97, 45 72, 48 50 Z"
                    />
                    {/* Garis Cusp & Shading */}
                    <path className="tooth-fissure" d="M 35 16 L 35 48 M 36 55 L 37 102" />
                </svg>
            );
        case 'premolar':
            // Premolar: Mahkota bikuspid bergelombang di atas, akar bifurkasi ke bawah
            return (
                <svg className="size-full max-h-20 max-w-14" viewBox="0 0 70 120">
                    {/* Mahkota Bikuspid */}
                    <path
                        className="tooth-shape tooth-unrecorded"
                        d="M 18 20 C 22 14, 30 14, 35 18 C 40 14, 48 14, 52 20 C 55 28, 54 42, 49 50 C 44 54, 38 55, 35 55 C 32 55, 26 54, 21 50 C 16 42, 15 28, 18 20 Z"
                    />
                    {/* Akar Bercabang Ganda (Bifurkasi) */}
                    <path
                        className="tooth-shape tooth-unrecorded"
                        d="M 22 50 C 24 70, 26 90, 27 114 C 28 115.5, 31 115, 32 100 C 33 88, 37 88, 38 100 C 39 115, 42 115.5, 43 114 C 44 90, 46 70, 48 50 Z"
                    />
                    {/* Garis Shading / Cusp Division */}
                    <path className="tooth-fissure" d="M 35 20 L 35 48 M 27 60 L 28 95 M 43 60 L 42 95" />
                </svg>
            );
        case 'molar':
        default:
            // Molar: Mahkota luas 4 tonjolan di atas, 3 akar trifurkasi menyebar ke bawah
            return (
                <svg className="size-full max-h-20 max-w-14" viewBox="0 0 70 120">
                    {/* Mahkota Molar Luas */}
                    <path
                        className="tooth-shape tooth-unrecorded"
                        d="M 15 18 C 21 12, 30 14, 35 12 C 40 14, 49 12, 55 18 C 58 26, 57 42, 52 50 C 46 55, 39 56, 35 56 C 31 56, 24 55, 18 50 C 13 42, 12 26, 15 18 Z"
                    />
                    {/* Tiga Akar Trifurkasi Menyebar */}
                    <path
                        className="tooth-shape tooth-unrecorded"
                        d="M 19 50 C 20 72, 21 94, 21 112 C 22 113.5, 25 113, 27 96 C 28 85, 31 82, 33 94 C 34 112, 36 115, 37 112 C 38 94, 40 85, 42 96 C 44 113, 47 113.5, 48 112 C 49 94, 50 72, 51 50 Z"
                    />
                    {/* Garis Shading / Cusp Division */}
                    <path className="tooth-fissure" d="M 35 16 L 35 48 M 22 60 L 22 95 M 48 60 L 48 95" />
                </svg>
            );
    }
}

// Sub-komponen Gigi Atas pada Tampilan Oklusi Senyum
function SmileUpperTooth({
    fdi,
    d,
    getToothClass,
    onClick,
    onHover,
    selectedTooth,
    disabled,
}: {
    fdi: string;
    d: string;
    getToothClass: (fdi: string, type: ToothType) => string;
    onClick: (fdi: string) => void;
    onHover: (fdi: string | null) => void;
    selectedTooth: string;
    disabled: boolean;
}) {
    const isSelected = selectedTooth === fdi;
    const type: ToothType = fdi.endsWith('1') || fdi.endsWith('2') ? 'incisor' : fdi.endsWith('3') ? 'canine' : fdi.endsWith('4') || fdi.endsWith('5') ? 'premolar' : 'molar';
    const toothClassName = getToothClass(fdi, type);

    return (
        <g
            className={`tooth-interactive ${toothClassName} ${disabled ? 'cursor-default' : 'cursor-pointer'}`}
            onClick={() => onClick(fdi)}
            onMouseEnter={() => onHover(fdi)}
            onMouseLeave={() => onHover(null)}
            role="button"
            tabIndex={disabled ? -1 : 0}
        >
            <title>{`Gigi FDI ${fdi}`}</title>
            <path className="tooth-shape" d={d} />
            {isSelected && (
                <path
                    d={d}
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2.2"
                    className="text-neutral-900"
                />
            )}
        </g>
    );
}

// Sub-komponen Gigi Bawah pada Tampilan Oklusi Senyum
function SmileLowerTooth({
    fdi,
    d,
    getToothClass,
    onClick,
    onHover,
    selectedTooth,
    disabled,
}: {
    fdi: string;
    d: string;
    getToothClass: (fdi: string, type: ToothType) => string;
    onClick: (fdi: string) => void;
    onHover: (fdi: string | null) => void;
    selectedTooth: string;
    disabled: boolean;
}) {
    const isSelected = selectedTooth === fdi;
    const type: ToothType = fdi.endsWith('1') || fdi.endsWith('2') ? 'incisor' : fdi.endsWith('3') ? 'canine' : fdi.endsWith('4') || fdi.endsWith('5') ? 'premolar' : 'molar';
    const toothClassName = getToothClass(fdi, type);

    return (
        <g
            className={`tooth-interactive ${toothClassName} ${disabled ? 'cursor-default' : 'cursor-pointer'}`}
            onClick={() => onClick(fdi)}
            onMouseEnter={() => onHover(fdi)}
            onMouseLeave={() => onHover(null)}
            role="button"
            tabIndex={disabled ? -1 : 0}
        >
            <title>{`Gigi FDI ${fdi}`}</title>
            <path className="tooth-shape" d={d} />
            {isSelected && (
                <path
                    d={d}
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2.2"
                    className="text-neutral-900"
                />
            )}
        </g>
    );
}
