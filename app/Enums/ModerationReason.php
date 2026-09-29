<?php

namespace App\Enums;

enum ModerationReason: string
{
    case ComportementAbusif = 'comportement_abusif';
    case Fraude = 'fraude';
    case FauxDocuments = 'faux_documents';
    case NonRespectRegles = 'non_respect_regles';
    case RetardsRepetes = 'retards_repetes';
    case Plainte = 'plainte';
    case Autre = 'autre';

    public function label(): string
    {
        return match ($this) {
            self::ComportementAbusif => 'Comportement abusif',
            self::Fraude => 'Fraude',
            self::FauxDocuments => 'Faux documents',
            self::NonRespectRegles => 'Non-respect des règles',
            self::RetardsRepetes => 'Retards répétés',
            self::Plainte => 'Plainte',
            self::Autre => 'Autre',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $reason) => ['value' => $reason->value, 'label' => $reason->label()], self::cases());
    }
}
