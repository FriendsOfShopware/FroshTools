import './style.scss';
import template from './template.html.twig';
import { filterCommands, getCommandDefinitions } from './commands';

const { Component, Mixin } = Shopware;

const RECENT_STORAGE_KEY = 'frosh-tools.command-palette.recent';
const RECENT_LIMIT = 5;

Component.register('ft-command-palette', {
    template,

    inject: {
        froshToolsService: { from: 'froshToolsService', default: null },
        themeService: { from: 'themeService', default: null },
        repositoryFactory: { from: 'repositoryFactory', default: null },
    },

    mixins: [Mixin.getByName('notification')],

    props: {
        elasticsearchAvailable: { type: Boolean, default: false },
        logsAvailable: { type: Boolean, default: false },
        fastlyAvailable: { type: Boolean, default: false },
    },

    emits: ['close'],

    data() {
        return {
            query: '',
            activeId: null,
            isRunning: false,
            pendingConfirm: null,
            recentIds: this.readRecentIds(),
        };
    },

    computed: {
        titleId() {
            return `ft-command-palette-title-${this.$.uid}`;
        },

        listboxId() {
            return `ft-command-palette-list-${this.$.uid}`;
        },

        commandContext() {
            return {
                routerPush: (location) => this.$router.push(location),
                froshToolsService: this.froshToolsService,
                themeService: this.themeService,
                repositoryFactory: this.repositoryFactory,
                notifySuccess: (payload) => this.createNotificationSuccess(payload),
                notifyError: (payload) => this.createNotificationError(payload),
                t: (key, params) => this.$t(key, params),
                elasticsearchAvailable: this.elasticsearchAvailable,
                logsAvailable: this.logsAvailable,
                fastlyAvailable: this.fastlyAvailable,
            };
        },

        resolvedCommands() {
            const ctx = this.commandContext;

            return getCommandDefinitions()
                .filter((command) =>
                    typeof command.available === 'function'
                        ? command.available(ctx)
                        : true
                )
                .map((command) => ({
                    ...command,
                    label: this.$t(command.labelKey),
                    description: command.descriptionKey
                        ? this.$t(command.descriptionKey)
                        : '',
                    groupLabel: this.$t(
                        `frosh-tools.commandPalette.groups.${command.group}`
                    ),
                }));
        },

        visibleCommands() {
            const filtered = filterCommands(this.resolvedCommands, this.query);

            if (this.query.trim()) {
                return filtered;
            }

            // Promote recent commands to the top when the query is empty.
            const recent = [];
            const rest = [];

            for (const id of this.recentIds) {
                const match = filtered.find((command) => command.id === id);
                if (match) {
                    recent.push(match);
                }
            }

            for (const command of filtered) {
                if (!this.recentIds.includes(command.id)) {
                    rest.push(command);
                }
            }

            return [...recent, ...rest];
        },

        groupedCommands() {
            const groups = new Map();

            for (const command of this.visibleCommands) {
                const key =
                    !this.query.trim() && this.recentIds.includes(command.id)
                        ? 'recent'
                        : command.group;

                if (!groups.has(key)) {
                    groups.set(key, {
                        group: key,
                        groupLabel:
                            key === 'recent'
                                ? this.$t(
                                      'frosh-tools.commandPalette.groups.recent'
                                  )
                                : command.groupLabel,
                        items: [],
                    });
                }

                groups.get(key).items.push(command);
            }

            return Array.from(groups.values());
        },

        activeOptionId() {
            return this.activeId ? this.optionId(this.activeId) : null;
        },

        pendingConfirmLabel() {
            if (!this.pendingConfirm) {
                return '';
            }

            if (this.pendingConfirm.confirmLabelKey) {
                return this.$t(this.pendingConfirm.confirmLabelKey);
            }

            return this.pendingConfirm.label;
        },
    },

    watch: {
        visibleCommands: {
            immediate: true,
            handler(commands) {
                if (commands.length === 0) {
                    this.activeId = null;
                    return;
                }

                if (!commands.some((command) => command.id === this.activeId)) {
                    this.activeId = commands[0].id;
                }
            },
        },
    },

    mounted() {
        document.addEventListener('keydown', this.onDocumentKeydown, true);
        this.previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        this.previousActiveElement = document.activeElement;

        this.$nextTick(() => {
            this.focusInput();
        });
    },

    unmounted() {
        document.removeEventListener('keydown', this.onDocumentKeydown, true);
        document.body.style.overflow = this.previousOverflow || '';
        this.restorePreviousFocus();
    },

    methods: {
        optionId(id) {
            return `${this.listboxId}-option-${id}`;
        },

        focusInput() {
            const input = this.$refs.input;
            if (input && typeof input.focus === 'function') {
                input.focus();
                if (typeof input.select === 'function') {
                    input.select();
                }
            }
        },

        restorePreviousFocus() {
            const element = this.previousActiveElement;

            if (
                element &&
                document.contains(element) &&
                typeof element.focus === 'function'
            ) {
                element.focus();
            }

            this.previousActiveElement = null;
        },

        onInput(event) {
            this.query = event.target.value;
            this.pendingConfirm = null;
        },

        onInputKeydown(event) {
            if (this.pendingConfirm) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    this.confirmAndRun();
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    event.stopPropagation();
                    this.cancelConfirm();
                }
                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                this.moveActive(1);
                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                this.moveActive(-1);
                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                const active = this.visibleCommands.find(
                    (command) => command.id === this.activeId
                );
                if (active) {
                    this.runCommand(active);
                }
            }
        },

        onDocumentKeydown(event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();

                if (this.pendingConfirm) {
                    this.cancelConfirm();
                    return;
                }

                this.close();
            }
        },

        setActive(id) {
            this.activeId = id;
        },

        moveActive(delta) {
            const commands = this.visibleCommands;
            if (commands.length === 0) {
                return;
            }

            const currentIndex = commands.findIndex(
                (command) => command.id === this.activeId
            );
            const nextIndex =
                currentIndex < 0
                    ? 0
                    : (currentIndex + delta + commands.length) %
                      commands.length;

            this.activeId = commands[nextIndex].id;
            this.scrollActiveIntoView();
        },

        scrollActiveIntoView() {
            this.$nextTick(() => {
                const option = document.getElementById(this.activeOptionId);
                if (option && typeof option.scrollIntoView === 'function') {
                    option.scrollIntoView({ block: 'nearest' });
                }
            });
        },

        async runCommand(command) {
            if (this.isRunning || !command) {
                return;
            }

            if (command.confirm) {
                this.pendingConfirm = command;
                return;
            }

            await this.executeCommand(command);
        },

        cancelConfirm() {
            this.pendingConfirm = null;
            this.$nextTick(() => this.focusInput());
        },

        async confirmAndRun() {
            if (!this.pendingConfirm) {
                return;
            }

            const command = this.pendingConfirm;
            this.pendingConfirm = null;
            await this.executeCommand(command);
        },

        async executeCommand(command) {
            this.isRunning = true;

            try {
                if (command.type === 'navigate' && command.route) {
                    try {
                        await this.$router.push({ name: command.route });
                    } catch {
                        // Ignore duplicate-navigation rejections from vue-router.
                    }
                    this.rememberRecent(command.id);
                    // Drop the busy flag before close() — close refuses to emit
                    // while a command is still marked as running.
                    this.isRunning = false;
                    this.close();
                    return;
                }

                if (typeof command.run === 'function') {
                    await command.run(this.commandContext);
                    this.rememberRecent(command.id);
                    this.isRunning = false;
                    this.close();
                    return;
                }
            } catch (error) {
                this.createNotificationError({
                    message: error?.response?.data?.error ?? error.message,
                });
            } finally {
                this.isRunning = false;
            }
        },

        rememberRecent(id) {
            const next = [
                id,
                ...this.recentIds.filter((entry) => entry !== id),
            ].slice(0, RECENT_LIMIT);

            this.recentIds = next;

            try {
                window.localStorage.setItem(
                    RECENT_STORAGE_KEY,
                    JSON.stringify(next)
                );
            } catch {
                /* private mode / blocked storage — ignore */
            }
        },

        readRecentIds() {
            try {
                const raw = window.localStorage.getItem(RECENT_STORAGE_KEY);
                if (!raw) {
                    return [];
                }

                const parsed = JSON.parse(raw);
                return Array.isArray(parsed)
                    ? parsed.filter((entry) => typeof entry === 'string')
                    : [];
            } catch {
                return [];
            }
        },

        close() {
            if (this.isRunning) {
                return;
            }

            this.$emit('close');
        },
    },
});
