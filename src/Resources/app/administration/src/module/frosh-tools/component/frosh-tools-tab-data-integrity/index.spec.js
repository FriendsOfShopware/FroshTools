import { describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import { mountRegistered } from '../../../../../test/helpers.js';
import '../../../../mixin/sortable-table.js';
import '../../../../mixin/settings-result.js';
import './index.js';
import enGB from '../../snippet/en-GB.json';
import deDE from '../../snippet/de-DE.json';

async function createWrapper(service) {
    return mountRegistered('frosh-tools-tab-data-integrity', {
        provide: {
            froshToolsService: service,
        },
    });
}

describe('frosh-tools-tab-data-integrity', () => {
    it('loads the data integrity status on creation', async () => {
        const status = [{ id: 'duplicate-delivery-times', state: 'STATE_OK' }];
        const service = {
            dataIntegrityStatus: vi.fn().mockResolvedValue(status),
        };

        const wrapper = await createWrapper(service);
        await flushPromises();

        expect(service.dataIntegrityStatus).toHaveBeenCalledTimes(1);
        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.vm.loadError).toBeNull();
        expect(wrapper.vm.dataIntegrityStatus).toEqual(status);
    });

    it('shows an error state instead of loading forever when loading fails', async () => {
        const service = {
            dataIntegrityStatus: vi
                .fn()
                .mockRejectedValue(new Error('Request failed')),
        };

        const wrapper = await createWrapper(service);
        await flushPromises();

        expect(wrapper.vm.isLoading).toBe(false);
        expect(wrapper.vm.loadError).toBe('Request failed');

        await wrapper.vm.$nextTick();
        expect(wrapper.find('ft-hero-state-stub').exists()).toBe(true);
    });

    it('recovers when retrying after a failure', async () => {
        const service = {
            dataIntegrityStatus: vi
                .fn()
                .mockRejectedValueOnce(new Error('Request failed'))
                .mockResolvedValue([]),
        };

        const wrapper = await createWrapper(service);
        await flushPromises();
        expect(wrapper.vm.loadError).toBe('Request failed');

        await wrapper.vm.refresh();
        await flushPromises();

        expect(wrapper.vm.loadError).toBeNull();
        expect(wrapper.vm.dataIntegrityStatus).toEqual([]);
        expect(wrapper.vm.isLoading).toBe(false);
    });

    it('provides info for every data integrity check and maps states to pill variants', async () => {
        const wrapper = await createWrapper({
            dataIntegrityStatus: vi.fn().mockResolvedValue([]),
        });
        await flushPromises();

        for (const id of [
            'product-canonical-without-variants',
            'product-canonical-other-family',
            'product-main-variant-invalid',
            'variant-main-variant-config',
            'product-description-too-long',
            'duplicate-delivery-times',
            'duplicate-customer-emails',
            'product-cover-invalid',
            'variant-duplicate-options',
            'variant-without-options',
            'product-delivery-time-missing',
            'product-layout-wrong-type',
            'category-layout-wrong-type',
            'rule-invalid',
            'flow-invalid',
            'product-stream-invalid',
            'sales-channel-default-payment-unassigned',
            'sales-channel-default-shipping-unassigned',
            'sales-channel-domain-language-unassigned',
            'sales-channel-domain-currency-unassigned',
            'shipping-method-without-prices',
            'customer-default-address-invalid',
            'category-sorting-invalid',
            'sales-channel-navigation-category-inactive',
            'system-config-cms-page-missing',
            'product-stream-without-filters-in-use',
            'product-display-group-missing',
            'payment-method-handler-missing',
            'delivery-time-invalid',
            'currency-rounding-interval-zero',
            'currency-factor-invalid',
            'tax-provider-unavailable',
            'country-forced-state-without-states',
            'sales-channel-default-country-unavailable',
            'promotion-discount-setgroup-missing',
            'customer-requested-group-missing',
            'language-translation-code-missing',
            'product-price-tiers-invalid',
            'product-max-purchase-below-min-purchase',
            'media-folder-configuration-missing',
            'category-link-target-invalid',
            'product-export-currency-invalid',
            'product-configurator-settings-missing',
            'variant-option-configurator-setting-missing',
            'product-main-category-invalid',
            'rule-condition-deleted-references',
            'flow-action-deleted-references',
            'tax-rule-states-invalid',
            'flow-mail-template-incomplete',
            'number-range-type-missing',
            'number-range-sales-channel-missing',
        ]) {
            expect(wrapper.vm.hasInfo({ id }), id).toBe(true);
        }
        expect(wrapper.vm.hasInfo({ id: 'unknown-check' })).toBe(false);

        expect(wrapper.vm.recommendationFor({ id: 'rule-invalid' })).toEqual({
            description:
                enGB['frosh-tools'].tabs['data-integrity'].recommendations[
                    'rule-invalid'
                ].description,
            solution:
                enGB['frosh-tools'].tabs['data-integrity'].recommendations[
                    'rule-invalid'
                ].solution,
        });

        expect(wrapper.vm.pillVariant('STATE_INFO')).toBe('info');
        expect(wrapper.vm.pillVariant('STATE_OK')).toBe('success');
    });

    it('translates the info of every data integrity check into German', () => {
        const english =
            enGB['frosh-tools'].tabs['data-integrity'].recommendations;
        const german =
            deDE['frosh-tools'].tabs['data-integrity'].recommendations;

        expect(Object.keys(german)).toEqual(Object.keys(english));
        for (const [id, texts] of Object.entries(german)) {
            expect(texts.description, id).not.toBe(english[id].description);
            expect(texts.solution, id).not.toBe(english[id].solution);
        }
    });
});
