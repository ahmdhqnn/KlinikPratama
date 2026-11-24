import type { ReactNode } from 'react';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

export function ReportTableCard({ title, description, icon, pagination, children }: {
    title: string;
    description: string;
    icon: ReactNode;
    pagination?: PaginationData;
    children: ReactNode;
}) {
    return (
        <Card className="min-w-0 overflow-hidden">
            <CardHeader className="border-b border-neutral-100">
                <div className="flex items-start gap-3">
                    <span aria-hidden="true" className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700">{icon}</span>
                    <div>
                        <CardTitle>{title}</CardTitle>
                        <CardDescription className="mt-1">{description}</CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="p-0">
                <div className="overflow-x-auto">{children}</div>
            </CardContent>
            {pagination && <Pagination pagination={pagination} />}
        </Card>
    );
}
