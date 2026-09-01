/**
 * Shopware trunk dropped some Admin components (and template blocks).
 * Override only when the target is still registered so plugin boot cannot fail.
 */
export function overrideIfExists(name, config) {
    const registry = Shopware.Component.getComponentRegistry();

    if (typeof registry.has === 'function' && !registry.has(name)) {
        return false;
    }

    try {
        Shopware.Component.override(name, config);
        return true;
    } catch {
        return false;
    }
}
