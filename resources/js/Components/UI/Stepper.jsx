import { cn } from '@/utils/cn';
import { Check } from 'lucide-react';

/**
 * Étapes d'un formulaire en plusieurs parties (inscription livreur, entreprise…).
 *
 * - steps : [{ label, description? }]
 * - current : index de l'étape en cours (0 = première)
 *
 * Mobile : « Étape 2 sur 4 » + barre de progression. À partir de sm : toutes les étapes.
 */
export default function Stepper({ steps, current, className }) {
    const total = steps.length;
    const progress = total > 1 ? (current / (total - 1)) * 100 : 100;

    return (
        <nav aria-label="Progression" className={className}>
            {/* Version compacte (mobile) */}
            <div className="sm:hidden">
                <p className="text-xs font-semibold uppercase tracking-wide text-primary-700">
                    Étape {current + 1} sur {total}
                </p>
                <p className="mt-0.5 font-semibold text-secondary-900">{steps[current]?.label}</p>
                <div
                    className="mt-3 h-2 overflow-hidden rounded-full bg-gray-200"
                    role="progressbar"
                    aria-valuemin={1}
                    aria-valuemax={total}
                    aria-valuenow={current + 1}
                    aria-label={`Étape ${current + 1} sur ${total}`}
                >
                    <div className="h-full rounded-full bg-primary transition-all duration-300" style={{ width: `${progress}%` }} />
                </div>
            </div>

            {/* Version complète */}
            <ol className="hidden items-start sm:flex">
                {steps.map((step, index) => {
                    const done = index < current;
                    const active = index === current;

                    return (
                        <li key={step.label} className="relative flex flex-1 flex-col items-center text-center">
                            {index > 0 && (
                                <span
                                    className={cn(
                                        'absolute right-1/2 top-5 h-0.5 w-full -translate-y-1/2',
                                        index <= current ? 'bg-primary' : 'bg-gray-200',
                                    )}
                                    aria-hidden="true"
                                />
                            )}
                            <span
                                aria-current={active ? 'step' : undefined}
                                className={cn(
                                    'relative z-10 flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold transition',
                                    done && 'bg-primary text-white',
                                    active && 'bg-white text-primary-700 ring-4 ring-primary',
                                    !done && !active && 'bg-white text-gray-500 ring-2 ring-gray-200',
                                )}
                            >
                                {done ? <Check className="h-5 w-5" aria-hidden="true" /> : index + 1}
                            </span>
                            <span
                                className={cn(
                                    'mt-2 px-1 text-sm font-medium',
                                    active ? 'text-secondary-900' : done ? 'text-gray-700' : 'text-gray-500',
                                )}
                            >
                                {step.label}
                                <span className="sr-only">{done ? ' (terminée)' : active ? ' (en cours)' : ''}</span>
                            </span>
                            {step.description && (
                                <span className="mt-0.5 px-1 text-xs text-gray-500">{step.description}</span>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
