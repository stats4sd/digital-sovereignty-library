{{--
    Contents overview for a session page: one entry per renderable item that has a heading.
    Desktop (lg+): a sticky sidebar beside the content. Smaller screens: a fixed bottom bar
    showing the current section, which opens a bottom sheet to jump between sections.
    Both read the `sessionOutline` Alpine component declared on the page wrapper, which tracks
    the active section from scroll position, reading progress, and which activities already
    hold learner notes (from the learner store, so ticks are per browser).
--}}
@props(['entries', 'module', 'previous', 'next'])

<aside class="hidden lg:block" aria-label="{{ t('Session contents') }}">
    <div class="sticky top-28 max-h-[calc(100vh-8rem)] overflow-y-auto pe-2">
        <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-secondary">{{ t('In this session') }}</p>

        <ol class="mt-4 border-s-2 border-brand-line" data-session-outline="sidebar">
            @foreach($entries as $index => $entry)
                <li>
                    <a href="#{{ $entry['id'] }}"
                        x-on:click.prevent="jump('{{ $entry['id'] }}')"
                        x-bind:aria-current="active === '{{ $entry['id'] }}' ? 'location' : null"
                        x-bind:class="active === '{{ $entry['id'] }}' ? 'border-brand-primary text-brand-primary font-bold' : 'border-transparent text-brand-ink-muted hover:text-brand-ink'"
                        class="-ms-0.5 flex items-start gap-2 border-s-2 py-1.5 ps-4 text-sm leading-snug transition-colors">
                        <span class="min-w-0 flex-1">
                            <span class="block">{{ $entry['label'] }}</span>
                            @if($entry['tag'])
                                <span class="mt-0.5 block text-[0.65rem] font-semibold uppercase tracking-wider text-brand-ink-muted/80">{{ $entry['tag'] }}</span>
                            @endif
                        </span>
                        @if($entry['activity'])
                            <span class="mt-0.5 shrink-0" x-bind:title="isDone('{{ $entry['key'] }}') ? '{{ t('You have notes here') }}' : '{{ t('Activity') }}'">
                                <svg x-show="isDone('{{ $entry['key'] }}')" x-cloak class="h-4 w-4 text-brand-secondary" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/></svg>
                                <svg x-show="! isDone('{{ $entry['key'] }}')" class="h-4 w-4 text-brand-line" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="10" cy="10" r="7"/></svg>
                                <span class="sr-only" x-text="isDone('{{ $entry['key'] }}') ? '{{ t('You have notes here') }}' : '{{ t('Activity') }}'"></span>
                            </span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ol>

        <p class="mt-4 ps-4 text-xs text-brand-ink-muted" aria-hidden="true">
            <span x-text="Math.round(progress * 100)">0</span>% {{ t('read') }}
        </p>

        @if($previous || $next)
            <div class="mt-6 flex flex-col gap-2 border-t border-brand-line pt-4 text-sm">
                @if($previous)
                    <a href="{{ route('curriculum.session', ['key' => $module->key, 'session' => $previous->slug]) }}" class="font-bold text-brand-primary hover:underline">
                        <x-dir-arrow back /> {{ t('Session %d', $previous->number) }}
                    </a>
                @endif
                @if($next)
                    <a href="{{ route('curriculum.session', ['key' => $module->key, 'session' => $next->slug]) }}" class="font-bold text-brand-primary hover:underline">
                        {{ t('Session %d', $next->number) }} <x-dir-arrow />
                    </a>
                @endif
            </div>
        @endif
    </div>
</aside>

{{-- Reading progress: a thin bar along the top edge of the viewport, above the sticky header. --}}
<div class="pointer-events-none fixed inset-x-0 top-0 z-[60] h-1 bg-transparent" aria-hidden="true">
    <div class="h-full bg-brand-secondary transition-[width] duration-150 ease-out" x-bind:style="`width: ${progress * 100}%`"></div>
</div>

