export function formatCurrency(amount: number): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    }).format(amount);
}

export function formatNumber(amount: number): string {
    return new Intl.NumberFormat('id-ID').format(amount);
}
