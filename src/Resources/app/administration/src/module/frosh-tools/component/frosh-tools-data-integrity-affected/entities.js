function translatedName(entity) {
    return entity.translated?.name || entity.name || null;
}

function detailRoute(name) {
    return (id) => ({ name, params: { id } });
}

function named(routeName, detail = () => null) {
    return {
        route: detailRoute(routeName),
        label: translatedName,
        detail,
    };
}

export default {
    product: {
        route: detailRoute('sw.product.detail'),
        associations: ['options'],
        label: translatedName,
        detail: (product) =>
            [
                product.productNumber,
                ...(product.options ?? []).map(translatedName),
            ]
                .filter(Boolean)
                .join(' · '),
    },
    category: {
        route: detailRoute('sw.category.detail'),
        label: translatedName,
        detail: (category) => {
            const breadcrumb =
                category.translated?.breadcrumb ?? category.breadcrumb ?? [];

            return breadcrumb.slice(0, -1).join(' › ') || null;
        },
    },
    customer: {
        route: detailRoute('sw.customer.detail'),
        label: (customer) =>
            [customer.firstName, customer.lastName].filter(Boolean).join(' ') ||
            customer.email,
        detail: (customer) =>
            [customer.customerNumber, customer.email]
                .filter(Boolean)
                .join(' · '),
    },
    rule: named('sw.settings.rule.detail'),
    flow: named('sw.flow.detail'),
    product_stream: named('sw.product.stream.detail'),
    sales_channel: named('sw.sales.channel.detail'),
    payment_method: named(
        'sw.settings.payment.detail',
        (paymentMethod) => paymentMethod.technicalName ?? null
    ),
    shipping_method: named(
        'sw.settings.shipping.detail',
        (shippingMethod) => shippingMethod.technicalName ?? null
    ),
    delivery_time: named('sw.settings.delivery.time.detail'),
    currency: named(
        'sw.settings.currency.detail',
        (currency) => currency.isoCode
    ),
    country: named('sw.settings.country.detail', (country) => country.iso),
    language: named('sw.settings.language.detail'),
    promotion: named('sw.promotion.v2.detail'),
    number_range: named('sw.settings.number.range.detail'),
    tax: named('sw.settings.tax.detail', (tax) =>
        tax.taxRate === undefined ? null : `${tax.taxRate} %`
    ),
    cms_page: named('sw.cms.detail'),
    customer_group: named('sw.settings.customer.group.detail'),
    media_folder: {
        route: (id) => ({ name: 'sw.media.index', params: { folderId: id } }),
        label: translatedName,
        detail: () => null,
    },
    mail_template: {
        route: detailRoute('sw.mail.template.detail'),
        associations: ['mailTemplateType'],
        label: (mailTemplate) =>
            translatedName(mailTemplate.mailTemplateType ?? {}),
        detail: (mailTemplate) =>
            mailTemplate.translated?.description ||
            mailTemplate.description ||
            null,
    },
    system_config: {
        route: (id, systemConfig) =>
            systemConfig?.configurationKey?.startsWith('core.basicInformation.')
                ? { name: 'sw.settings.basic.information.index' }
                : { name: 'sw.cms.index' },
        associations: ['salesChannel'],
        label: (systemConfig) => systemConfig.configurationKey,
        detail: (systemConfig) =>
            systemConfig.salesChannel
                ? translatedName(systemConfig.salesChannel)
                : null,
    },
    tax_provider: {
        route: detailRoute('sw.settings.tax.tax_provider.detail'),
        label: (taxProvider) =>
            translatedName(taxProvider) || taxProvider.identifier,
        detail: (taxProvider) => taxProvider.identifier,
    },
};
