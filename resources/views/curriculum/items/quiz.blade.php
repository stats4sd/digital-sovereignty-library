@use('App\Support\TranslatableText')
@php
    $headingId = "item-{$item->key}";
    $questions = collect($config['items'] ?? [])
        ->filter(fn ($question) => is_array($question) && filled($question['id'] ?? null))
        ->values();
    $passMark = max(1, min((int) ($config['passMark'] ?? 1), max(1, $questions->count())));
    $payload = [
        'key' => $item->key,
        'passMark' => $passMark,
        'items' => $questions->map(fn (array $question) => [
            'id' => $question['id'],
            'kind' => $question['kind'] ?? 'pick-one',
            'correct' => collect($question['options'] ?? [])
                ->filter(fn ($option) => is_array($option) && ($option['correct'] ?? false))
                ->pluck('id')
                ->values()
                ->all(),
        ])->all(),
    ];
    $labels = [
        'passed' => t('Passed'),
        'notPassed' => t('Not passed yet'),
        'notPassedShort' => t('Not passed'),
        'attempt' => t('Attempt %d'),
        'scoreOf' => t('%1$d of %2$d correct'),
    ];
@endphp

<section class="note-block mt-14" aria-labelledby="{{ $headingId }}" data-item-type="quiz"
    x-data="curriculumQuiz(JSON.parse(document.getElementById(@js("quiz-data-{$item->key}")).textContent))">
    <script type="application/json" id="quiz-data-{{ $item->key }}">@json($payload)</script>

    @include('curriculum.items._heading', ['id' => $headingId, 'heading' => $item->text('heading')])

    <p class="mt-4 text-base text-brand-ink-muted">
        {{ n('%d question.', '%d questions.', $questions->count(), $questions->count()) }}
        {{ n('Pass mark: %d correct answer.', 'Pass mark: %d correct answers.', $passMark, $passMark) }}
        {{ t('You can attempt it as many times as you like.') }}
    </p>

    <form @submit.prevent="submit()" class="mt-8" :aria-describedby="reviewing ? 'quiz-result-{{ $item->key }}' : null">
        <ol class="divide-y-2 divide-brand-line border-y-2 border-brand-line">
            @foreach($questions as $index => $question)
                @php
                    $questionId = $question['id'];
                    $isPickOne = ($question['kind'] ?? 'pick-one') === 'pick-one';
                    $feedbackCorrect = TranslatableText::pick($question['feedback']['correct'] ?? null);
                    $feedbackIncorrect = TranslatableText::pick($question['feedback']['incorrect'] ?? null);
                @endphp
                <li class="py-6">
                    <fieldset :disabled="reviewing !== null" class="quiz-item">
                        <legend class="text-base font-bold text-brand-primary" data-glossary-scope>
                            <span class="text-brand-secondary">{{ $index + 1 }}.</span>
                            {{ TranslatableText::pick($question['stem'] ?? null) }}
                            <span class="ms-2 text-sm font-semibold text-brand-ink-muted">({{ $isPickOne ? t('pick one') : t('pick all that apply') }})</span>
                        </legend>

                        <ul class="mt-3 grid gap-2">
                            @foreach(($question['options'] ?? []) as $option)
                                @if(is_array($option) && filled($option['id'] ?? null))
                                    @php $domId = "quiz-{$item->key}-{$questionId}-{$option['id']}"; @endphp
                                    <li>
                                        <label for="{{ $domId }}"
                                            class="flex items-start gap-3 rounded-xl border-2 bg-brand-surface px-3 py-2 text-base"
                                            :class="isKey(@js($questionId), @js($option['id'])) ? 'border-brand-success' : 'border-brand-line'">
                                            <input id="{{ $domId }}"
                                                type="{{ $isPickOne ? 'radio' : 'checkbox' }}"
                                                name="quiz-{{ $item->key }}-{{ $questionId }}"
                                                value="{{ $option['id'] }}"
                                                class="mt-1.5 accent-brand-primary"
                                                :checked="isChecked(@js($questionId), @js($option['id']))"
                                                @change="choose(@js($questionId), @js($option['id']), $event.target.checked)">
                                            <span>
                                                {{ TranslatableText::pick($option['text'] ?? null) }}
                                                <span x-show="isKey(@js($questionId), @js($option['id']))" x-cloak class="ms-1 text-sm font-semibold text-brand-success">({{ t('correct answer') }})</span>
                                            </span>
                                        </label>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </fieldset>

                    <template x-if="reviewing">
                        <div class="mt-3 rounded-xl border-2 p-4 text-base"
                            :class="outcome(@js($questionId)) === 'correct' ? 'border-brand-success' : 'border-brand-footer-text'">
                            <p class="font-bold" :class="outcome(@js($questionId)) === 'correct' ? 'text-brand-success' : 'text-brand-footer-text'">
                                <span x-show="outcome(@js($questionId)) === 'correct'">{{ t('Correct') }}</span>
                                <span x-show="outcome(@js($questionId)) === 'incorrect'">{{ t('Incorrect') }}</span>
                                <span x-show="outcome(@js($questionId)) === 'unanswered'">{{ t('Not answered') }}</span>
                            </p>
                            @if($feedbackCorrect)
                                <p class="mt-1" x-show="outcome(@js($questionId)) === 'correct'">{{ $feedbackCorrect }}</p>
                            @endif
                            @if($feedbackIncorrect)
                                <p class="mt-1" x-show="outcome(@js($questionId)) !== 'correct'">{{ $feedbackIncorrect }}</p>
                            @endif
                        </div>
                    </template>
                </li>
            @endforeach
        </ol>

        <div id="quiz-result-{{ $item->key }}" aria-live="polite" class="mt-6">
            <template x-if="reviewing">
                <div class="rounded-2xl border-2 p-6" :class="reviewing.passed ? 'border-brand-success bg-brand-surface' : 'border-brand-warning bg-brand-surface-muted'">
                    <h3 x-ref="resultHeading" tabindex="-1" class="text-xl font-bold text-brand-primary">
                        <span x-text="reviewing.passed ? @js($labels['passed']) : @js($labels['notPassed'])"></span>
                        &mdash;
                        <span x-text="scoreLabel(reviewing)"></span>
                    </h3>
                    <p class="mt-2 text-base">
                        {{ t('The pass mark is %d.', $passMark) }}
                        <span x-show="! reviewing.passed">{{ t('Review the feedback above, then try again.') }}</span>
                    </p>
                    <p class="mt-2 text-sm text-brand-ink-muted">
                        <span x-show="$store.learner.available">{{ t('This attempt is saved in this browser.') }}</span>
                        <span x-show="! $store.learner.available">{{ t('Not saved: this browser is not storing anything, so this attempt is lost on reload.') }}</span>
                    </p>
                </div>
            </template>
        </div>

        <div class="mt-6 flex flex-wrap items-center gap-4">
            <template x-if="reviewing === null">
                <div class="flex flex-wrap items-center gap-4">
                    <button type="submit" class="brand-pill">{{ t('Submit answers') }}</button>
                    <span class="text-sm text-brand-ink-muted" x-text="answeredLabel()"></span>
                </div>
            </template>
            <template x-if="reviewing !== null">
                <button type="button" class="brand-pill brand-pill-outline" @click="tryAgain()">{{ t('Try again') }}</button>
            </template>
        </div>
    </form>

    <div class="mt-8" x-show="attempts.length > 0" x-cloak>
        <h3 class="text-lg font-bold text-brand-footer-text">{{ t('Your attempts') }}</h3>
        <ol class="mt-2 divide-y divide-brand-line">
            <template x-for="(attempt, index) in attempts" :key="attempt.at">
                <li class="flex flex-wrap items-baseline gap-x-4 gap-y-1 py-2 text-base">
                    <span class="font-semibold" x-text="@js($labels['attempt']).replace('%d', index + 1)"></span>
                    <span class="text-brand-ink-muted" x-text="formatWhen(attempt.at)"></span>
                    <span x-text="scoreLabel(attempt)"></span>
                    <span :class="attempt.passed ? 'font-bold text-brand-success' : 'text-brand-footer-text'"
                        x-text="attempt.passed ? @js($labels['passed']) : @js($labels['notPassedShort'])"></span>
                </li>
            </template>
        </ol>
    </div>

    <script type="application/json" id="quiz-labels-{{ $item->key }}">@json(['scoreOf' => $labels['scoreOf'], 'answered' => t('%1$d of %2$d answered')])</script>
</section>
