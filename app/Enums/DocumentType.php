<?php

namespace App\Enums;

/**
 * Pièces justificatives. Chaque emplacement est un type distinct
 * (les 4 photos du véhicule ne se remplacent pas entre elles).
 */
enum DocumentType: string
{
    case IdCard = 'id_card';
    case DrivingLicense = 'driving_license';
    case LicensePlatePhoto = 'license_plate_photo';
    case VehiclePhotoFront = 'vehicle_photo_front';
    case VehiclePhotoBack = 'vehicle_photo_back';
    case VehiclePhotoLeft = 'vehicle_photo_left';
    case VehiclePhotoRight = 'vehicle_photo_right';
    case BusinessRegistration = 'business_registration';
    case TaxId = 'tax_id';
    case HealthPermit = 'health_permit';
    case Other = 'other';

    /**
     * Taille maximale acceptée par le serveur, en kilo-octets (les photos sont compressées
     * dans le navigateur avant l'envoi et pèsent en pratique quelques centaines de Ko).
     */
    public const MAX_KILOBYTES = 5120;

    public function label(): string
    {
        return match ($this) {
            self::IdCard => "Carte d'identité (CIN)",
            self::DrivingLicense => 'Permis de conduire',
            self::LicensePlatePhoto => "Photo de la plaque d'immatriculation",
            self::VehiclePhotoFront => 'Photo du véhicule (avant)',
            self::VehiclePhotoBack => 'Photo du véhicule (arrière)',
            self::VehiclePhotoLeft => 'Photo du véhicule (côté gauche)',
            self::VehiclePhotoRight => 'Photo du véhicule (côté droit)',
            self::BusinessRegistration => 'Registre du commerce (RCCM)',
            self::TaxId => "Numéro d'identification fiscale (NIF)",
            self::HealthPermit => 'Autorisation sanitaire',
            self::Other => 'Autre document',
        };
    }

    /**
     * Consigne affichée sous l'emplacement, avec un exemple.
     */
    public function hint(): string
    {
        return match ($this) {
            self::IdCard => 'Photo ou scan du recto et du verso, en un seul fichier (image ou PDF). Ex. : les deux faces côte à côte.',
            self::DrivingLicense => 'Photo ou scan lisible de votre permis en cours de validité (image ou PDF).',
            self::LicensePlatePhoto => 'Photo nette de la plaque, prise de face : tous les caractères doivent être lisibles.',
            self::VehiclePhotoFront => 'Le véhicule entier vu de face, à la lumière du jour.',
            self::VehiclePhotoBack => 'Le véhicule entier vu de derrière.',
            self::VehiclePhotoLeft => 'Le véhicule entier vu du côté gauche.',
            self::VehiclePhotoRight => 'Le véhicule entier vu du côté droit.',
            self::BusinessRegistration => 'Photo ou scan de l’extrait du registre du commerce (image ou PDF).',
            self::TaxId => 'Photo ou scan de l’attestation NIF (image ou PDF).',
            self::HealthPermit => 'Pour un restaurant, un fast-food ou une pharmacie : autorisation ou agrément sanitaire (image ou PDF).',
            self::Other => 'Tout document utile à la vérification (licence, attestation…), image ou PDF.',
        };
    }

    /**
     * Photo à prendre sur place : images uniquement (jpg, png), appareil photo proposé sur mobile.
     */
    public function isPhoto(): bool
    {
        return in_array($this, [
            self::LicensePlatePhoto,
            self::VehiclePhotoFront,
            self::VehiclePhotoBack,
            self::VehiclePhotoLeft,
            self::VehiclePhotoRight,
        ], true);
    }

    /**
     * Extensions acceptées (règle Laravel "mimes").
     *
     * @return list<string>
     */
    public function extensions(): array
    {
        return $this->isPhoto() ? ['jpg', 'jpeg', 'png'] : ['jpg', 'jpeg', 'png', 'pdf'];
    }

    /**
     * Types MIME acceptés (attribut HTML "accept").
     *
     * @return list<string>
     */
    public function mimeTypes(): array
    {
        return $this->isPhoto()
            ? ['image/jpeg', 'image/png']
            : ['image/jpeg', 'image/png', 'application/pdf'];
    }

    /**
     * Liste unique des documents obligatoires, utilisée à l'inscription, à la validation
     * par l'admin et sur le profil.
     *
     * - livreur moto ou voiture : CIN + permis + plaque + 4 photos du véhicule ;
     * - livreur vélo : CIN + 4 photos du véhicule ;
     * - entreprise : registre du commerce (RCCM) + NIF + pièce d'identité du gérant ;
     * - client et admin : aucun document.
     *
     * @return list<self>
     */
    public static function requiredFor(Role $role, ?VehicleType $vehicle = null): array
    {
        $vehiclePhotos = [
            self::VehiclePhotoFront,
            self::VehiclePhotoBack,
            self::VehiclePhotoLeft,
            self::VehiclePhotoRight,
        ];

        return match ($role) {
            Role::Delivery => match ($vehicle) {
                VehicleType::Bicycle => [self::IdCard, ...$vehiclePhotos],
                VehicleType::Moto, VehicleType::Car => [self::IdCard, self::DrivingLicense, self::LicensePlatePhoto, ...$vehiclePhotos],
                null => [self::IdCard],
            },
            Role::Business => [self::BusinessRegistration, self::TaxId, self::IdCard],
            default => [],
        };
    }

    /**
     * Documents facultatifs proposés à l'inscription (ex. autorisation sanitaire d'un restaurant).
     *
     * @return list<self>
     */
    public static function optionalFor(Role $role): array
    {
        return match ($role) {
            Role::Business => [self::HealthPermit, self::Other],
            default => [],
        };
    }

    /**
     * Messages de validation des fichiers envoyés sous `documents.{type}` (toutes les inscriptions).
     *
     * @return array<string, string>
     */
    public static function validationMessages(): array
    {
        $messages = [];

        foreach (self::cases() as $type) {
            $label = $type->label();
            $formats = $type->isPhoto() ? 'JPG ou PNG' : 'JPG, PNG ou PDF';

            $messages["documents.{$type->value}.required"] = "Document manquant : {$label}.";
            $messages["documents.{$type->value}.file"] = "{$label} : l’envoi du fichier a échoué, réessayez.";
            $messages["documents.{$type->value}.uploaded"] = "{$label} : l’envoi du fichier a échoué, réessayez.";
            $messages["documents.{$type->value}.mimes"] = "{$label} : format non accepté ({$formats} uniquement).";
            $messages["documents.{$type->value}.max"] = "{$label} : fichier trop lourd (5 Mo maximum).";
        }

        return $messages;
    }

    /**
     * Description transmise au composant React DocumentUploader.
     *
     * @return array{type: string, label: string, hint: string, photo: bool, accept: string, max_kb: int}
     */
    public function toUploader(): array
    {
        return [
            'type' => $this->value,
            'label' => $this->label(),
            'hint' => $this->hint(),
            'photo' => $this->isPhoto(),
            'accept' => implode(',', $this->mimeTypes()),
            'max_kb' => self::MAX_KILOBYTES,
        ];
    }
}
