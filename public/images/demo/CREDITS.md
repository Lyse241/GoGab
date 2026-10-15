# Photos de démonstration

Photos utilisées par les seeders (`DemoCatalog`, `StoreSeeder`, `ProductSeeder`) pour illustrer les
commerces et produits de démonstration. **La liste complète (auteur, licence, source de chaque photo)
est dans `credits.json`** et s'affiche sur la page publique `/credits-photos` (lien « Crédits photos »
dans le pied de page).

## Sources et licences

- **Wikimedia Commons** : photos de plats gabonais (catégorie « Cuisine of Gabon » et « Food of Gabon »)
  sous licence **CC BY 4.0 / CC BY-SA 4.0**. L'attribution (auteur, licence, lien) est obligatoire ; les
  photos ont été recadrées et redimensionnées, et restent donc sous la même licence (CC BY-SA : partage
  dans les mêmes conditions).
- **Pexels** (licence Pexels : usage commercial autorisé, attribution non requise) : plats, boissons et
  couvertures de commerces. Page d'origine : `https://www.pexels.com/photo/{id}/`.
- **Domaine public / CC0** (StockSnap, Rawpixel, Flickr via Openverse) : produits courants (pains,
  légumes, desserts, épicerie…).

Les photos retenues ne mettent pas en avant de personnes identifiables (les scènes de marché ou de
cuisine ne montrent des personnes que de loin).

## Régénérer ou remplacer

Les images sont recadrées (800 × 800 pour les produits, 1 200 × 800 pour les couvertures) et compressées
en JPEG. Pour un vrai lancement, les commerces téléversent leurs propres photos depuis « Mon commerce » et
« Produits » (stockées dans `storage/app/public`) : ces photos de démonstration ne servent alors plus.
