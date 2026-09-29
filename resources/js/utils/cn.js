/**
 * Concatène des classes CSS en ignorant les valeurs vides :
 * cn('a', cond && 'b', ['c', null]) → "a b c".
 */
export function cn(...classes) {
    return classes.flat(Infinity).filter(Boolean).join(' ');
}
