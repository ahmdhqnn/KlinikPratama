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
