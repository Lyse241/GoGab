import {
    Apple,
    Baby,
    Beef,
    CakeSlice,
    Carrot,
    Coffee,
    Croissant,
    CupSoda,
    Egg,
    Fish,
    Flower,
    HeartPulse,
    IceCreamCone,
    Pill,
    Pizza,
    Sandwich,
    Shirt,
    ShoppingBag,
    ShoppingBasket,
    Smartphone,
    Soup,
    Store,
    Utensils,
    Wine,
} from 'lucide-react';

/**
 * Icônes lucide des catégories : nom enregistré en base (config gogab.category_icons) → composant.
 * Importées une par une (et non toute la bibliothèque) pour garder un bundle léger.
 */
export const CATEGORY_ICONS = {
    utensils: Utensils,
    sandwich: Sandwich,
    pizza: Pizza,
    beef: Beef,
    fish: Fish,
    soup: Soup,
    croissant: Croissant,
    'cake-slice': CakeSlice,
    'ice-cream-cone': IceCreamCone,
    coffee: Coffee,
    'cup-soda': CupSoda,
    wine: Wine,
    pill: Pill,
    'heart-pulse': HeartPulse,
    baby: Baby,
    'shopping-basket': ShoppingBasket,
    'shopping-bag': ShoppingBag,
    apple: Apple,
    carrot: Carrot,
    egg: Egg,
    shirt: Shirt,
    smartphone: Smartphone,
    flower: Flower,
    store: Store,
};

/**
 * Composant d'icône d'une catégorie (icône « boutique » si le nom est inconnu ou absent).
 */
export function categoryIcon(name) {
    return CATEGORY_ICONS[name] ?? Store;
}
