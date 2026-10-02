# Gogab — mémoire projet

Document de référence pour les sessions suivantes. Ne pas le traiter comme du code applicatif.

## Résumé

Gogab est une marketplace de livraison pour Libreville (Gabon). Les clients commandent auprès de commerces locaux ; les livreurs prennent en charge les commandes ; les entreprises gèrent leur boutique ; un administrateur valide les comptes et pilote la plateforme.

## Stack réelle (versions installées)

Relevées le 28 septembre 2026 (`php artisan --version`, `composer.lock`, `package-lock.json`).

| Élément | Contrainte (`composer.json` / `package.json`) | Installé |
| --- | --- | --- |
| PHP | `^8.2` | **8.3.30** (CLI) |
| Laravel | `laravel/framework: ^12.0` | **12.69.2** |
| Inertia (Laravel) | `inertiajs/inertia-laravel: ^2.0` | **2.0.28** |
| Inertia (React) | `@inertiajs/react: ^2.0.0` | **2.3.28** |
| React | `react` / `react-dom: ^18.2.0` | **18.3.1** |
| Tailwind CSS | `tailwindcss: ^3.2.1` | **3.4.19** |
| Vite | `vite: ^6.0.11` | **6.4.3** |
| Tests | `phpunit/phpunit: ^11.5.3` | **PHPUnit 11.5.56** |

- **Langage front :** JavaScript (JSX). Starter kit Laravel Breeze React. Fichiers `.jsx` / `.js` sous `resources/js`. `jsconfig.json` présent, **pas de `tsconfig.json`**, pas de `.tsx`. Les nouvelles pages et composants restent en **JSX**, pas en TypeScript.
- **Tests :** **PHPUnit** (classes dans `tests/Feature` et `tests/Unit`, `phpunit.xml`). Pest n’est pas installé (`pestphp/pest` absent ; seul l’allow-plugin Composer existe). **Tout prompt qui demande des tests utilise PHPUnit**, pas Pest.
- Auth : sessions Laravel (Breeze). Sanctum est dans Composer mais le front n’en consomme pas d’API pour l’instant.
- Base : MySQL en local ; les tests PHPUnit utilisent SQLite en mémoire (`phpunit.xml`).
- Devise : FCFA.
- Paniers : **un panier indépendant par commerce**, côté React, `localStorage` clé `gogab_carts` (pas de table `carts`). Voir « Paniers » plus bas.

## Rôles

Quatre rôles. Valeur stockée en anglais, libellé UI en français.

| Valeur (`users.role`) | Libellé | Rôle |
| --- | --- | --- |
| `client` | Client | Commande, suit ses livraisons |
| `delivery` | Livreur | Prend et livre les commandes |
| `business` | Entreprise | Gère boutique, catalogue, commandes reçues |
| `admin` | Administrateur | Valide les comptes, supervise la plateforme |

État actuel du code : `users.role` est un `string(20)` ; les valeurs sont portées par l’enum PHP `App\Enums\Role` (cast dans `User`, helpers `isClient()`, `isDelivery()`, `isBusiness()`, `isAdmin()`, `hasRole()`). Les anciennes constantes `User::ROLE_*` ont été supprimées. Espaces : client → `/` (catalogue), livreur → `/delivery`, entreprise → `/business`, admin → `/admin` (`User::homeRoute()`).

## Statuts de compte

Tout compte reste `pending` tant que l’admin ne l’a pas validé. Sans validation : pas d’accès métier (catalogue entreprise, prises de courses, etc.).

| Valeur (`users.account_status`) | Signification |
| --- | --- |
| `pending` | Inscrit, en attente de validation admin |
| `approved` | Compte actif |
| `rejected` | Inscription refusée |
| `suspended` | Compte désactivé après coup |

État actuel du code : colonne `users.account_status` (défaut `pending`, enum `App\Enums\AccountStatus`, helper `isApproved()`), plus `rejection_reason`, `approved_at`, `approved_by`. Les comptes existants et ceux du seeder sont `approved` ; la `UserFactory` crée des comptes `approved` par défaut (états `pending()`, `rejected()`, `suspended()`). Contrôle : middleware `approved` (`EnsureApproved`) sur commande, `/delivery`, `/business`, `/admin` ; redirection vers `/account/pending`, `/account/rejected` (motif + « Corriger et renvoyer » → `/account/rejected/correction`, page temporaire) ou `/account/suspended` (`AccountStatusController`, récapitulatif de ce qui a été envoyé). Un compte non validé peut se connecter, voir le catalogue, son profil et ses notifications ; un client `pending` prépare son panier mais ne commande pas.

## Cycle de vie d’une commande

Valeurs stockées en français (snake_case), comme en v1.

Flux principal :

`en_attente` → `acceptee` → `en_preparation` → `en_recherche_livreur` → `livreur_assigne` → `en_livraison` → `arrive` → `livree`

Branches :

- `refusee` — l’entreprise refuse la commande
- `annulee` — annulation (client / règles métier à préciser à l’implémentation)

État actuel du code : `orders.status` est un `string(30)` casté en `App\Enums\OrderStatus` (10 valeurs, `label()`, `color()` pour le badge React `StatusBadge`, `isFinal()`). 

