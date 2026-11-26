import type { PageProps } from '@inertiajs/core';
import type { BreadcrumbItem } from '@/components/ui/breadcrumb';

export interface AppUser {
    id: number;
    name: string;
    email: string;
    role: string;
    roleLabel: string;
    clinicType?: string | null;
    clinicName?: string | null;
    photoUrl: string | null;
}

export interface SharedProps extends PageProps {
    nurseQueues?: { assessment: number; treatments: number } | null;
    adminQueues?: { assessment: number; treatments: number; examinations: number; handovers: number } | null;
    doctorQueues?: { examinations: number } | null;
    registrationReferralCount?: number | null;
    pharmacyQueues?: { handovers: number } | null;
    breadcrumbs?: BreadcrumbItem[];
    auth: { user: AppUser | null };
    flash: {
        success?: string | null;
        error?: string | null;
        warning?: string | null;
        info?: string | null;
    };
}
