<?php

namespace App\Exceptions;

/**
 * Code promo refusé (inconnu, expiré, déjà utilisé…). Message en français destiné au client.
 * Rendu hérité : toast d'erreur, ou 422 JSON.
 */
class PromoException extends OrderTransitionException {}
