import { LoaderCircle } from 'lucide-react';
import { cn } from '@/lib/utils';

export function Spinner({ className, label = 'Memuat' }: { className?: string; label?: string }) {
    return <LoaderCircle aria-label={label} className={cn('size-4 animate-spin', className)} role="status" />;
}
