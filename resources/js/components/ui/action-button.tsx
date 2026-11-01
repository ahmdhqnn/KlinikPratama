import { ArrowLeftRight, Ban, ClipboardCheck, ClipboardList, DoorOpen, Download, Eye, FilePlus2, FlaskConical, HeartPulse, KeyRound, Package, PackageSearch, Pencil, Pill, Printer, ShieldCheck, Stethoscope, Syringe, Trash2, Undo2, UserCheck, UserX } from 'lucide-react';
import { cloneElement, type ComponentProps, type ReactElement, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const actionIcons = {
    activate: UserCheck,
    adjust: ClipboardList,
    cancel: Ban,
    deactivate: UserX,
    delete: Trash2,
    detail: Eye,
    download: Download,
    edit: Pencil,
    issue: FilePlus2,
    examination: Stethoscope,
    laboratory: FlaskConical,
    pharmacy: Pill,
    print: Printer,
    procedure: Syringe,
    resetPassword: KeyRound,
    return: Undo2,
    rme: HeartPulse,
    rooms: DoorOpen,
    screening: ClipboardCheck,
    stock: PackageSearch,
    supplies: Package,
    transfer: ArrowLeftRight,
    verify: ShieldCheck,
};

type ActionButtonProps = Omit<ComponentProps<typeof Button>, 'asChild' | 'children' | 'size' | 'variant'> & {
    action: keyof typeof actionIcons;
    label: string;
    disabledReason?: string | null;
} & ({
    asChild: true;
    children: ReactElement<{ children?: ReactNode }>;
} | {
    asChild?: false;
    children?: never;
});

export function ActionButton({ action, label, asChild = false, children, className, disabled, disabledReason, title, type = 'button', ...props }: ActionButtonProps) {
    const Icon = actionIcons[action];
    const icon = <Icon aria-hidden="true" className="size-4" />;
    const destructive = action === 'delete' || action === 'cancel' || action === 'deactivate';

    const explanation = disabled ? disabledReason ?? title ?? 'Aksi belum tersedia untuk status data atau hak akses Anda.' : title ?? label;
    const button = (
        <Button
            {...props}
            aria-label={props['aria-label'] ?? label}
            asChild={asChild && !disabled}
            className={cn('size-9 rounded-md p-0', destructive && 'text-red-600 hover:bg-red-50 hover:text-red-700 dark:text-red-400 dark:hover:bg-red-950/40 dark:hover:text-red-300', className)}
            disabled={disabled}
            aria-description={disabled ? explanation : undefined}
            size="icon"
            title={explanation}
            type={asChild && !disabled ? undefined : type}
            variant="secondary"
        >
            {asChild && !disabled && children ? cloneElement(children, {}, icon) : icon}
        </Button>
    );

    return disabled ? <span className="inline-flex shrink-0 cursor-not-allowed" title={`${label}: ${explanation}`}>{button}</span> : button;
}
