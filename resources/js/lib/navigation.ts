export interface NavigationTarget {
    href: string;
    exact?: boolean;
    activePaths?: string[];
}

export function activeNavigationHref(url: string, items: NavigationTarget[]): string | null {
    const pathname = url.split(/[?#]/, 1)[0].replace(/\/+$/, '') || '/';
    let selected: string | null = null;
    let longestMatch = -1;

    for (const item of items) {
        for (const path of [item.href, ...(item.activePaths ?? [])]) {
            const matches = pathname === path || (!item.exact && path !== '/' && pathname.startsWith(`${path}/`));
            if (matches && path.length > longestMatch) {
                selected = item.href;
                longestMatch = path.length;
            }
        }
    }

    return selected;
}

export function returnToPreviousScreen(fallbackUrl: string): void {
    const referrer = window.document.referrer;

    if (referrer) {
        const previousUrl = new URL(referrer);

        if (
            window.history.length > 1 &&
            previousUrl.origin === window.location.origin &&
            previousUrl.href !== window.location.href
        ) {
            window.history.back();

            return;
        }
    }

    window.location.assign(fallbackUrl);
}