**Moteur : `App\Services\OrderWorkflow` — SEUL endroit où le statut d’une commande change** (aucune écriture de statut ailleurs ; `Order::recordStatus()` n’est appelé que par lui).
- `place(User $client, array $attributes, iterable $items)` : création au statut `en_attente` (client validé), lignes, historique, notification « Nouvelle commande » à l’entreprise. `OrderController@store` calcule les prix puis délègue.
- `transition(Order $order, OrderStatus $to, User $actor, ?string $note, bool $cashCollected = false)` : table `OrderWorkflow::TRANSITIONS` (statut de départ → statut d’arrivée → rôles) ; acteur validé et concerné (client = sa commande ; entreprise = propriétaire du commerce ; livreur = pour `livreur_assigne`, livreur disponible dont le quartier de rattachement est dans la zone du commerce, qui devient `delivery_id` ; ensuite uniquement le livreur assigné ; admin = toujours) ; motif obligatoire pour `refusee` (entreprise) et `annulee` (admin), copié dans `orders.cancel_reason`. Commande relue et verrouillée dans une transaction, mise à jour conditionnelle (`where status = départ`, et `delivery_id is null` pour la prise de course) : un seul livreur peut prendre une course, un double clic échoue proprement. Historique + notifications (`Notifier`) à chaque étape.
- Transitions : `en_attente` → `acceptee` | `refusee` (entreprise), → `annulee` (client ou admin) ; `acceptee` → `en_preparation` (entreprise) ; `en_preparation` → `en_recherche_livreur` (entreprise, annonce : renseigne `orders.announced_at`, `announcement_count = 1`) ; `en_recherche_livreur` → `annulee` par l’entreprise (motif obligatoire) uniquement si l’annonce est restée sans livreur depuis `Order::ANNOUNCEMENT_RETRY_MINUTES` (5) ; `en_recherche_livreur` → `livreur_assigne` (livreur de la zone) ; `livreur_assigne` → `en_livraison` → `arrive` → `livree` (livreur assigné) ; `annulee` par l’admin depuis tout statut non final.
- Notifications : nouvelle commande → entreprise ; acceptée / refusée (avec motif) / en préparation → client ; annonce → livreurs validés, disponibles, de la zone (`couriersForZone()`) + client ; livreur assigné, commande récupérée, livrée → client et entreprise ; « Votre livreur est arrivé » → client ; annulée → parties concernées sauf l’auteur.
- Relance d’annonce : `OrderWorkflow::relaunch($order, $actor)` (statut inchangé, verrou + mise à jour conditionnelle, `announced_at` = maintenant, `announcement_count` + 1, notification « Course toujours disponible » aux livreurs de la zone, pas au client) ; entreprise propriétaire après 5 min (`Order::announcementIsStale()`, `announcementRetryAt()`), admin à tout moment ; `canRelaunch()`. Route `POST /business/orders/{order}/relaunch` (`business.orders.relaunch`).
- Remise d’une commande cash par le livreur (`livree`) : `$cashCollected` obligatoire (`OrderWorkflow::CASH_MESSAGE`), enregistré dans `orders.cash_collected_at` ; champ `cash_collected` de `PUT orders.status.update`.
- Prise de course (`livreur_assigne`) : livreur validé, disponible (« Vous êtes indisponible… »), sans autre course active (`hasActiveDelivery()`, `OrderWorkflow::BUSY_MESSAGE`), de la zone ; la ligne du livreur est aussi verrouillée (`lockForUpdate`) dans la transaction. Course déjà prise : `OrderWorkflow::TAKEN_MESSAGE` (« Cette course vient d’être prise par un autre livreur. ») ; le retour sur `/delivery/offers` recharge la liste sans la carte.
- Refus : `App\Exceptions\OrderTransitionException` (message français précis ; rendu = retour avec toast d’erreur, ou 422 JSON), commande inchangée. `allowedTransitions($order, $actor)` pour l’interface ; `courierServesStore()`.
- HTTP : `PUT /orders/{order}/status` (`orders.status.update`, tous rôles connectés et validés, champs `status`, `note`) ; `POST /delivery/orders/{order}/accept` (prise de course). **`OrderPolicy::view`** : client = ses commandes, entreprise = son commerce, livreur = ses courses + annonces de sa zone, admin = toutes.
- Espace livreur : voir « Espace livreur » plus bas (offres de sa zone sur `/delivery/offers`, courses en cours sur `/delivery/current`).
- Attention démo : les commerces seedés sans propriétaire (`owner_id` null) ne peuvent pas accepter de commande (seule « Chez Maman Ngoye » a une entreprise).
- Tests : `tests/Feature/Orders/` (`OrderWorkflowTest`, `OrderPolicyTest`, trait `BuildsOrders`), `DeliveryTest`.

Zones (sans GPS) : `neighborhoods.zone` ∈ `Nord`, `Centre`, `Est`, `Sud` (`Neighborhood::ZONES`). Les « livreurs autour » d’un commerce = livreurs dont `delivery_profiles.base_neighborhood_id` est dans la même zone que `stores.neighborhood_id`.

## Conventions

- Tables et colonnes en **anglais** (`users`, `stores`, `products`, `neighborhoods`, `orders`, `order_items`).
- Libellés d’interface en **français**.
- Prix en **FCFA** (`decimal(10, 2)` aujourd’hui).
- Logique métier dans `app/Services` (premier service : `Notifier`).
- Autorisations via **Policies** (dossier encore absent). Le garde-fou actuel est le middleware `role` (`EnsureRole`).
- Validation via **Form Requests**.
- Front : **JavaScript / JSX** (Breeze), alias `@/*` → `resources/js/*`.
- Casse des dossiers du starter kit (PascalCase) : `resources/js/Pages`, `resources/js/Components`, `resources/js/Layouts`, `resources/js/Contexts`. Ne pas introduire `pages/` ni `components/` en minuscules.
- Pages Inertia rangées par rôle, **sous-dossiers PascalCase** (comme `Pages/Admin` et `Pages/Delivery` déjà présents) :
  - `resources/js/Pages/Public`
  - `resources/js/Pages/Client`
  - `resources/js/Pages/Business`
  - `resources/js/Pages/Delivery`
  - `resources/js/Pages/Admin`
- Composants partagés : `resources/js/Components`.
- **Design system** : `resources/js/Components/UI` — **toujours l’utiliser pour les nouvelles pages** (Button, Input, Textarea, Select, Checkbox, Card, Badge, StatusBadge, Modal, ConfirmDialog, Tabs, Stepper, Skeleton, EmptyState, Toast/useToast, Pagination, FileUpload, Spinner). Vitrine : `/design-system` (APP_ENV=local uniquement). Icônes : `lucide-react`. Helper `cn()` (`utils/cn.js`), montants `formatFCFA()` (`utils/format.js`).
- Charte : `tailwind.config.js` (Tailwind 3) — `primary` #00A86B, `secondary` #1E3A8A, `accent` #FBBF24, `gray` = neutral, sémantiques `success` / `warning` / `danger` / `info`, Poppins. Texte blanc sur `primary-600`, jamais sur le jaune.
- Statuts : `UI/StatusBadge` lit libellés et couleurs dans la prop partagée `statuses` (construite depuis `OrderStatus` / `AccountStatus` `label()` + `color()`). Ne pas recopier les libellés côté React.
- Messages flash (`success`, `error`, `warning`, `info`) : affichés en toasts automatiquement par `ToastProvider` (app.jsx).
- Layouts : `resources/js/Layouts` — `PublicLayout` (header sticky : quartier, recherche globale → `/search?q=`, cloche, panier, compte ; hauteur du header exposée en variable CSS `--header-h` pour les barres collantes des pages ; props `hero` = bandeau pleine largeur sous le header, `tone` = gray | white, `searchOnMobile` ; quartier par défaut = `auth.user.neighborhood_id` si rien n’est mémorisé ; footer `Components/Layout/PublicFooter` bleu profond, données de la prop partagée `footer` : `payment_methods` (libellés `PaymentMethod`) et `popular_categories` (5 catégories ayant le plus de commerces visibles)), `DashboardLayout` (sidebar desktop / barre du bas mobile ; menus par rôle dans `Layouts/navigation.js`, un lien n’apparaît que si sa route existe), `GuestLayout` (auth).
- Composants Breeze `PrimaryButton`, `TextInput`, `InputLabel`, `InputError`, `Checkbox` : simples adaptateurs vers `UI/*`, gardés pour les pages auth / profil ; à supprimer quand ces pages seront refaites.
- Tests demandés dans les prompts suivants : **PHPUnit** (`tests/Feature`, `tests/Unit`), pas Pest.

