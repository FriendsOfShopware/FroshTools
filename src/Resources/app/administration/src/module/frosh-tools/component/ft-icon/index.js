import './style.scss';

// Curated import set — only the icons we actually use, so tree-shaking keeps the bundle tiny.
// Names match the kebab-case file names in lucide-static; the resolver in ft-icon maps them to the SVG strings here.
import RefreshCw           from 'lucide-static/icons/refresh-cw.svg?raw';
import MoreHorizontal      from 'lucide-static/icons/more-horizontal.svg?raw';
import Pencil              from 'lucide-static/icons/pencil.svg?raw';
import Play                from 'lucide-static/icons/play.svg?raw';
import Trash2              from 'lucide-static/icons/trash-2.svg?raw';
import ExternalLink        from 'lucide-static/icons/external-link.svg?raw';
import Check               from 'lucide-static/icons/check.svg?raw';
import AlertTriangle       from 'lucide-static/icons/triangle-alert.svg?raw';
import Zap                 from 'lucide-static/icons/zap.svg?raw';
import Paintbrush          from 'lucide-static/icons/paintbrush.svg?raw';
import Send                from 'lucide-static/icons/send.svg?raw';
import Settings            from 'lucide-static/icons/settings.svg?raw';
import FileText            from 'lucide-static/icons/file-text.svg?raw';
import Workflow            from 'lucide-static/icons/workflow.svg?raw';
import LineChart           from 'lucide-static/icons/chart-line.svg?raw';
import X                   from 'lucide-static/icons/x.svg?raw';

const ICONS = {
    'refresh':         RefreshCw,
    'more':            MoreHorizontal,
    'pencil':          Pencil,
    'play':            Play,
    'trash':           Trash2,
    'external-link':   ExternalLink,
    'check':           Check,
    'alert':           AlertTriangle,
    'bolt':            Zap,
    'paint':           Paintbrush,
    'send':            Send,
    'cog':             Settings,
    'file':            FileText,
    'flow':            Workflow,
    'chart':           LineChart,
    'close':           X,
};

const { Component } = Shopware;

Component.register('ft-icon', {
    props: {
        name: {
            type: String,
            required: true,
        },
        size: {
            type: [Number, String],
            default: 14,
        },
    },
    computed: {
        svg() {
            return ICONS[this.name] || '';
        },
        style() {
            const s = typeof this.size === 'number' ? `${this.size}px` : this.size;
            return { width: s, height: s };
        },
    },
    template: '<span class="ft-icon" :style="style" v-html="svg"></span>',
});
