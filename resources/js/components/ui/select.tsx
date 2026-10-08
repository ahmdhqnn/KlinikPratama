import * as SelectPrimitive from '@radix-ui/react-select';
import { Children, isValidElement, type ComponentProps, type ReactNode } from 'react';
import { Check, ChevronDown } from 'lucide-react';
import { cn } from '@/lib/utils';

const EMPTY_VALUE = '__shadcn_empty_select__';

export interface SelectChangeEvent {
    target: { value: string };
}

interface SelectProps extends Omit<ComponentProps<typeof SelectPrimitive.Trigger>, 'onChange' | 'value' | 'defaultValue'> {
    value?: string | number;
    defaultValue?: string | number;
    name?: string;
    required?: boolean;
    onChange?: (event: SelectChangeEvent) => void;
    children: ReactNode;
}

interface OptionProps {
    value?: string | number;
    disabled?: boolean;
    children?: ReactNode;
}

function optionText(children: ReactNode): string {
    if (typeof children === 'string' || typeof children === 'number') {
        return String(children);
    }

    return Children.toArray(children).map((child) => {
        if (typeof child === 'string' || typeof child === 'number') {
            return String(child);
        }

        return '';
    }).join('');
}

export function Select({ className, children, value, defaultValue, onChange, id, name, disabled, required, ...props }: SelectProps) {
    const options = Children.toArray(children).flatMap((child) => {
        if (!isValidElement<OptionProps>(child) || child.type !== 'option') {
            return [];
        }

        const optionValue = String(child.props.value ?? optionText(child.props.children));

        return [{
            label: child.props.children,
            value: optionValue === '' ? EMPTY_VALUE : optionValue,
            disabled: child.props.disabled,
        }];
    });
    const selectedValue = value === undefined ? undefined : String(value);
    const selectedDefaultValue = defaultValue === undefined ? undefined : String(defaultValue);

    return (
        <SelectPrimitive.Root
            defaultValue={selectedDefaultValue === '' ? EMPTY_VALUE : selectedDefaultValue}
            disabled={disabled}
            name={name}
            onValueChange={(nextValue) => onChange?.({ target: { value: nextValue === EMPTY_VALUE ? '' : nextValue } })}
            required={required}
            value={selectedValue === undefined ? undefined : selectedValue === '' ? EMPTY_VALUE : selectedValue}
        >
            <SelectPrimitive.Trigger
                className={cn(
                    'flex h-10 w-full items-center justify-between gap-2 rounded-lg border border-neutral-200 bg-surface px-3.5 py-2 text-left text-sm text-neutral-950 shadow-sm outline-none transition focus-visible:border-neutral-500 focus-visible:ring-4 focus-visible:ring-neutral-500/10 disabled:cursor-not-allowed disabled:opacity-50 data-[placeholder]:text-neutral-400',
                    className,
                )}
                id={id}
                {...props}
            >
                <SelectPrimitive.Value />
                <SelectPrimitive.Icon asChild>
                    <ChevronDown aria-hidden="true" className="size-4 shrink-0 text-neutral-500" />
                </SelectPrimitive.Icon>
            </SelectPrimitive.Trigger>
            <SelectPrimitive.Portal>
            <SelectPrimitive.Content className="z-[95] max-h-72 overflow-hidden rounded-xl border border-neutral-200 bg-surface shadow-lg shadow-inverse/10 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0" position="popper" sideOffset={5}>
                    <SelectPrimitive.Viewport className="p-1">
                        {options.map((option, index) => (
                            <SelectPrimitive.Item
                                className="relative flex min-h-9 cursor-default select-none items-center rounded-lg py-1.5 pl-3 pr-9 text-sm text-neutral-700 outline-none focus:bg-neutral-50 focus:text-neutral-800 data-[disabled]:pointer-events-none data-[disabled]:opacity-50"
                                disabled={option.disabled}
                                key={`${option.value}-${index}`}
                                value={option.value}
                            >
                                <SelectPrimitive.ItemText>{option.label}</SelectPrimitive.ItemText>
                                <SelectPrimitive.ItemIndicator className="absolute right-3 flex items-center">
                                    <Check className="size-4 text-neutral-700" />
                                </SelectPrimitive.ItemIndicator>
                            </SelectPrimitive.Item>
                        ))}
                    </SelectPrimitive.Viewport>
                </SelectPrimitive.Content>
            </SelectPrimitive.Portal>
        </SelectPrimitive.Root>
    );
}
