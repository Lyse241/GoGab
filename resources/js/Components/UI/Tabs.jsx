import { cn } from '@/utils/cn';
import { useId, useRef, useState } from 'react';

/**
 * Onglets accessibles (flèches gauche/droite, Début/Fin), défilables sur mobile.
 *
 * - items : [{ value, label, icon?, count?, content? }]
 * - value / onChange : mode contrôlé ; sinon defaultValue
 * - variant : "pills" (défaut) ou "underline"
 * Si les items ont un `content`, le panneau actif est rendu sous la liste.
 */
export default function Tabs({ items, value, defaultValue, onChange, variant = 'pills', className, label = 'Onglets' }) {
    const [internal, setInternal] = useState(defaultValue ?? items[0]?.value);
    const active = value ?? internal;
    const baseId = useId();
    const refs = useRef([]);

    const select = (next) => {
        setInternal(next);
        onChange?.(next);
    };

    const onKeyDown = (event, index) => {
        const last = items.length - 1;
        const target = {
            ArrowRight: index === last ? 0 : index + 1,
            ArrowLeft: index === 0 ? last : index - 1,
            Home: 0,
            End: last,
        }[event.key];

        if (target !== undefined) {
            event.preventDefault();
            refs.current[target]?.focus();
            select(items[target].value);
        }
    };

    const activeItem = items.find((item) => item.value === active);

    return (
        <div className={className}>
            <div
                role="tablist"
                aria-label={label}
                className={cn(
                    'no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:px-0',
                    variant === 'underline' && 'gap-6 border-b border-gray-200',
                )}
            >
                {items.map((item, index) => {
                    const selected = item.value === active;
                    const Icon = item.icon;

                    return (
                        <button
                            key={item.value}
                            ref={(element) => (refs.current[index] = element)}
                            type="button"
                            role="tab"
                            id={`${baseId}-tab-${item.value}`}
                            aria-selected={selected}
                            aria-controls={item.content !== undefined ? `${baseId}-panel-${item.value}` : undefined}
                            tabIndex={selected ? 0 : -1}
                            onClick={() => select(item.value)}
                            onKeyDown={(event) => onKeyDown(event, index)}
                            className={cn(
                                'inline-flex min-h-tap shrink-0 items-center gap-2 whitespace-nowrap text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary',
                                variant === 'pills' &&
                                    (selected
                                        ? 'rounded-full bg-secondary px-4 text-white shadow-sm'
                                        : 'rounded-full bg-white px-4 text-gray-700 ring-1 ring-gray-200 hover:bg-gray-50'),
                                variant === 'underline' &&
                                    (selected
                                        ? '-mb-px border-b-2 border-primary text-secondary-900'
                                        : '-mb-px border-b-2 border-transparent text-gray-500 hover:text-gray-800'),
                            )}
                        >
                            {Icon && <Icon className="h-4 w-4" aria-hidden="true" />}
                            {item.label}
                            {item.count !== undefined && (
                                <span
                                    className={cn(
                                        'min-w-5 rounded-full px-1.5 py-0.5 text-center text-xs font-bold',
                                        selected && variant === 'pills'
                                            ? 'bg-accent text-secondary-900'
                                            : 'bg-gray-100 text-gray-600',
                                    )}
                                >
                                    {item.count}
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>

            {activeItem?.content !== undefined && (
                <div
                    role="tabpanel"
                    id={`${baseId}-panel-${activeItem.value}`}
                    aria-labelledby={`${baseId}-tab-${activeItem.value}`}
                    tabIndex={0}
                    className="mt-4 focus:outline-none"
                >
                    {activeItem.content}
                </div>
            )}
        </div>
    );
}
