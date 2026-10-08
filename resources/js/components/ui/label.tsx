import * as LabelPrimitive from '@radix-ui/react-label';
import { cn } from '@/lib/utils';

export function Label({ className, ...props }: LabelPrimitive.LabelProps) {
    return (
        <LabelPrimitive.Root
            className={cn('block text-sm font-medium leading-none text-neutral-700 peer-disabled:cursor-not-allowed peer-disabled:opacity-70', className)}
            {...props}
        />
    );
}
