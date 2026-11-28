import type { ReactNode } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Pagination, type PaginationData } from '@/components/dashboard/pagination';
import { TableScroll } from '@/components/ui/table';

export function DataTableWrapper({
    title,
    description,
    icon,
    headerActions,
    pagination,
    children,
}: {
    title: string;
    description?: string;
    icon?: ReactNode;
    headerActions?: ReactNode;
    pagination?: PaginationData | null;
    children: ReactNode;
}) {
    return (
        <Card className="overflow-hidden">
            <CardHeader className="border-b border-neutral-100">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="flex items-start gap-3">
                        {icon && (
                            <span className="flex size-10 shrink-0 items-center justify-center rounded-xl bg-neutral-100 text-neutral-700">
                                {icon}
                            </span>
                        )}
                        <div>
                            <CardTitle>{title}</CardTitle>
                            {description && <CardDescription className="mt-1">{description}</CardDescription>}
                        </div>
                    </div>
                    {headerActions && <div className="flex flex-wrap items-center gap-2">{headerActions}</div>}
                </div>
            </CardHeader>
            <CardContent className="p-0">
                <TableScroll>{children}</TableScroll>
                {pagination && <Pagination pagination={pagination} />}
            </CardContent>
        </Card>
    );
}
