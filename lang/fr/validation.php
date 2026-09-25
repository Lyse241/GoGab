<?php

/*
|--------------------------------------------------------------------------
| Messages de validation (français)
|--------------------------------------------------------------------------
| Les règles non listées ici retombent sur l'anglais (APP_FALLBACK_LOCALE).
*/

return [

    'accepted' => 'Le champ :attribute doit être accepté.',
    'active_url' => "Le champ :attribute n'est pas une URL valide.",
    'after' => 'Le champ :attribute doit être une date postérieure au :date.',
    'after_or_equal' => 'Le champ :attribute doit être une date postérieure ou égale au :date.',
    'alpha' => 'Le champ :attribute ne doit contenir que des lettres.',
    'alpha_dash' => 'Le champ :attribute ne doit contenir que des lettres, chiffres, tirets et underscores.',
    'alpha_num' => 'Le champ :attribute ne doit contenir que des lettres et des chiffres.',
    'array' => 'Le champ :attribute doit être une liste.',
    'before' => 'Le champ :attribute doit être une date antérieure au :date.',
    'before_or_equal' => 'Le champ :attribute doit être une date antérieure ou égale au :date.',
    'between' => [
        'array' => 'Le champ :attribute doit contenir entre :min et :max éléments.',
        'file' => 'Le fichier :attribute doit peser entre :min et :max Ko.',
        'numeric' => 'Le champ :attribute doit être compris entre :min et :max.',
        'string' => 'Le champ :attribute doit contenir entre :min et :max caractères.',
    ],
    'boolean' => 'Le champ :attribute doit être vrai ou faux.',
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'current_password' => 'Le mot de passe est incorrect.',
    'date' => "Le champ :attribute n'est pas une date valide.",
    'decimal' => 'Le champ :attribute doit avoir :decimal décimales.',
    'different' => 'Les champs :attribute et :other doivent être différents.',
    'digits' => 'Le champ :attribute doit contenir :digits chiffres.',
    'digits_between' => 'Le champ :attribute doit contenir entre :min et :max chiffres.',
    'dimensions' => "Les dimensions de l'image :attribute ne sont pas valides.",
    'distinct' => 'Le champ :attribute contient une valeur en double.',
    'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
    'exists' => "La valeur sélectionnée pour :attribute n'existe pas.",
    'file' => 'Le champ :attribute doit être un fichier.',
    'filled' => 'Le champ :attribute doit avoir une valeur.',
    'gt' => [
        'numeric' => 'Le champ :attribute doit être supérieur à :value.',
        'string' => 'Le champ :attribute doit contenir plus de :value caractères.',
    ],
    'gte' => [
        'numeric' => 'Le champ :attribute doit être supérieur ou égal à :value.',
        'string' => 'Le champ :attribute doit contenir au moins :value caractères.',
    ],
    'image' => 'Le champ :attribute doit être une image.',
    'in' => "La valeur sélectionnée pour :attribute n'est pas valide.",
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'ip' => 'Le champ :attribute doit être une adresse IP valide.',
    'json' => 'Le champ :attribute doit être un document JSON valide.',
    'lowercase' => 'Le champ :attribute doit être en minuscules.',
    'lt' => [
        'numeric' => 'Le champ :attribute doit être inférieur à :value.',
        'string' => 'Le champ :attribute doit contenir moins de :value caractères.',
    ],
    'lte' => [
        'numeric' => 'Le champ :attribute doit être inférieur ou égal à :value.',
        'string' => 'Le champ :attribute ne doit pas dépasser :value caractères.',
    ],
    'max' => [
        'array' => 'Le champ :attribute ne doit pas contenir plus de :max éléments.',
        'file' => 'Le fichier :attribute ne doit pas dépasser :max Ko.',
        'numeric' => 'Le champ :attribute ne doit pas être supérieur à :max.',
        'string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
    ],
    'mimes' => 'Le champ :attribute doit être un fichier de type : :values.',
    'mimetypes' => 'Le champ :attribute doit être un fichier de type : :values.',
    'min' => [
        'array' => 'Le champ :attribute doit contenir au moins :min éléments.',
        'file' => 'Le fichier :attribute doit peser au moins :min Ko.',
        'numeric' => 'Le champ :attribute doit être supérieur ou égal à :min.',
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'not_in' => "La valeur sélectionnée pour :attribute n'est pas valide.",
    'not_regex' => "Le format du champ :attribute n'est pas valide.",
    'numeric' => 'Le champ :attribute doit être un nombre.',
    'password' => [
        'letters' => 'Le champ :attribute doit contenir au moins une lettre.',
        'mixed' => 'Le champ :attribute doit contenir au moins une majuscule et une minuscule.',
        'numbers' => 'Le champ :attribute doit contenir au moins un chiffre.',
        'symbols' => 'Le champ :attribute doit contenir au moins un symbole.',
        'uncompromised' => 'Ce :attribute est apparu dans une fuite de données. Choisissez-en un autre.',
    ],
    'present' => 'Le champ :attribute doit être présent.',
    'prohibited' => "Le champ :attribute n'est pas autorisé.",
    'regex' => "Le format du champ :attribute n'est pas valide.",
    'required' => 'Le champ :attribute est obligatoire.',
    'required_if' => 'Le champ :attribute est obligatoire quand :other vaut :value.',
    'required_with' => 'Le champ :attribute est obligatoire quand :values est renseigné.',
    'required_without' => "Le champ :attribute est obligatoire quand :values n'est pas renseigné.",
    'same' => 'Les champs :attribute et :other doivent être identiques.',
    'size' => [
        'array' => 'Le champ :attribute doit contenir :size éléments.',
        'file' => 'Le fichier :attribute doit peser :size Ko.',
        'numeric' => 'Le champ :attribute doit valoir :size.',
        'string' => 'Le champ :attribute doit contenir :size caractères.',
    ],
    'string' => 'Le champ :attribute doit être du texte.',
    'timezone' => 'Le champ :attribute doit être un fuseau horaire valide.',
    'unique' => 'Ce :attribute est déjà utilisé.',
    'uploaded' => "L'envoi du fichier :attribute a échoué.",
    'url' => 'Le champ :attribute doit être une URL valide.',
    'uuid' => 'Le champ :attribute doit être un UUID valide.',

    'custom' => [],

    /*
    | Noms de champs lisibles, utilisés à la place de :attribute.
    */
    'attributes' => [
        'name' => 'nom',
        'email' => 'e-mail',
        'phone' => 'téléphone',
        'password' => 'mot de passe',
        'current_password' => 'mot de passe actuel',
        'password_confirmation' => 'confirmation du mot de passe',
        'category' => 'catégorie',
        'image' => 'image',
        'description' => 'description',
        'price' => 'prix',
        'quantity' => 'quantité',
        'status' => 'statut',
        'neighborhood_id' => 'quartier',
        'address_landmarks' => 'repères',
        'payment_method' => 'mode de paiement',
        'items' => 'panier',
    ],

];