{{-- Mobile: bottom bar + sheet. --}}
<div class="lg:hidden">
    <div class="fixed inset-x-0 bottom-0 z-40 border-t-2 border-brand-line bg-brand-bg/95 backdrop-blur" data-session-outline="mobile-bar">
        <button type="button"
            x-on:click="open = true"
            aria-haspopup="dialog"
            x-bind:aria-expanded="open"
            class="flex w-full items-center gap-3 px-5 py-3 text-start">
            <svg class="h-5 w-5 shrink-0 text-brand-primary" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M2 4.75A.75.75 0 0 1 2.75 4h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 4.75Zm0 10.5a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75a.75.75 0 0 1-.75-.75ZM2 10a.75.75 0 0 1 .75-.75h14.5a.75.75 0 0 1 0 1.5H2.75A.75.75 0 0 1 2 10Z" clip-rule="evenodd"/></svg>
            <span class="min-w-0 flex-1">
                <span class="block text-[0.65rem] font-bold uppercase tracking-[0.2em] text-brand-secondary">
                    {{ t('Contents') }} &middot; <span x-text="positionLabel()"></span>
                </span>
                <span class="block truncate text-sm font-semibold text-brand-ink" x-text="activeLabel()"></span>
            </span>
            <svg class="h-5 w-5 shrink-0 text-brand-ink-muted" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M14.77 12.79a.75.75 0 0 1-1.06-.02L10 8.832 6.29 12.77a.75.75 0 1 1-1.08-1.04l4.25-4.5a.75.75 0 0 1 1.08 0l4.25 4.5a.75.75 0 0 1-.02 1.06Z" clip-rule="evenodd"/></svg>
        </button>
    </div>

    <div x-show="open" x-cloak class="fixed inset-0 z-50" x-on:keydown.escape.window="open = false">
        <div class="absolute inset-0 bg-black/35" x-show="open" x-transition.opacity x-on:click="open = false"></div>

        <div role="dialog" aria-modal="true" aria-label="{{ t('Session contents') }}"
            x-show="open" x-trap.noscroll="open"
            x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
            class="absolute inset-x-0 bottom-0 flex max-h-[80vh] flex-col rounded-t-2xl bg-brand-bg shadow-2xl">
            <div class="flex items-center justify-between border-b-2 border-brand-line px-5 py-4">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-brand-secondary">{{ t('In this session') }}</p>
                <button type="button" x-on:click="open = false" class="rounded-full p-1 text-brand-ink-muted hover:text-brand-ink" aria-label="{{ t('Close') }}">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <ol class="overflow-y-auto px-2 py-2" data-session-outline="sheet">
                @foreach($entries as $index => $entry)
                    <li>
                        <a href="#{{ $entry['id'] }}"
                            x-on:click.prevent="jump('{{ $entry['id'] }}')"
                            x-bind:aria-current="active === '{{ $entry['id'] }}' ? 'location' : null"
                            x-bind:class="active === '{{ $entry['id'] }}' ? 'bg-brand-surface-muted font-bold text-brand-primary' : 'text-brand-ink'"
                            class="flex items-center gap-3 rounded-xl px-3 py-3">
                            <span class="w-6 shrink-0 text-end text-xs font-bold text-brand-ink-muted">{{ $index === 0 ? '' : $index }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block leading-snug">{{ $entry['label'] }}</span>
                                @if($entry['tag'])
                                    <span class="mt-0.5 block text-[0.65rem] font-semibold uppercase tracking-wider text-brand-ink-muted/80">{{ $entry['tag'] }}</span>
                                @endif
                            </span>
                            @if($entry['activity'])
                                <svg x-show="isDone('{{ $entry['key'] }}')" x-cloak class="h-5 w-5 shrink-0 text-brand-secondary" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd"/></svg>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ol>

            @if($previous || $next)
                <div class="flex items-center justify-between gap-4 border-t-2 border-brand-line px-5 py-4 text-sm">
                    @if($previous)
                        <a href="{{ route('curriculum.session', ['key' => $module->key, 'session' => $previous->slug]) }}" class="font-bold text-brand-primary hover:underline">
                            <x-dir-arrow back /> {{ t('Session %d', $previous->number) }}
                        </a>
                    @else
                        <span></span>
                    @endif
                    @if($next)
                        <a href="{{ route('curriculum.session', ['key' => $module->key, 'session' => $next->slug]) }}" class="font-bold text-brand-primary hover:underline">
                            {{ t('Session %d', $next->number) }} <x-dir-arrow />
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('sessionOutline', (entries) => ({
            entries,
            active: entries[0]?.id ?? null,
            open: false,
            progress: 0,
            ticking: false,

            init() {
                const update = () => {
                    this.ticking = false;
                    this.track();
                };
                const schedule = () => {
                    if (! this.ticking) {
                        this.ticking = true;
                        window.requestAnimationFrame(update);
                    }
                };

                window.addEventListener('scroll', schedule, { passive: true });
                window.addEventListener('resize', schedule);
                window.addEventListener('load', schedule);
                this.$nextTick(() => this.track());
            },

            // Active = the last section whose heading has scrolled past the sticky header.
            track() {
                const threshold = 120;
                const doc = document.documentElement;
                const scrollable = doc.scrollHeight - window.innerHeight;
                this.progress = scrollable > 0 ? Math.min(1, Math.max(0, window.scrollY / scrollable)) : 1;

                let current = this.entries[0]?.id ?? null;

                for (const entry of this.entries) {
                    const element = document.getElementById(entry.id);

                    if (element && element.getBoundingClientRect().top <= threshold) {
                        current = entry.id;
                    } else {
                        break;
                    }
                }

                // At the very bottom, the last section is the one being read even if its heading is still low.
                if (scrollable > 0 && window.scrollY >= scrollable - 2 && this.entries.length) {
                    current = this.entries[this.entries.length - 1].id;
                }

                this.active = current;
            },

            jump(id) {
                this.open = false;
                const element = document.getElementById(id);

                if (! element) {
                    return;
                }

                element.scrollIntoView({ behavior: 'smooth', block: 'start' });
                window.history.replaceState(null, '', `#${id}`);

                if (! element.hasAttribute('tabindex')) {
                    element.setAttribute('tabindex', '-1');
                }
                element.focus({ preventScroll: true });
            },

            activeEntry() {
                return this.entries.find((entry) => entry.id === this.active) ?? this.entries[0];
            },

            activeLabel() {
                return this.activeEntry()?.label ?? '';
            },

            positionLabel() {
                const index = this.entries.findIndex((entry) => entry.id === this.active);

                return `${Math.max(index, 0)} / ${this.entries.length - 1}`;
            },

            // Whether the learner store holds any non-empty value under this item's key.
            isDone(key) {
                const store = Alpine.store('learner');

                if (! store) {
                    return false;
                }

                return Object.entries(store.data).some(([path, value]) => {
                    if (path !== key && ! path.startsWith(`${key}.`)) {
                        return false;
                    }

                    if (Array.isArray(value)) {
                        return value.length > 0;
                    }

                    return typeof value === 'string' ? value.trim() !== '' : value !== null && value !== undefined && value !== '';
                });
            },
        }));
    });
</script>
