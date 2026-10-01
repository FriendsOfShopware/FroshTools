import template from './template.twig';
import { overrideIfExists } from '../override-if-exists';

// Shopware 6.6 / 6.7.13 render the badge next to sw-version.
// 6.7.15 / trunk dropped sw_version_status; this override is then a no-op.
overrideIfExists('sw-version', {
    template,
});
