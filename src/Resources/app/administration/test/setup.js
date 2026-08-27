import { beforeEach } from 'vitest';
import { allowConsoleMessage } from '@friendsofshopware/vitest-shopware-admin-bridge/test-utils';
import enTools from '../src/module/frosh-tools/snippet/en-GB.json';
import deTools from '../src/module/frosh-tools/snippet/de-DE.json';
import enWebhook from '../src/module/frosh-tools-webhook/snippet/en-GB.json';
import deWebhook from '../src/module/frosh-tools-webhook/snippet/de-DE.json';

function extendLocale(name, messages) {
    if (typeof Shopware.Locale.extend === 'function') {
        Shopware.Locale.extend(name, messages);
    }
}

beforeEach(() => {
    const english = { ...enTools, ...enWebhook };
    const german = { ...deTools, ...deWebhook };

    extendLocale('en-GB', english);
    extendLocale('de-DE', german);

    // Shopware 6.6 vue-i18n warns when a key is resolved via the `en` fallback
    // locale even though the message exists on `en-GB`.
    allowConsoleMessage('[intlify] Fall back to translate');
    allowConsoleMessage('[vuex] unknown mutation type: adminMenu');
});
