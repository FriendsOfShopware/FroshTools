import template from './template.twig';
import { overrideIfExists } from '../override-if-exists';

overrideIfExists('sw-admin-menu', {
    template,
});
