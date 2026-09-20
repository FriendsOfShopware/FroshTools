import template from './template.twig';
import { overrideIfExists } from '../override-if-exists';

// Shopware 6.6 / 6.7 render the badge next to sw-version.
// Trunk removed the sw_version_status block; this override is then a no-op.
overrideIfExists('sw-version', {
    template,
});
