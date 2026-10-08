/**
 * Inline explanations for the data integrity checks.
 *
 * Keyed by the `id` of the SettingsResult that the backend returns.
 *
 * Shape per entry:
 *   description: why the check matters / what the finding means (required)
 *   solution:    how to resolve it (optional)
 */
export default {
    'product-canonical-without-variants': {
        description:
            'A product without variants has a canonical product set. The canonical product selects which variant of a product is used as the canonical URL, so on a product without variants it is a leftover, for example from variants that were deleted later. It can point search engines to a URL that no longer belongs to this product.',
        solution:
            'The SEO tab of the administration only offers the canonical product for products with variants, so remove it through the Admin API by setting "canonicalProductId" to null, for example with an import.',
    },
    'product-canonical-other-family': {
        description:
            'The canonical product of these products is not one of their own variants but belongs to another product. Search engines are told that the page is a duplicate of a different product, so the product page can drop out of the search index.',
        solution:
            'Open the product in the administration and select one of its own variants as canonical product in the SEO tab, or remove the canonical product.',
    },
    'product-main-variant-invalid': {
        description:
            'The main variant configured for the product listing does not exist anymore or is not a variant of this product. The listing then cannot show the intended variant for the product.',
        solution:
            'Open the product in the administration and select one of its own variants as main variant in the variants tab under "Storefront presentation".',
    },
    'variant-main-variant-config': {
        description:
            'A variant has its own main variant configuration. The main variant is configured on the main product and applies to all of its variants, a value on a variant is a leftover, for example from a product that was converted into a variant.',
        solution:
            'Remove the main variant configuration from these variants, so they inherit the configuration of their main product.',
    },
    'product-description-too-long': {
        description:
            'Elasticsearch rejects single terms larger than 32766 bytes. Before Shopware 6.6.6.0 the product description was indexed as such a term, so products with a longer description fail to index and are missing from search and listings.',
        solution:
            'Shorten the descriptions, or update Shopware to 6.6.6.0 or later, which no longer indexes long values as a single term.',
    },
    'duplicate-delivery-times': {
        description:
            'Several delivery times have the same minimum, maximum and unit. They are shown identically in the storefront, but rules, filters and imports that refer to one of them miss the products using the others.',
        solution:
            'Assign the products and shipping methods of the duplicates to one of the delivery times and delete the others.',
    },
    'duplicate-customer-emails': {
        description:
            'Several registered customers share an email address, although Shopware requires it to be unique per sales channel when customers are bound to their sales channel, and unique across all sales channels otherwise. The login always uses the newest of these accounts, so the customers of the older accounts cannot log in anymore.',
        solution:
            'Merge or delete the duplicate customer accounts, or change their email addresses. Guest accounts are not counted.',
    },
    'product-cover-invalid': {
        description:
            'The cover of these products points to an image that was deleted or that belongs to another product. "product.product_media_id" has no foreign key, so deleting the image leaves the reference behind. Listings and the product page then show no image or the image of another product.',
        solution:
            'Open the product in the administration and select a cover in the media tab.',
    },
    'variant-duplicate-options': {
        description:
            'Several variants of a product have exactly the same options. The variant switcher in the storefront can only reach one of them, so the others cannot be selected and their stock is not sold.',
        solution:
            'Delete the surplus variants in the variants tab of the main product, after moving their stock, prices and media to the variant that remains.',
    },
    'variant-without-options': {
        description:
            'These variants have no options at all, usually because they were imported without them or the options were deleted. The variant switcher cannot select them.',
        solution:
            'Delete these variants and generate them again in the variants tab of the main product, or assign their options through the Admin API.',
    },
    'product-delivery-time-missing': {
        description:
            'These products refer to a delivery time that was deleted. "product.delivery_time_id" has no foreign key, so the reference stays behind and no delivery time is shown for the product.',
        solution:
            'Select an existing delivery time in the deliverability section of the product.',
    },
    'product-layout-wrong-type': {
        description:
            'These products use a layout that is not a product page layout, for example a listing or landing page layout. Such layouts lack the product specific elements like the buy box.',
        solution:
            'Assign a product page layout in the layout tab of the product, or remove the layout to use the default one.',
    },
    'category-layout-wrong-type': {
        description:
            'These categories use a product page layout, which expects a product and cannot render a category page.',
        solution:
            'Assign a listing, landing page or shop page layout in the layout tab of the category.',
    },
    'rule-invalid': {
        description:
            'Shopware marks a rule as invalid when one of its conditions cannot be loaded anymore, usually because the extension that provided it was removed. Invalid rules are never loaded into the cart, so prices, shipping methods, payment methods and promotions that depend on them stop working.',
        solution:
            'Open the rules in the rule builder and replace the missing conditions, or reinstall the extension that provided them.',
    },
    'flow-invalid': {
        description:
            'Shopware marks a flow as invalid when one of its actions cannot be loaded anymore, usually because the extension that provided it was removed. Invalid flows are skipped, so their mails and other actions are not executed even though the flow is active.',
        solution:
            'Open the flows in the flow builder and replace the missing actions, or reinstall the extension that provided them.',
    },
    'product-stream-invalid': {
        description:
            'Shopware marks a dynamic product group as invalid when its filters cannot be converted into a search, for example because a filtered field or custom field no longer exists. Core throws an error for such a group, so category listings, cross selling, product sliders and product exports that use it fail.',
        solution:
            'Open the dynamic product groups and fix or remove the broken filters.',
    },
    'sales-channel-default-payment-unassigned': {
        description:
            'The default payment method of these sales channels is not one of their assigned payment methods. New carts still start with the default payment method, which the sales channel does not offer, so the cart reports it as blocked: the storefront switches to another payment method and shows a notice, headless clients have to switch it themselves. The administration prevents this, so it usually comes from imports or API writes.',
        solution:
            'Assign the default payment method to the sales channel or choose one of the assigned methods as default.',
    },
    'sales-channel-default-shipping-unassigned': {
        description:
            'The default shipping method of these sales channels is not one of their assigned shipping methods. New carts still start with the default shipping method, which the sales channel does not offer, so the cart reports it as blocked: the storefront switches to another shipping method and shows a notice, headless clients have to switch it themselves. The administration prevents this, so it usually comes from imports or API writes.',
        solution:
            'Assign the default shipping method to the sales channel or choose one of the assigned methods as default.',
    },
    'sales-channel-domain-language-unassigned': {
        description:
            'These domains use a language that is not assigned to their sales channel. Features that are based on the assigned languages, like the language switcher, do not cover the domain. The administration only offers assigned languages, so it usually comes from imports or API writes.',
        solution:
            'Assign the language to the sales channel or change the language of the domain.',
    },
    'sales-channel-domain-currency-unassigned': {
        description:
            'These domains use a currency that is not assigned to their sales channel. Features that are based on the assigned currencies, like the currency switcher, do not cover the domain. The administration only offers assigned currencies, so it usually comes from imports or API writes.',
        solution:
            'Assign the currency to the sales channel or change the currency of the domain.',
    },
    'shipping-method-without-prices': {
        description:
            'These shipping methods are active but have no price matrix. Their shipping costs cannot be calculated, so they cannot be used in the checkout.',
        solution: 'Add prices to the shipping methods or deactivate them.',
    },
    'customer-default-address-invalid': {
        description:
            'The default billing or shipping address of these customers does not exist or belongs to another customer. The default address columns have no foreign keys, so deleted or reassigned addresses stay referenced. Checkout and account pages expect both addresses and can fail for these customers.',
        solution:
            'Select existing addresses of the customer as default billing and shipping address in the customer detail page.',
    },
    'category-sorting-invalid': {
        description:
            'These categories are sorted after a category that has another parent. The position of a category is stored as the sibling it follows, so the sorting of the category tree breaks for them.',
        solution:
            'Drag the categories to their intended position in the category tree of the administration, which stores a valid sibling.',
    },
};
