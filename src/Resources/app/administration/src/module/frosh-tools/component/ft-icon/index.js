import './style.scss';
import template from './template.html.twig';

// Curated import set — only the icons we actually use, so tree-shaking keeps the bundle tiny.
// lucide-static ships SVG strings as ES modules, so we do not need Vite-specific asset imports.
import {
    AlertTriangle,
    ArrowRight,
    Check,
    ChevronDown,
    ChevronUp,
    Copy,
    ExternalLink,
    FileText,
    Info,
    LineChart,
    MoreHorizontal,
    Paintbrush,
    Pencil,
    Play,
    RefreshCw,
    Send,
    Settings,
    Trash2,
    Workflow,
    X,
    Zap,
} from 'lucide-static';

const ICONS = {
    refresh: RefreshCw,
    more: MoreHorizontal,
    pencil: Pencil,
    play: Play,
    trash: Trash2,
    'external-link': ExternalLink,
    'arrow-right': ArrowRight,
    'chevron-up': ChevronUp,
    'chevron-down': ChevronDown,
    check: Check,
    copy: Copy,
    alert: AlertTriangle,
    info: Info,
    bolt: Zap,
    paint: Paintbrush,
    send: Send,
    cog: Settings,
    file: FileText,
    flow: Workflow,
    chart: LineChart,
    close: X,
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
            const s =
                typeof this.size === 'number' ? `${this.size}px` : this.size;
            return { width: s, height: s };
        },
    },
    template,
});
