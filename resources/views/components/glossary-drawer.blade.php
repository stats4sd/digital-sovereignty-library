@php
    $glossaryTerms = \App\Models\GlossaryTerm::all()
        ->mapWithKeys(fn ($term) => [$term->getTranslation('term', app()->getLocale()) => [
            'def' => $term->getTranslation('definition', app()->getLocale()),
            'source' => $term->source,
            'source_url' => $term->source_url,
        ]])
        ->filter(fn ($entry, $term) => $term !== '' && $entry['def'] !== '')
        ->sortKeys();
@endphp

@if($glossaryTerms->isNotEmpty())
<div x-data="{
        open: false,
        query: '',
        sourceFilter: null,
        focusTerm: null,
        terms: JSON.parse(document.getElementById('glossary-data').textContent),
        get sources() {
            return [...new Set(Object.values(this.terms).map((e) => e.source).filter(Boolean))].sort();
        },
        get filtered() {
            const q = this.query.trim().toLowerCase();
            return Object.entries(this.terms).filter(([term, entry]) =>
                (!this.sourceFilter || entry.source === this.sourceFilter)
                && (!q || term.toLowerCase().includes(q) || entry.def.toLowerCase().includes(q))
            );
        },
        openDrawer(term = null) {
            this.query = '';
            this.sourceFilter = null;
            this.focusTerm = term;
            this.open = true;
            this.$nextTick(() => {
                // x-trap focuses the first control; prefer the search box (without
                // scrolling the list, so a term deep-link stays in view).
                this.$refs.searchInput?.focus({ preventScroll: true });
                if (term) {
                    const el = document.getElementById('gloss-' + this.slug(term));
                    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        },
        slug(term) {
            return term.toLowerCase().replace(/[^a-z0-9]+/g, '-');
        },
    }"
    x-on:open-glossary.window="openDrawer($event.detail?.term ?? null)"
    x-on:keydown.escape.window="open = false">

    <script type="application/json" id="glossary-data">{!! $glossaryTerms->toJson(JSON_UNESCAPED_UNICODE) !!}</script>

    {{-- Backdrop --}}
    <div class="fixed inset-0 bg-black/35 z-40" x-show="open" x-transition.opacity x-on:click="open = false" style="display: none;"></div>

    {{-- Drawer --}}
    <div class="fixed top-0 right-0 h-full w-96 max-w-[88vw] bg-brand-bg shadow-2xl z-50 flex flex-col"
        role="dialog"
        aria-modal="true"
        aria-label="{{ t('Glossary') }}"
        x-trap.inert.noscroll="open"
        x-show="open"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        style="display: none;">

        <div class="flex items-center justify-between px-6 pt-6 pb-4 border-b border-brand-primary/10">
            <h3 class="text-xl font-bold text-brand-primary">{{ t('Glossary') }}</h3>
            <button type="button" class="text-2xl leading-none text-gray-500 hover:text-brand-secondary" aria-label="{{ t('Close glossary') }}" x-on:click="open = false">&times;</button>
        </div>

        <div class="px-6 py-4 border-b border-brand-primary/10">
            <input type="text" x-model="query" x-ref="searchInput"
                placeholder="{{ t('Search terms…') }}"
                class="w-full rounded-lg border border-brand-primary/20 bg-white/70 px-3 py-2 text-sm focus:border-brand-secondary focus:ring-0">

            {{-- Source filter — only shown once terms from more than one glossary exist. --}}
            <div class="flex flex-wrap gap-1.5 mt-3" x-show="sources.length > 1" x-cloak>
                <button type="button"
                    class="rounded-full border px-2.5 py-0.5 text-xs transition-colors"
                    :class="sourceFilter === null ? 'bg-brand-secondary text-white border-brand-secondary' : 'border-brand-primary/20 text-gray-500 hover:border-brand-secondary'"
                    x-on:click="sourceFilter = null">{{ t('All sources') }}</button>
                <template x-for="source in sources" :key="source">
                    <button type="button"
                        class="rounded-full border px-2.5 py-0.5 text-xs transition-colors"
                        :class="sourceFilter === source ? 'bg-brand-secondary text-white border-brand-secondary' : 'border-brand-primary/20 text-gray-500 hover:border-brand-secondary'"
                        x-on:click="sourceFilter = (sourceFilter === source ? null : source)"
                        x-text="source"></button>
                </template>
            </div>
        </div>

        <div class="overflow-y-auto px-6 pb-8 flex-1">
            <template x-for="[term, entry] in filtered" :key="term">
                <div class="py-4 border-b border-brand-primary/10 scroll-mt-5 rounded-lg"
                    :id="'gloss-' + slug(term)"
                    :class="focusTerm === term ? 'bg-brand-secondary/15 px-3 -mx-3' : ''">
                    <div class="font-semibold text-brand-primary text-sm mb-1" x-text="term"></div>
                    <div class="text-sm text-gray-600 leading-relaxed" x-text="entry.def"></div>
                    <div class="text-xs text-gray-400 mt-1.5" x-show="entry.source">
                        {{ t('Source:') }}
                        <template x-if="entry.source_url">
                            <a class="underline hover:text-brand-secondary" :href="entry.source_url" target="_blank" rel="noopener" x-text="entry.source"></a>
                        </template>
                        <template x-if="!entry.source_url">
                            <span x-text="entry.source"></span>
                        </template>
                    </div>
                </div>
            </template>
            <div class="text-sm text-gray-400 text-center py-6" x-show="filtered.length === 0">{{ t('No matching terms.') }}</div>

            <div class="pt-5 text-xs text-gray-400 leading-relaxed space-y-2">
                <p>{{ t('This glossary is based on work by Marion Girard Cisneros, used with permission, and is adapted and extended over time.') }}</p>
                <p class="font-semibold text-gray-500">{{ t('Other glossaries worth consulting:') }}</p>
                <ul class="list-disc ml-4 space-y-1">
                    <li>
                        <a class="underline hover:text-brand-secondary" href="https://lfs-bcacarn-2023.sites.olt.ubc.ca/files/2024/07/Toolkit-Glossary-EN-24.03.27-2.pdf" target="_blank" rel="noopener">
                            {{ t('Glossary — Data Governance in Agriculture (UBC toolkit, PDF)') }}
                        </a>
                    </li>
                    <li>
                        <a class="underline hover:text-brand-secondary" href="https://opendatahandbook.org/glossary/en/" target="_blank" rel="noopener">
                            {{ t('Open Data Handbook Glossary') }}
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const terms = JSON.parse(document.getElementById('glossary-data').textContent);
        // Longest-first so "data sovereignty" wins over "data rights"-style overlaps.
        const ordered = Object.keys(terms).sort((a, b) => b.length - a.length);
        if (!ordered.length) return;

        const escapeRe = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        // \b only works next to word characters, so terms that start/end with a
        // non-word character (e.g. a trailing "(FPIC)") get no boundary marker
        // there — otherwise they could never match.
        const withBoundaries = (t) => (/^\w/.test(t) ? '\\b' : '') + escapeRe(t) + (/\w$/.test(t) ? '\\b' : '');
        const pattern = new RegExp('(' + ordered.map(withBoundaries).join('|') + ')', 'i');

        document.querySelectorAll('[data-glossary-scope]').forEach((scope) => {
            const walker = document.createTreeWalker(scope, NodeFilter.SHOW_TEXT, {
                acceptNode: (node) =>
                    node.parentElement.closest('a, button, .glossary-term')
                        ? NodeFilter.FILTER_REJECT
                        : NodeFilter.FILTER_ACCEPT,
            });

            const textNodes = [];
            while (walker.nextNode()) textNodes.push(walker.currentNode);

            textNodes.forEach((node) => {
                let current = node;
                let match;
                // Every occurrence is annotated, so the affordance is wherever the reader is.
                while ((match = current.textContent.match(pattern)) !== null) {
                    const canonical = ordered.find((t) => t.toLowerCase() === match[1].toLowerCase());

                    const span = document.createElement('span');
                    span.className = 'glossary-term';
                    span.tabIndex = 0;
                    span.textContent = match[1];
                    span.dataset.term = canonical;

                    const after = current.splitText(match.index);
                    after.textContent = after.textContent.slice(match[1].length);
                    current.parentNode.insertBefore(span, after);
                    current = after;
                }
            });
        });

        // Shared tooltip element, content set via textContent only.
        const tip = document.createElement('div');
        tip.className = 'glossary-tip';
        tip.style.display = 'none';
        const tipTerm = document.createElement('div');
        tipTerm.className = 'glossary-tip-term';
        const tipDef = document.createElement('div');
        tipDef.className = 'glossary-tip-def';
        const tipLink = document.createElement('button');
        tipLink.type = 'button';
        tipLink.className = 'glossary-tip-link';
        tipLink.textContent = @js(t('Open full glossary →'));
        tip.append(tipTerm, tipDef, tipLink);
        document.body.appendChild(tip);

        let hideTimer = null;
        let currentTerm = null;

        const showTip = (el) => {
            clearTimeout(hideTimer);
            currentTerm = el.dataset.term;
            tipTerm.textContent = currentTerm;
            tipDef.textContent = terms[currentTerm]?.def ?? '';
            tip.style.display = 'block';
            const r = el.getBoundingClientRect();
            const left = Math.max(12, Math.min(r.left + r.width / 2 - tip.offsetWidth / 2, window.innerWidth - tip.offsetWidth - 12));
            let top = r.bottom + 6;
            if (top + tip.offsetHeight > window.innerHeight - 12) top = r.top - tip.offsetHeight - 6;
            tip.style.left = left + 'px';
            tip.style.top = top + 'px';
        };
        const hideTip = () => {
            hideTimer = setTimeout(() => { tip.style.display = 'none'; }, 300);
        };

        document.querySelectorAll('.glossary-term').forEach((el) => {
            el.addEventListener('mouseenter', () => showTip(el));
            el.addEventListener('focus', () => showTip(el));
            el.addEventListener('mouseleave', hideTip);
            el.addEventListener('blur', hideTip);
            el.addEventListener('click', (e) => { e.stopPropagation(); showTip(el); });
        });
        tip.addEventListener('mouseenter', () => clearTimeout(hideTimer));
        tip.addEventListener('mouseleave', hideTip);
        tipLink.addEventListener('click', () => {
            tip.style.display = 'none';
            window.dispatchEvent(new CustomEvent('open-glossary', { detail: { term: currentTerm } }));
        });
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.glossary-term') && !e.target.closest('.glossary-tip')) tip.style.display = 'none';
        });
        window.addEventListener('scroll', () => { tip.style.display = 'none'; }, true);
    });
</script>
@endif