État actuel des pages : `Pages/Public/Home` (accueil), `Pages/Public/Store` (page commerce), `Pages/Public/Search` (recherche globale) ; paniers `Pages/Cart` ; checkout `Pages/Checkout`, commandes du client `Pages/Orders` (`Index`, `Confirmation`, `Show`). `Business/`, `Admin/` et `Delivery/` existent ; `Client/` pas encore.

Page commerce (`StoreController@show` → `Public/Store`, 404 si `! isVisible()`) : en-tête (couverture, logo, catégorie, quartier et zone, description, repères, `OpeningStatusBadge`, horaires de la semaine avec le jour surligné) ; bandeau « Fermé · ouvre à 08h00 » (`status_label` du serveur) et « + » désactivés si fermé ; barre collante (`top: var(--header-h)`) avec recherche dans le menu (filtre client sur le nom) et puces de sections (ancres `#section-n`, section visible surlignée par `IntersectionObserver`, la barre défile horizontalement sans faire défiler la page) ; produits en `Components/ProductCard` (texte à gauche, photo et bouton « + » à droite, sélecteur − / + une fois dans le panier). Ajout direct au panier de CE commerce (`useCart().addItem(product, store)`, aucune confirmation) ; barre flottante « Voir mon panier · X FCFA » (panier de ce commerce seulement) qui ouvre le tiroir.

Paniers (`Contexts/CartContext`) : état `{ [storeId]: { store: { id, name, logo }, items: [{ product_id, name, price, image, quantity }], updated_at } }`, persistance `localStorage` `gogab_carts` (paniers vides supprimés automatiquement ; l’ancien panier unique `gogab_cart` est repris une fois puis effacé ; synchro entre onglets). Un produit va toujours dans le panier de son commerce (`product.store_id` doit correspondre au commerce passé, sinon l’ajout est ignoré) ; ajouter chez un commerce ne touche jamais aux autres paniers. API : `carts` (liste, plus récent d’abord, avec `count` et `subtotal`), `itemCount` (total), `cartOf(storeId)`, `quantityOf(storeId, productId)`, `addItem`, `updateQuantity(storeId, productId, qty)`, `removeItem(storeId, productId)`, `removeItems`, `clearCart(storeId)`, tiroir `openCarts()` (liste, ou l’unique panier directement), `openCart(storeId)`, `closeDrawer()`.
- Interface : `Components/Layout/CartButton` (header, compteur = tous les articles, ouvre le tiroir) ; `Components/Cart/CartDrawer` (monté dans `PublicLayout` : panneau à droite sur desktop, feuille montante sur mobile ; liste `Cart/CartList` ou panier `Cart/CartPanel`) ; `CartPanel` = lignes (photo, − / +, suppression), sous-total, « Commander chez … » (`atStore()` évite « chez Chez … »), « Vider ce panier » (confirmation en ligne). À l’affichage, `useStoreStatus(storeId, productIds)` → `GET /stores/{id}/status?products=1,2` : fermé → bandeau `status_label` + bouton désactivé ; produits indisponibles ou supprimés signalés (retrait en un clic). Compte non client ou client non validé : bouton désactivé avec explication. Page `/cart` (`Cart/Index`, `?store=` met un panier en avant) : un bloc complet par panier.
- Commander : `GET /checkout/{store}` (`CheckoutController@create`, rôle client — un client en attente voit la page avec un message à la place du bouton, `canOrder = false` ; 404 si commerce invisible ; ne traite que le panier de ce commerce, le vide après la commande). Page : récapitulatif en lecture seule, adresse préremplie depuis le profil (`address` : `neighborhood_id`, `address_landmarks`, sinon quartier du header), repères obligatoires, paiement en cartes (`paymentMethods` : `label`, `hint`, `mobile_money`), pour le paiement à la livraison « Avec quel montant paierez-vous ? » (« Montant exact » + `quickCashAmounts` supérieurs au total + champ libre, « Monnaie à rendre » en direct, refus si inférieur au total), note pour le commerce, sous-total + frais (`deliveryFee`) + total.
- Création : `POST /orders` (client validé) avec `store_id` ; `StoreOrderRequest` revérifie commerce visible et ouvert (sinon « {nom} : Fermé · ouvre à … »), produits disponibles et tous de ce commerce, `cash_given` obligatoire et ≥ total recalculé pour `cash` (ignoré sinon, « 10 000 » accepté). Prix : **`App\Services\OrderPricing`** (`quote()` : lignes à prix figés, sous-total, `deliveryFee()` = `config('gogab.delivery_fee')`, env `GOGAB_DELIVERY_FEE`, 1 000 FCFA par défaut, total). Puis `OrderWorkflow::place()` et redirection vers `GET /orders/{order}/confirmation` (`orders.confirmation`, numéro, total, monnaie, bouton « Suivre ma commande » → `orders.show`).
- Suivi client (routes `['auth', 'role:client']`, `OrderPolicy::view` → **403** sur la commande d’un autre) : `GET /orders` (`orders.index`, `Orders/Index`, « Mes commandes » : onglets `?tab=` En cours / Terminées (`isFinal()`), compteurs, 10 par page) ; `GET /orders/{order}` (`orders.show`, `Orders/Show`) : récapitulatif (articles, sous-total, frais, total, adresse, paiement, « Vous remettez » / « Monnaie à recevoir »), timeline **`App\Support\OrderTimeline::for($order)`** depuis `order_status_histories` (libellés client : Commande envoyée, Acceptée, En préparation, Recherche d’un livreur, Livreur assigné, En route, Livreur arrivé, Livrée ; `state` done / current / upcoming et heure ; refus ou annulation = arrêt de la timeline + étape de fin avec motif), livreur assigné (`courier` : prénom, véhicule, marque, téléphone → bouton « Appeler » `tel:`, masqué une fois terminée), `can_cancel` (via `OrderWorkflow::allowedTransitions`, donc uniquement `en_attente`) → `ConfirmDialog` avec motif facultatif → `PUT orders.status.update`. Rafraîchissement `usePoll` toutes les 10 s (`only: ['order']`), arrêté quand `is_final`. « Mes commandes » dans le menu utilisateur et le footer des clients ; les notifications du client mènent à `/orders/{id}`. `Order::change_due` (accesseur) = `cash_given − total_price`, null hors paiement à la livraison. Dates affichées à l’heure de Libreville. Visiteur : `GET /cart/{store}/login` (`cart.login`) mémorise le retour (`url.intended` = `/cart?store=…`) puis mène à la connexion. `/checkout` (ancienne adresse) redirige vers `/cart`.

Recherche globale (`GET /search?q=`, `SearchController`, route `search`, 2 caractères minimum) : commerces visibles dont le nom, la description ou la catégorie correspond (30 max), et produits dont le nom ou la description correspond (60 max, disponibles d’abord) regroupés par commerce (`productGroups` : `store` + `products`) ; ajout au panier possible depuis les résultats. L’accueil accepte encore `?q=` mais les barres de recherche mènent à `/search`.

