/**
 * Total et nombre d'articles du panier, sans les produits devenus indisponibles
 * (ils restent affichés, barrés, jusqu'à ce que le client les retire).
 *
 * - items : cart.items ({ product_id, price, quantity })
 * - unavailableIds : Set des product_id indisponibles
 */
export function cartTotals(items, unavailableIds = new Set()) {
    return items
        .filter((item) => !unavailableIds.has(item.product_id))
        .reduce(
            (totals, item) => ({
                total: totals.total + item.price * item.quantity,
                count: totals.count + item.quantity,
            }),
            { total: 0, count: 0 },
        );
}
