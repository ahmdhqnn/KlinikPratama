interface FindingLocation {
    toothFdi: string;
    surface: string;
}

export function selectedOdontogramTeeth(findings: FindingLocation[], selectedKeys: string[]): string[] {
    const keys = new Set(selectedKeys);
    return [...new Set(findings.filter((finding) => keys.has(`${finding.toothFdi}-${finding.surface}`)).map((finding) => finding.toothFdi))];
}
