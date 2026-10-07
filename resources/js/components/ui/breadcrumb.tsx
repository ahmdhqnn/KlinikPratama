import { Link } from '@inertiajs/react';

export interface BreadcrumbItem {
    label: string;
    href?: string;
}

export function Breadcrumb({ items }: { items: BreadcrumbItem[] }) {
    if (items.length === 0) {
        return null;
    }

    return (
        <nav aria-label="Breadcrumb" className="min-w-0">
            <ol className="flex min-w-0 items-center gap-2 overflow-hidden text-sm text-neutral-500 md:text-base">
                {items.map((item, index) => {
                    const current = index === items.length - 1;

                    return (
                        <li className="flex min-w-0 items-center gap-2" key={`${item.href ?? item.label}-${index}`}>
                            {index > 0 && <span aria-hidden="true" className="shrink-0 text-neutral-300">/</span>}
                            {item.href && !current ? (
                                <Link className="truncate rounded-sm transition-colors hover:text-neutral-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-500" href={item.href}>{item.label}</Link>
                            ) : (
                                <span aria-current={current ? 'page' : undefined} className={`truncate ${current ? 'font-semibold text-neutral-900' : 'text-neutral-500'}`}>{item.label}</span>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
