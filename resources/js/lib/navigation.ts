import { router } from '@inertiajs/react';
import type { MouseEvent } from 'react';

const NAV_STACK_KEY = 'klinik:nav-stack';
const MAX_NAV_STACK = 30;
const IGNORED_PATH_PREFIXES = ['/login', '/logout', '/bantuan-akses', '/verifikasi'];

let trackerInstalled = false;

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

export function roleDashboardHref(role?: string | null): string {
    switch (role) {
        case 'pendaftaran':
            return '/pendaftaran';
        case 'perawat':
            return '/perawat/dashboard';
        case 'dokter':
            return '/dokter';
        case 'farmasi':
            return '/farmasi/dashboard';
        default:
            return '/';
    }
}

function pathnameOf(url: string): string {
    const clean = url.split(/[?#]/, 1)[0].replace(/\/+$/, '');

    return clean || '/';
}

function normalizeRelativeUrl(rawUrl: string): string | null {
    if (!rawUrl) {
        return null;
    }

    try {
        const base = typeof window !== 'undefined' ? window.location.origin : 'http://localhost';
        const parsed = new URL(rawUrl, base);

        if (typeof window !== 'undefined' && parsed.origin !== window.location.origin) {
            return null;
        }

        const path = parsed.pathname.replace(/\/+$/, '') || '/';
        if (IGNORED_PATH_PREFIXES.some((prefix) => path === prefix || path.startsWith(`${prefix}/`))) {
            return null;
        }

        return `${path}${parsed.search}`;
    } catch {
        return null;
    }
}

function readNavigationStack(): string[] {
    if (typeof window === 'undefined') {
        return [];
    }

    try {
        const raw = window.sessionStorage.getItem(NAV_STACK_KEY);
        if (!raw) {
            return [];
        }
        const parsed = JSON.parse(raw);

        return Array.isArray(parsed) ? parsed.filter((item): item is string => typeof item === 'string') : [];
    } catch {
        return [];
    }
}

function writeNavigationStack(stack: string[]): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.sessionStorage.setItem(NAV_STACK_KEY, JSON.stringify(stack.slice(-MAX_NAV_STACK)));
    } catch {
        // Ignore storage quota or private-mode errors
    }
}

export function recordNavigationVisit(rawUrl: string): void {
    const normalized = normalizeRelativeUrl(rawUrl);
    if (!normalized) {
        return;
    }

    const nextPath = pathnameOf(normalized);
    const stack = readNavigationStack();
    let existingIndex = -1;

    for (let i = stack.length - 1; i >= 0; i -= 1) {
        if (pathnameOf(stack[i]) === nextPath) {
            existingIndex = i;
            break;
        }
    }

    const nextStack = existingIndex >= 0
        ? [...stack.slice(0, existingIndex), normalized]
        : [...stack, normalized];

    writeNavigationStack(nextStack);
}

export function installNavigationHistoryTracker(): void {
    if (trackerInstalled || typeof window === 'undefined') {
        return;
    }
    trackerInstalled = true;

    recordNavigationVisit(`${window.location.pathname}${window.location.search}`);
    router.on('navigate', (event) => {
        recordNavigationVisit(event.detail.page.url);
    });
}

export function returnToPreviousScreen(fallbackUrl: string): void {
    if (typeof window === 'undefined') {
        router.visit(fallbackUrl);

        return;
    }

    const currentPath = pathnameOf(window.location.pathname);
    const stack = readNavigationStack();

    for (let i = stack.length - 1; i >= 0; i -= 1) {
        if (pathnameOf(stack[i]) !== currentPath) {
            const target = stack[i];
            writeNavigationStack(stack.slice(0, i + 1));
            router.visit(target);

            return;
        }
    }

    const referrer = window.document.referrer;
    if (referrer) {
        const normalizedReferrer = normalizeRelativeUrl(referrer);
        if (normalizedReferrer && pathnameOf(normalizedReferrer) !== currentPath) {
            router.visit(normalizedReferrer);

            return;
        }
    }

    router.visit(fallbackUrl);
}

export function handleBackNavigation(event: MouseEvent<Element>, fallbackUrl: string): void {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    event.preventDefault();
    returnToPreviousScreen(fallbackUrl);
}
