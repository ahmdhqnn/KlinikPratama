const controlSelector = 'input:not([type="hidden"]):not([aria-hidden="true"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button[role="checkbox"]:not([disabled]), button[role="combobox"]:not([disabled]), button[aria-invalid="true"]:not([disabled]), [tabindex="0"][aria-invalid="true"]';

export function focusFormControl(control: HTMLElement): void {
    control.focus({ preventScroll: true });
    control.scrollIntoView({ block: 'center', inline: 'nearest' });
}

export function revealFormControl(control: HTMLElement): boolean {
    if (control.closest('[hidden], [inert], [aria-hidden="true"]') || control.matches(':disabled')) return false;
    for (let parent = control.parentElement; parent; parent = parent.parentElement) {
        if (getComputedStyle(parent).display === 'none') return false;
    }
    const closed: HTMLDetailsElement[] = [];
    for (let parent = control.parentElement; parent; parent = parent.parentElement) {
        if (parent instanceof HTMLDetailsElement && !parent.open) closed.push(parent);
    }
    closed.forEach((details) => { details.open = true; });
    const visible = control.getClientRects().length > 0 && getComputedStyle(control).visibility === 'visible';
    if (!visible) closed.forEach((details) => { details.open = false; });
    return visible;
}

export function focusFirstInvalidField(root: ParentNode): boolean {
    const invalid = root.querySelectorAll<HTMLElement>('[aria-invalid="true"], [data-field-invalid]');
    for (const field of invalid) {
        const controls = field.matches(controlSelector) ? [field] : field.querySelectorAll<HTMLElement>(controlSelector);
        for (const control of controls) {
            if (revealFormControl(control)) {
                focusFormControl(control);
                return true;
            }
        }
        if (field.hasAttribute('data-field-invalid') && field.tabIndex === -1 && revealFormControl(field)) {
            focusFormControl(field);
            return true;
        }
    }
    return false;
}