Accueil (`StoreController@index` → `Public/Home`) : bandeau vert (`Hero`, base en vague vers le blanc) avec recherche « De quoi avez-vous besoin ? » (`SearchBar size="lg"`, garde la catégorie) et pastilles rondes des catégories ayant au moins un commerce visible (`categories` : `name`, `slug`, `icon` → `utils/categoryIcons`, `stores_count`) ; `?category=slug` filtre (catégorie inconnue ignorée) et donne le titre (`title` : « Restaurants à Libreville », pluriel du premier mot, « Résultats pour « q » »…). Commerces `Store::visible()`, ouverts d’abord ; chaque carte reçoit `logo`, `neighborhood_id`, `neighborhood`, `zone`, `today_hours` (`StoreHours::todayLabel`) ; le navigateur remonte ceux du quartier choisi puis de sa zone (badge « Votre quartier » / « Votre zone »), fermés grisés. Squelettes pendant les rechargements (événements `router` start / finish), états vides par cas. Bandeau « Rejoignez Gogab » (livreur, commerce) pour les visiteurs et les clients.

## Règles de travail

- **Mobile-First** : concevoir d’abord pour téléphone (Libreville, usage terrain).
- **Pas de GPS temps réel.** La proximité = le quartier (`neighborhoods`). Un livreur « proche » est dans la même zone, pas un pin carte. Le quartier choisi dans le header est mémorisé côté navigateur (`NeighborhoodContext`, `localStorage`) et prérempli au checkout.
- **Notifications in-app** uniquement (pas d’SMS / push, pas de WebSocket). Toujours passer par `App\Services\Notifier::send($users, $title, $message, $url = null, $type = 'info')` (un `User`, une collection ou un tableau ; types `info` / `success` / `warning`). Canal `database` (table `notifications` standard), notification `App\Notifications\AppNotification`, envoi **synchrone** (pas de worker requis). Les liens internes (hôte d’`APP_URL` ou de la requête en cours) sont stockés en chemin relatif ; le front n’ouvre que les liens internes.
  - Routes : `GET /notifications` (page, `?filter=all|unread|read`), `GET /notifications/unread` (JSON : `unread_count` + 10 dernières), `POST /notifications/{id}/read`, `POST /notifications/read-all`.
  - Front : `NotificationsProvider` (app.jsx) interroge `/notifications/unread` toutes les 15 s (en pause si l’onglet est caché) et affiche un toast à l’arrivée d’une nouvelle notification ; `Components/Layout/NotificationBell` (menu), `Components/NotificationItem`, page `Pages/Notifications/Index`. Prop partagée `auth.unread_notifications` (indice seulement : après un rechargement partiel Inertia elle peut être périmée).
  - Démo : `php artisan gogab:notify-test {email} --type=info|success|warning`.
- **Ne jamais committer** sans accord explicite de l’utilisateur.

## Authentification et inscription

- Breeze adapté (pas réécrit) : `Auth/Login` au design Gogab, `GuestLayout` (connexion, inscription, pages d’état du compte), `UI/PasswordInput`.
- Après connexion : `redirect()->intended(route($user->homeRoute()))` — espace du rôle, ou page d’état si le compte n’est pas validé.
- `auth.user` partagé = champs d’interface uniquement (`id`, `name`, `email`, `phone`, `email_verified_at`, `role`, `account_status`, `account_status_label`, `initials`), plus `auth.role`, `auth.role_label`, `auth.unread_notifications`.
- Menu utilisateur : « Mon espace » selon le rôle (Administration / Mon commerce / Mes courses ; rien pour le client), « Suivi de mon inscription » si non validé, « Mon profil », « Déconnexion ».
- Inscription : `/register` (choix du profil) → `/register/client` (`RegisterClientRequest`, service `App\Services\AccountRegistration::registerClient()`). Client créé `pending`, tous les admins validés notifiés (« Nouveau compte client à valider »), connexion automatique, redirection `/account/pending`.
- `config('gogab.auto_approve_clients')` (env `GOGAB_AUTO_APPROVE_CLIENTS`, false par défaut) : clients approuvés directement (démo).
- Inscription livreur : `/register/delivery`, 3 étapes (`UI/Stepper`) dans un seul `useForm` (retour arrière sans perte, fichiers compris). Chaque étape est vérifiée par `POST /register/delivery/check` (`step` = 1 ou 2, 204 ou 422) ; soumission finale `POST /register/delivery` (`RegisterDeliveryRequest`, règles par étape `stepRules()`), puis `AccountRegistration::registerDelivery()` : user `pending` + `delivery_profile` + documents dans une transaction (fichiers supprimés si échec), admins notifiés « Nouveau livreur à valider », redirection `/account/pending` avec écran de succès (`justRegistered`).
- Inscription entreprise : `/register/business`, 3 étapes (gérant, commerce avec `OpeningHoursEditor` et logo facultatif sur le disque public `stores/logos`, documents). `RegisterBusinessRequest` + `POST /register/business/check` ; `AccountRegistration::registerBusiness()` crée dans une transaction le gérant `pending` (rattaché au quartier du commerce), le `store` (`owner_id`, `is_active = false`, horaires) et les documents ; admins notifiés « Nouvelle entreprise à valider ».
- Front commun aux inscriptions en plusieurs étapes : hook `Hooks/useRegistrationSteps` (validation immédiate, vérification serveur de l’étape, retour à l’étape en erreur) et `Components/NeighborhoodSelect` (quartiers groupés par zone).
- **Visibilité publique** : scope `Store::visible()` = commerce actif (`is_active`) ET propriétaire validé (ou sans propriétaire, commerces seedés) ; `isVisible()` pour un modèle chargé. Utilisé sur l’accueil et la recherche ; page commerce et `/stores/{id}/status` en 404 sinon. Un commerce invisible n’est jamais « ouvert » (`StoreHours::isOpenNow`), donc jamais commandable.
- Règles d’informations personnelles partagées : trait `App\Http\Requests\Concerns\ValidatesAccountDetails` (`accountDetailsRules(withAddress: false)` pour le gérant).
- **Documents obligatoires : une seule source, `DocumentType::requiredFor(Role, ?VehicleType)`** (moto/voiture : CIN + permis + plaque + 4 photos ; vélo : CIN + 4 photos ; entreprise : RCCM + NIF + CIN du gérant) ; facultatifs : `DocumentType::optionalFor()` (entreprise : `health_permit`, `other`) ; messages d’erreur : `DocumentType::validationMessages()`. `DocumentType` porte aussi `hint()`, `isPhoto()` (photos : JPG/PNG uniquement ; CIN, permis : + PDF), `extensions()`, `mimeTypes()`, `MAX_KILOBYTES` (5 Mo), `toUploader()` pour le front. `VehicleType::requiresLicense()`.
- Fichiers des documents : disque privé `local` (`storage/app/private/documents/{user_id}/…`), jamais d’URL publique — l’admin devra passer par un contrôleur pour les afficher.
- Front : `Components/DocumentUploader` (un emplacement par document) sur `UI/FileUpload` (options `camera` = « Prendre une photo » sur mobile, `prepare`, `formats`) ; `utils/compressImage` réduit les photos (1600 px, JPEG) avant l’envoi.
- Téléphones : `App\Support\PhoneNumber::normalize()` → format `0XX XX XX XX` (+241 / 00241 acceptés) ; unicité sur la forme normalisée.

