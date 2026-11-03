interface FindingLocation {
    toothFdi: string;
    surface: string;
}

export function selectedOdontogramTeeth(findings: FindingLocation[], selectedKeys: string[]): string[] {
    const keys = new Set(selectedKeys);
    return [...new Set(findings.filter((finding) => keys.has(`${finding.toothFdi}-${finding.surface}`)).map((finding) => finding.toothFdi))];
}

export function paginateOdontogram<T extends FindingLocation & { findingCode: string; details?: Record<string, string> | null }>(findings: T[], page: number) {
    const priority = (finding: T): number => {
        if (finding.findingCode === 'sound') return 4;
        if (['acute_abscess', 'chronic_abscess'].includes(finding.details?.periapical ?? '')) return 0;
        if (finding.details?.pulpal === 'necrosis' || finding.findingCode === 'non_vital') return 1;
        if (['caries', 'fractured_crown', 'root_remnant'].includes(finding.findingCode)) return 2;
        return 3;
    };
    const sorted = [...findings].sort((a, b) => priority(a) - priority(b) || a.toothFdi.localeCompare(b.toothFdi, 'id', { numeric: true }) || a.surface.localeCompare(b.surface));
    const lastPage = Math.max(1, Math.ceil(sorted.length / 4));
    const currentPage = Math.max(1, Math.min(page, lastPage));
    const start = (currentPage - 1) * 4;
    return { items: sorted.slice(start, start + 4), currentPage, lastPage, total: sorted.length, from: sorted.length ? start + 1 : null, to: sorted.length ? Math.min(start + 4, sorted.length) : null, previousUrl: null, nextUrl: null };
}
