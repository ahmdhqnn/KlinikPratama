import type { ComponentProps } from 'react';
import { Select } from '@/components/ui/select';

const religions = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu', 'Kepercayaan', 'Lainnya'];

export function ReligionSelect({ value, ...props }: Omit<ComponentProps<typeof Select>, 'children'>) {
    const current = String(value ?? '');
    return <Select {...props} value={current}>
        <option value="">Pilih agama</option>
        {current && !religions.includes(current) && <option value={current}>{current}</option>}
        {religions.map((religion) => <option key={religion} value={religion}>{religion}</option>)}
    </Select>;
}
