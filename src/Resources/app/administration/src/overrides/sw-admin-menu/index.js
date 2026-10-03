import template from './template.twig';
import { overrideIfExists } from '../override-if-exists';

// 6.7.15 / trunk render Administration as mt-text. Keep that shell and
// keep the badge and Shopware version inside the compact title row.
overrideIfExists('sw-admin-menu', {
    template,

    computed: {
        shopwareVersion() {
            return Shopware.Context.app.config.version;
        },
    },
});
