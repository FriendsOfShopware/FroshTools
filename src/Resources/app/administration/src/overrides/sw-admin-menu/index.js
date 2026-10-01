import template from './template.twig';
import { overrideIfExists } from '../override-if-exists';

// 6.7.15 / trunk render Administration as mt-text. Keep that shell and
// swap only the inner text for the badge so the 40px header does not stretch.
overrideIfExists('sw-admin-menu', {
    template,
});
