import * as DialogPrimitive from '@radix-ui/react-dialog';
import type { ComponentProps } from 'react';
import { X } from 'lucide-react';
import { cn } from '@/lib/utils';

export const Drawer = DialogPrimitive.Root;
export const DrawerTrigger = DialogPrimitive.Trigger;
export const DrawerClose = DialogPrimitive.Close;

export function DrawerContent({ className, children, side = 'left', ...props }: ComponentProps<typeof DialogPrimitive.Content> & { side?: 'left' | 'right' | 'top' | 'bottom' }) {
    const placement = {
        left: 'inset-y-0 left-0 h-full w-[min(18rem,calc(100vw-2rem))] border-r data-[state=closed]:-translate-x-full data-[state=open]:translate-x-0',
        right: 'inset-y-0 right-0 h-full w-[min(18rem,calc(100vw-2rem))] border-l data-[state=closed]:translate-x-full data-[state=open]:translate-x-0',
        top: 'inset-x-0 top-0 max-h-[85vh] w-full border-b data-[state=closed]:-translate-y-full data-[state=open]:translate-y-0',
        bottom: 'inset-x-0 bottom-0 max-h-[85vh] w-full border-t data-[state=closed]:translate-y-full data-[state=open]:translate-y-0',
    }[side];

    return (
        <DialogPrimitive.Portal>
            <DialogPrimitive.Overlay className="fixed inset-0 z-50 bg-inverse/50 backdrop-blur-[2px] data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0" />
            <DialogPrimitive.Content
                className={cn('fixed z-50 flex flex-col border-neutral-200 bg-surface shadow-2xl outline-none transition-transform duration-200 ease-out data-[state=closed]:duration-200 data-[state=open]:duration-300', placement, className)}
                {...props}
            >
                {children}
                <DialogPrimitive.Close aria-label="Tutup" className="absolute right-3 top-3 rounded-md p-2 text-neutral-500 hover:bg-neutral-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-500">
                    <X aria-hidden="true" className="size-4" />
                </DialogPrimitive.Close>
            </DialogPrimitive.Content>
        </DialogPrimitive.Portal>
    );
}

export function DrawerTitle({ className, ...props }: ComponentProps<typeof DialogPrimitive.Title>) {
    return <DialogPrimitive.Title className={cn('text-base font-semibold text-neutral-950', className)} {...props} />;
}

export function DrawerDescription({ className, ...props }: ComponentProps<typeof DialogPrimitive.Description>) {
    return <DialogPrimitive.Description className={cn('text-sm text-neutral-500', className)} {...props} />;
}
