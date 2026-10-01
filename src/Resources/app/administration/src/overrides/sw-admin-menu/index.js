import template from './template.twig';
import { overrideIfExists } from '../override-if-exists';

// 6.7.15 / trunk expose sw_admin_menu_header_title_status inside the
// Administration mt-text. Older 6.6 / 6.7.13 menus have no such block.
overrideIfExists('sw-admin-menu', {
    template,
});
