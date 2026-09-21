{{-- Per-note-item line telling the learner where their notes live. Static text: the store's
     status is not announced live, so screen readers are not interrupted on every keystroke. --}}
<p class="mt-3 text-sm text-brand-ink-muted" x-data>
    <span x-show="$store.learner.available">{{ t('Your notes are saved in this browser only. Clearing your browser data removes them.') }}</span>
    <span x-show="! $store.learner.available" x-cloak>{{ t('Not saved: this browser is not storing anything, so your notes will be lost when you leave the page.') }}</span>
</p>