## Espace admin

- `/admin` (rôle admin, compte validé), `DashboardLayout`. Menu (`Layouts/navigation.js`) : Tableau de bord, Comptes à valider, Clients, Livreurs, Entreprises, Boutiques (gestion existante des commerces et produits), Catégories, Quartiers, Commandes, Signalements (badge `open_reports`), Modération, Mon profil.
- Section pas encore construite (`/admin/orders`) : page partagée `Pages/ComingSoon` « Bientôt disponible » (props `title`, `description`, `back` = route du tableau de bord ; remplacer la route au moment de construire la section).
- Catégories `/admin/categories` (`Admin\CategoryController` index/store/update/destroy, `Admin\CategoryRequest`, page `Admin/Categories/Index`, formulaire en fenêtre) : nom unique, icône parmi `config('gogab.category_icons')` (correspondance nom → composant lucide dans `resources/js/utils/categoryIcons.js`, `categoryIcon(name)`), ordre d’affichage (vide = en dernier). Slug unique créé à l’ajout, inchangé au renommage. Suppression refusée (message flash `error`) si un commerce utilise la catégorie. Les listes de catégories (inscription entreprise, correction) sont triées `sort_order` puis `name`.
- Quartiers `/admin/neighborhoods` (`Admin\NeighborhoodController` index/store/update, `Admin\NeighborhoodRequest`, page `Admin/Neighborhoods/Index` groupée par zone avec compteurs commerces / livreurs / comptes) : nom unique, zone dans `Neighborhood::ZONES`. Pas de suppression. Les listes de quartiers ne sont pas mises en cache : un changement de zone est pris en compte immédiatement.
- `/admin/accounts` (`Admin\AccountController@index`) : comptes client / livreur / entreprise (jamais les admins) ; onglets `status` (pending par défaut, plus anciens d’abord), filtre `type`, recherche `q` (nom, e-mail, téléphone sans espaces) ; les compteurs d’onglets tiennent compte du type et de la recherche. Tableau sur desktop, cartes sur mobile ; chaque ligne mène à la page de validation.
- `/admin/accounts/{user}` (`Admin\AccountController@show`) : informations selon le rôle (véhicule, zone et disponibilité du livreur, fiche commerce avec horaires), activité (`activity` : produits du commerce, 10 dernières commandes reçues / courses / commandes passées selon le rôle, composant `Components/Admin/AccountActivity`), documents (aperçu via la route sécurisée, approuver / refuser avec motif), décision sur le compte, historique.
- **Logique dans `App\Services\AccountValidationService`** (`approveDocument`, `rejectDocument`, `approveAccount`, `rejectAccount`, `missingApprovals`, `documentsToResend`, `resubmit`), qui revérifie les Policies via `Gate` ; routes aussi protégées par `can:` (`UserPolicy::review` / `resubmit`, `DocumentPolicy::view` / `review`). Un admin ne peut examiner ni un autre admin ni son propre compte.
- Validation d'un compte : impossible tant qu'un document obligatoire (`DocumentType::requiredFor`) n'est pas approuvé ; renseigne `approved_at` / `approved_by`, active le commerce d'une entreprise (`is_active = true`), notification de bienvenue avec lien vers l'espace. Refus : motif obligatoire (`Admin\RejectionRequest`), commerce désactivé, notification avec le motif.
- Historique : table `account_decisions` (`AccountDecision::record()`, enum `AccountDecisionAction`) — envoi du dossier (à l'inscription), décisions sur documents et compte, corrections.
- Documents : **`App\Services\DocumentService`** (`store(User, UploadedFile, DocumentType)` sur le disque privé `local`, nom aléatoire ; un nouvel envoi du même type remplace l’ancien, repasse en pending et supprime l’ancien fichier après validation de la transaction ; `DocumentService::rules(type, required)` = formats, 5 Mo et vrai type du contenu via finfo ; `deleteFiles()`). Lecture : `GET /documents/{document}` (`DocumentController@show`, route `documents.show`, `DocumentPolicy::view`, JPEG/PNG/PDF en ligne, le reste en téléchargement, `nosniff`, pas de cache). Données pour Inertia : `App\Support\DocumentPresenter` ; `DocumentStatus::color()`. Tests : helper `$this->fakePdf()` (TestCase) pour un PDF au contenu réaliste.
- Correction côté utilisateur : `/account/rejected/correction` (`AccountCorrectionController`, `ResubmitAccountRequest`) — informations selon le rôle (type de véhicule non modifiable), documents refusés ou manquants à renvoyer (les autres remplaçables) ; le compte repasse `pending`, les admins reçoivent « Dossier corrigé à revalider » avec le lien vers la page de validation.
- Compteur des comptes en attente : scope `User::awaitingValidation()`, partagé aux admins validés via la prop `badges.pending_accounts` (clé `badge` d’un lien de menu) et dans `stats.pending_accounts` (carte « À valider » du tableau de bord).
- `DashboardLayout` : compteurs sur les liens ; sur mobile, au-delà de 5 liens, 4 liens + « Plus » (fenêtre avec le reste du menu). `shortLabel` possible pour la barre du bas.

## Espace entreprise

- `/business` (`['auth', 'role:business', 'approved']`), `DashboardLayout`, menu : Tableau de bord, Commandes, Produits, Mon commerce, Mon profil. Tableau de bord : compteurs du jour (journée de Libreville) `stats` : `new` (reçues aujourd’hui), `preparing` (acceptées + en préparation, en ce moment), `delivered` (livrées aujourd’hui), `revenue` (sous-total des commandes livrées aujourd’hui, hors frais).
- Commandes (`Business\OrderController`, pages `Business/Orders/Index` et `Business/Orders/Show`) : `GET /business/orders` (`?tab=` new | preparing | searching (« Attente livreur » : `en_recherche_livreur` + `livreur_assigne`) | delivering (`en_livraison`, `arrive`) | finished, voir `OrderController::TABS` ; `counts` par onglet ; `pendingIds` pour détecter les arrivées) et `GET /business/orders/{order}` (`can:view,order` → 403 sur la commande d’un autre commerce ; historique complet avec auteur, rôle et note). Cartes : référence, heure, client, articles, total, note du client, quartier, paiement (et montant remis). Actions = `OrderWorkflow::allowedTransitions()` (hors annulation) via `Components/Business/OrderActions` → `PUT orders.status.update` : Accepter, Refuser (fenêtre, motif obligatoire), « En préparation », « Publier l’annonce de livraison » (`ConfirmDialog`). Annonce publiée : `Components/Business/DeliveryAnnouncement` (« Recherche d’un livreur… », temps écoulé = `announcement.elapsed_seconds` du serveur puis avancé localement ; après 5 min « Relancer l’annonce » et « Annuler la commande » avec motif ; props `announcement` : `elapsed_seconds`, `retry_after_seconds`, `count`, `can_relaunch`, `can_cancel`). Livreur assigné : `courier` (nom, téléphone, véhicule, marque) affiché par `Components/Business/CourierCard` (bouton Appeler) sur la carte et le détail. `usePoll` 10 s ; nouvelle commande = toast + son discret (WebAudio, désactivable, `localStorage` `gogab_order_sound`) + `useNotifications().refresh()`. Badge du menu `badges.new_orders` (commandes en attente). Les notifications de l’entreprise mènent à `/business/orders/{id}`.
- `ToastProvider` n’affiche pas deux fois le même objet flash : un rechargement partiel (`usePoll`, `only`) conserve le flash précédent, qui ne doit pas réapparaître.
- Produits (`/business/products`, `Business\ProductController`, pages `Business/Products/Index` et `Business/Products/Form`) : liste groupée par `menu_section` (sections par ordre alphabétique, sans section en dernier sous « Autres produits »), recherche `q` (nom, description) et filtre `section` (`__none` = sans section) côté serveur, interrupteur de disponibilité (`PATCH /business/products/{product}/availability`, affichage optimiste), ajout / modification en page (`POST` multipart, photo 2 Mo dans `products`, `remove_image`), suppression avec `ConfirmDialog` (refusée si le produit figure dans des commandes : le rendre indisponible). **`ProductPolicy`** (`create`, `update`, `delete` : produit du commerce de l’entreprise validée) via `can:` sur les routes → 403 sur le produit d’une autre entreprise. `Business\ProductRequest` (prix en FCFA entiers, « 4 500 » accepté, section nettoyée avec majuscule initiale). Logique dans **`App\Services\ProductCatalogService`** (`create`, `update`, `setAvailability`, `delete`, `sections`).
- `Business\StoreController` : le commerce est toujours celui de l’utilisateur connecté (`$user->store`, 404 s’il n’en a pas) et passe par **`StorePolicy::manage`** (entreprise validée propriétaire). Logique dans **`App\Services\StoreProfileService`** (`update`, `setOpen`).
- Tableau de bord (`Business/Dashboard`) : bienvenue (couverture, logo), grand interrupteur « Commerce ouvert / fermé » (`PATCH /business/store/open`, `business.store.open`, met à jour `stores.is_open`), état du moment (`OpeningStatusBadge`, calculé serveur : ouvert seulement si interrupteur ouvert ET dans les horaires), horaires du jour, raccourcis.
- « Mon commerce » (`GET /business/store` `business.store.edit`, `POST /business/store` `business.store.update` en multipart) : nom, catégorie, description, téléphone, quartier, repères, horaires (`OpeningHoursEditor`), logo (2 Mo, `stores/logos`) et couverture (4 Mo, 400 × 200 px minimum, `stores`) sur le disque public ; `remove_logo` / `remove_cover_image` pour retirer. `Business\UpdateStoreRequest`. Les nouvelles images sont écrites avant la transaction (effacées si échec), les anciennes supprimées après. Documents non modifiables ici.
- Design system : `UI/Switch` (interrupteur accessible Headless UI, `size` sm | md | lg, `reverse` = interrupteur avant le libellé, `loading`) ; `UI/FileUpload` accepte `onRemoveCurrent` (bouton « Retirer » sur le fichier déjà enregistré).
- Démo : `entreprise@gogab.ga` gère « Chez Maman Ngoye » (adresse, description et horaires renseignés par `TestAccountsSeeder`).

## Espace livreur

- `/delivery` (`['auth', 'role:delivery', 'approved']`, noms `delivery.*`), Mobile-First. Menu (`Layouts/navigation.js`, 5 onglets, pas de « Plus ») : Accueil (`delivery.dashboard`), Offres (`delivery.offers`, badge `offers`), En cours (`delivery.current`, badge `active_orders`), Historique (`delivery.history`), Profil (`delivery.profile`).
- `Layouts/DeliveryLayout` (props `title`, `subtitle`, `availability`, `actions`) = `DashboardLayout` + grand interrupteur `Components/Delivery/AvailabilitySwitch` (`PATCH /delivery/availability`, `delivery_profiles.is_available`, affichage optimiste). Prop partagée `courier` (livreurs seulement) : `is_available`, `base_neighborhood`, `zone`. Badges livreur : `offers` (annonces de sa zone s’il est disponible), `active_orders`.
- Logique : **`App\Services\CourierProfileService`** (`setAvailability`, `setBaseNeighborhood`, `activeOrders`, `todayStats` = courses livrées et gains (somme de `delivery_fee`) sur la journée de Libreville, `earnings` = gains du jour / de la semaine (lundi → dimanche) / du mois, heure de livraison = `updated_at` d’une commande `livree`, `ACTIVE_STATUSES`). Livreur sans `delivery_profile` → 404 sur disponibilité et profil.
- Accueil (`Delivery\HomeController`, `Delivery/Home`) : zone d’activité, carte « course en cours » ou renvoi vers les offres, compteurs du jour (`stats.deliveries`, `stats.earnings`).
- Offres (`DeliveryController@offers`, `Delivery/Offers`) : annonces des commerces de la zone du quartier de base (livreur disponible), de la plus ancienne à la plus récente (`coalesce(announced_at, updated_at)`). Carte d’offre (`presentOffer`, ni nom ni téléphone ni repères du client) : commerce, quartier de retrait → quartier de livraison, `item_count`, `earning` (= `delivery_fee`), paiement ; en cash `cash_given` et `change_due` (« monnaie à prévoir »), `announced_seconds` (calculé serveur). Rafraîchissement `usePoll` 8 s, toast à l’arrivée d’une offre, « Accepter la course » avec `ConfirmDialog`. Prop `busy` (course active : `id`, `number`, `message`) → boutons désactivés avec explication. Course en cours (`DeliveryController@current`, `Delivery/Current`, prop `order` = la course active ou null → état vide vers les offres) : `UI/Stepper` (Retrait au commerce, En route, Chez le client, Livrée), consigne de l’étape, encaissement toujours visible (à encaisser, le client remet, monnaie à rendre), commerce (nom, quartier, repères, Appeler `stores.phone`) et client (prénom, quartier, repères, Appeler), articles, note ; gros bouton unique fixé au-dessus de la barre du bas : « J’ai récupéré la commande » (confirmation) → « Je suis arrivé chez le client » → « Commande remise » (fenêtre : rappel de la monnaie + case « Montant encaissé » en cash). Historique (`DeliveryController@history`, `Delivery/History`) : courses livrées paginées (date, heure, commerce, quartier, gain) et `earnings` (`today`, `week`, `month`) ; la prise de course (`POST /delivery/orders/{order}/accept`) redirige vers `/delivery/current`. Liens des notifications : annonces → `/delivery/offers`, autres → `/delivery/current`.
- Profil (`Delivery\ProfileController`, `Delivery/Profile`) : infos personnelles (modification sur `/profile`), véhicule, quartier de base modifiable (`PATCH /delivery/profile/base-neighborhood`, `Delivery\UpdateBaseNeighborhoodRequest`) qui change immédiatement la zone des offres, statut de chaque document obligatoire (`DocumentType::requiredFor`, « Manquant » s’il n’a pas été envoyé) puis des documents facultatifs.
- Tests : `tests/Feature/Delivery/CourierSpaceTest`, `tests/Feature/Delivery/OffersTest`, `tests/Feature/Delivery/CurrentDeliveryTest`, `DeliveryTest`.

## Modération des comptes

- **Tout passe par `App\Services\ModerationService`** (`warn`, `block`, `unblock`, `unblockExpired`, `flag`, `unflag`, `acknowledge`, `activeOrders`) protégé par `UserPolicy::moderate` (admin validé, jamais un autre admin ni soi-même) ; routes `POST /admin/accounts/{user}/moderation/{warn|block|unblock|flag|unflag}` (`Admin\ModerationController`, `ModerateAccountRequest`, middleware `can:moderate,user`).
- Table `moderation_actions` (enums `ModerationType`, `ModerationReason`) : `message` visible par l’utilisateur, `internal_note` jamais (présentation admin : `App\Support\ModerationPresenter`). Colonnes ajoutées : `users.blocked_until`, `users.flagged_at` (drapeau), `moderation_actions.acknowledged_at` (avertissement lu).
- Blocage : compte validé uniquement ; `account_status = suspended` + `blocked_until` (null = jusqu’à nouvel ordre) ; livreur `is_available = false` ; commerce masqué via `Store::visible()` / `isVisible()` (propriétaire non validé = invisible, sans toucher `is_active`). Middleware global `RedirectIfBlocked` : toute requête d’un compte bloqué mène à `/account/suspended` (sauf cette page et la déconnexion ; JSON → 403). Déblocage auto : `gogab:unblock-expired`, planifié chaque minute (`routes/console.php` ; lancer `php artisan schedule:work` en local, cron `schedule:run` en production).
- Un compte bloqué ne se revalide pas par la validation : `AccountValidationService` n’agit que sur les dossiers `pending` / `rejected`.
- Avertissement : notification + page `/account/warnings` (« Mes avertissements ») ; le plus ancien non lu est partagé (`moderation.pending_warning`) et affiché par `Components/Layout/WarningNotice` (fenêtre non fermable, « J’ai compris »). À partir de 3 avertissements, alerte sur la fiche (jamais de blocage automatique).
- Admin : bloc « Modération » de la fiche (`Components/Admin/ModerationPanel`), journal `/admin/moderation` (filtres type, motif, admin), annuaires `/admin/clients`, `/admin/deliveries`, `/admin/businesses` (tous statuts, filtre « Comptes signalés », drapeau dans les listes).

## Signalements entre utilisateurs

- Table `reports` (`reporter_id`, `reported_user_id`, `order_id`, `reason` enum `ReportReason` : retard, comportement_irrespectueux, commande_non_conforme, fraude, absence, autre ; `description`, `status` enum `ReportStatus` : open, in_review, resolved, dismissed ; `handled_by`, `handled_at`, `admin_note`), modèle `Report` (scopes `pending()` = open + in_review, `urgentFirst()` = fraudes à traiter en tête puis plus récents ; `isUrgent()`).
- **Tout passe par `App\Services\ReportService`** : `parties($order, $reporter)` (client → entreprise, livreur ; entreprise → client, livreur ; livreur assigné → client, entreprise ; jamais soi-même ni un étranger), `formFor()` (prop `reporting` des pages : `order_id`, `parties` avec `already_reported`, `reasons`), `create()` (doublon refusé : un seul signalement à traiter par signalant + personne signalée + commande ; admins notifiés « Nouveau signalement » / « (urgent) » pour une fraude ; la personne signalée n’est jamais notifiée), `markInReview()` (à l’ouverture du détail), `handle($admin, $report, warn|block|dismiss|resolve, $note, $sanction)` (avertir / bloquer via `ModerationService`, motif de modération par défaut `ReportReason::moderationReason()` ; sanction impossible = signalement inchangé ; signalant notifié « Votre signalement a été traité » sans détail).
- Côté utilisateurs : `POST /orders/{order}/reports` (`reports.store`, `['auth', 'approved', 'can:report,order']`, `OrderPolicy::report`, `StoreReportRequest`), bouton `Components/Reports/ReportProblemButton` (fenêtre : personne, motif, description obligatoire) sur `Orders/Show`, `Business/Orders/Show`, `Delivery/Current` et chaque ligne de `Delivery/History`.
- Admin : `ReportPolicy` (admin validé) ; `/admin/reports` (`Admin\ReportController@index`, filtres `status` = pending (défaut) | open | in_review | resolved | dismissed | all, `reason`, `type` = rôle du compte signalé, badge « Urgent »), `/admin/reports/{report}` (détail : faits, compte signalé, commande et historique des statuts, autres signalements et avertissements du compte), `POST /admin/reports/{report}/handle` (`Admin\HandleReportRequest` : message obligatoire pour avertir / bloquer, durée pour bloquer, note admin). Compteur `badges.open_reports` = signalements à traiter.
- Tests : `tests/Feature/Reports/ReportTest`.

## Horaires d’ouverture des commerces

- Fuseau : `config('gogab.timezone')` = `Africa/Libreville` (l’app reste en UTC). **Jamais l’heure du navigateur** : `is_open_now`, `status_label`, `status_detail` sont calculés côté serveur (`Store::openingStatus()`) et envoyés aux pages.
- Table `store_opening_hours` (une ligne par jour, `day_of_week` 1 = lundi … 7 = dimanche, `opens_at` / `closes_at` en `HH:MM:SS`, `is_closed` = jour de repos). Fermeture < ouverture = créneau passant minuit (le créneau de la veille s’applique après minuit) ; ouverture = fermeture = 24 h/24.
- `stores.is_open` = interrupteur manuel de fermeture temporaire (false = fermé même pendant les horaires).
- Règles : `App\Services\StoreHours` (`isOpenNow`, `currentSlot`, `nextOpeningAt`, `statusMessage`, `schedule`, `sync`, `everyDay`), exposées par `Store` (`isOpenNow()`, `nextOpeningAt()`, `statusMessage()`, `openingStatus()`, scope `openNow()`).
- Validation des horaires : trait `App\Http\Requests\Concerns\ValidatesOpeningHours` (champ `opening_hours`, 7 lignes) — à réutiliser pour l’inscription entreprise et « Mon commerce ».
- Front : `Components/OpeningHoursEditor` (+ `defaultOpeningHours`, `validateOpeningHours`), `Components/OpeningStatusBadge`, hook `Hooks/useStoreStatus` (GET `/stores/{store}/status`).
- Produits indisponibles (`products.is_available = false`) : la page commerce (`Public/Store`, menu groupé par `menu_section`, sans section en dernier) et la recherche les affichent grisés avec « Épuisé » et sans bouton d’ajout (« Retirer du panier » s’ils y sont déjà). `GET /stores/{store}/status` renvoie `unavailable_product_ids` : le panier et le checkout signalent l’article (ligne barrée), proposent de le retirer et bloquent la commande. Les totaux affichés (panier, checkout, barre de la page commerce) l’excluent : `utils/cartTotals.js` (`cartTotals(items, unavailableIds)` → `{ total, count }`). Refus serveur dans `StoreOrderRequest` dans tous les cas.
- Commande bloquée si fermé : boutons d’ajout désactivés + bandeau (page commerce), alerte + bouton désactivé (panier, checkout), refus serveur dans `StoreOrderRequest`.
- Tests : `StoreFactory` crée par défaut des horaires 24 h/24 (états `withHours()`, `withoutHours()`, `temporarilyClosed()`) ; figer l’heure avec `Carbon::setTestNow` + `CarbonImmutable::setTestNow`.

## Cartographie v1 (ce qui existe déjà)

### Migrations / tables

- `users` (+ `phone` unique, `role` string, `account_status`, `rejection_reason`, `neighborhood_id`, `address_landmarks`, `approved_at`, `approved_by`)
- `delivery_profiles` (`user_id` unique, `vehicle_type`, `vehicle_brand`, `plate_number`, `license_number`, `base_neighborhood_id`, `is_available`)
- `documents` (`user_id`, `type`, `file_path`, `original_name`, `mime_type`, `size`, `status`, `rejection_reason`, `reviewed_by`, `reviewed_at`)
- `categories` (`name`, `slug` unique, `icon` lucide, `sort_order`)
- `store_opening_hours` (`store_id`, `day_of_week`, `opens_at`, `closes_at`, `is_closed`)
- `stores` (`owner_id` unique nullable, `name`, `category_id`, `description`, `phone`, `neighborhood_id`, `address_landmarks`, `logo`, `cover_image` — ex-`image`, `is_open` défaut true, `is_active` défaut false)
- `products` (`store_id`, `menu_section`, `name`, `description`, `price`, `image`, `is_available` défaut true)
- `neighborhoods` (`name`, `zone`, sans timestamps)
- `orders` (`reference` GG-000123, `store_id`, `client_id`, `delivery_id`, `neighborhood_id`, `address_landmarks`, `subtotal`, `delivery_fee` — 0 pour l’instant, `total_price`, `payment_method`, `cash_given`, `client_note`, `cancel_reason`, `status`, `announced_at`, `announcement_count`, `cash_collected_at`)
- `order_status_histories` (`order_id`, `status`, `changed_by`, `note`, `created_at` seul)
- `order_items` (`order_id`, `product_id`, `quantity`, `price`)
- tables Laravel : `sessions`, `password_reset_tokens`, `cache`, `jobs`

### Modèles

`User`, `DeliveryProfile`, `Document`, `Category`, `Store`, `Product`, `Neighborhood`, `Order`, `OrderItem`, `OrderStatusHistory`

Factories : `UserFactory` (approved par défaut), `CategoryFactory`, `StoreFactory` (état `inCategory('Nom')`), `ProductFactory` (états `unavailable()`, `inSection('Plats')`).

### Enums (`app/Enums`, chacun avec `label()` en français)

`Role`, `AccountStatus`, `DocumentType`, `DocumentStatus`, `VehicleType`, `OrderStatus` (+ `color()`), `PaymentMethod`

### Contrôleurs

- Public / client : `StoreController`, `CheckoutController`, `OrderController`, `ProfileController`
- Livreur : `DeliveryController`
- Admin : `Admin\DashboardController`, `Admin\StoreController`, `Admin\ProductController`
- Auth Breeze : `app/Http/Controllers/Auth/*`

### Middleware

- `EnsureRole` (alias `role`, plusieurs rôles : `role:admin,business`) et `EnsureApproved` (alias `approved`), déclarés dans `bootstrap/app.php`. Ordre conseillé : `['auth', 'role:…', 'approved']`.
- `HandleInertiaRequests` (partage `auth.user`, `auth.role`, `auth.role_label`, flash success/error/warning/info, `neighborhoods` avec zone, `statuses` order/account)

### Seeders

Ordre : `NeighborhoodSeeder` (16 quartiers en 4 zones), `CategorySeeder` (6 catégories), `AdminSeeder` (`admin@gogab.ga`), `UserSeeder` (2 livreurs avec profil moto, 3 clients), `StoreSeeder` (7 commerces actifs sans propriétaire, horaires variés : repos le dimanche, créneau 18:00→02:00, « Supérette Akanda Express » en fermeture temporaire), `ProductSeeder`, `OpeningHoursSeeder` (08:00–22:00 hors dimanche pour tout commerce sans horaires), `TestAccountsSeeder` (comptes dans tous les états : `client.attente@`, `client.suspendu@`, `livreur.attente@` avec profil et 3 documents fictifs, `entreprise@` propriétaire de « Chez Maman Ngoye », `entreprise.refusee@` avec motif et commerce inactif « Snack Le Rond-Point »). Tous les comptes : mot de passe `password`, e-mails en `@gogab.ga`.

### Paiements (choix enregistré, pas de gateway)

Enum `PaymentMethod` : `airtel_money`, `moov_money`, `cash` (ex-`cash_on_delivery`, libellé « Paiement à la livraison ») ; `hint()` (consigne affichée : Mobile Money payé à la livraison via le numéro du livreur), `isMobileMoney()`. `cash_given` n’est enregistré que pour `cash` (obligatoire et ≥ total).

### Tests PHPUnit déjà présents

Auth Breeze, profil, catalogue, panier (pages), commandes, livreur, admin, rôles, pages d’erreur.

## Absences à combler (cible métier, pas encore dans le code)

- Espace entreprise : gestion des produits (prompt 15) et des commandes reçues
- Gestion admin des commandes (annuler / relancer celles d’un compte bloqué) : prompt dédié
- Upload et vérification des documents (table prête, pas d’écran)
- Interfaces du cycle de commande : annulation par l’admin (le moteur `OrderWorkflow` et la route `orders.status.update` sont prêts)
- Pages client rangées sous `Pages/Client`
- Activation du commerce (`is_active = true`) à la validation d’une entreprise par l’admin : à faire avec l’écran de validation
