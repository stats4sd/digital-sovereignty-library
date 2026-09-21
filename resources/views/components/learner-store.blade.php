{{--
    Browser-side learner state for one session page (spec §7): one localStorage blob per session
    under `dsl:v1:{moduleKey}:{sessionSlug}`, a flat map of item paths ({itemKey}, {itemKey}.{field},
    {itemKey}.{row}.{field}, {itemKey}.attempts) to values. Every storage access is wrapped in
    try/catch so private windows and blocked storage degrade to in-memory state with a notice.
    Must be rendered before @livewireScripts so the alpine:init listener is registered in time.
--}}
@props(['namespace'])

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('learner', {
            namespace: @js($namespace),
            data: {},
            available: true,
            status: 'idle',
            timer: null,

            init() {
                try {
                    const probeKey = `${this.namespace}:probe`;
                    window.localStorage.setItem(probeKey, '1');
                    window.localStorage.removeItem(probeKey);

                    const raw = window.localStorage.getItem(this.namespace);
                    const parsed = raw ? JSON.parse(raw) : {};
                    this.data = parsed && typeof parsed === 'object' && ! Array.isArray(parsed) ? parsed : {};
                } catch (error) {
                    this.available = false;
                    this.status = 'unavailable';
                    this.data = {};
                }

                window.addEventListener('pagehide', () => this.flush());
            },

            get(path, fallback = '') {
                const value = this.data[path];

                return value === undefined || value === null ? fallback : value;
            },

            set(path, value) {
                this.data[path] = value;
                this.status = this.available ? 'saving' : 'unavailable';

                window.clearTimeout(this.timer);
                this.timer = window.setTimeout(() => this.flush(), 400);
            },

            record(path, value) {
                this.data[path] = value;
                window.clearTimeout(this.timer);
                this.flush();
            },

            flush() {
                window.clearTimeout(this.timer);
                this.timer = null;

                if (! this.available) {
                    return;
                }

                try {
                    window.localStorage.setItem(this.namespace, JSON.stringify(this.data));
                    this.status = 'saved';
                } catch (error) {
                    this.available = false;
                    this.status = 'unavailable';
                }
            },
        });

        Alpine.data('curriculumQuiz', (quiz) => ({
            quiz,
            picks: {},
            reviewing: null,
            attempts: [],
            labels: {},

            init() {
                const stored = Alpine.store('learner').get(`${this.quiz.key}.attempts`, []);
                this.attempts = Array.isArray(stored) ? stored.filter((attempt) => attempt && typeof attempt.at === 'string') : [];

                const labels = document.getElementById(`quiz-labels-${this.quiz.key}`);
                this.labels = labels ? JSON.parse(labels.textContent) : {};
            },

            question(id) {
                return this.quiz.items.find((item) => item.id === id);
            },

            selected(questionId) {
                return this.picks[questionId] ?? [];
            },

            isChecked(questionId, optionId) {
                return this.selected(questionId).includes(optionId);
            },

            choose(questionId, optionId, checked) {
                const question = this.question(questionId);

                if (! question) {
                    return;
                }

                if (question.kind === 'pick-one') {
                    this.picks[questionId] = checked ? [optionId] : [];

                    return;
                }

                const current = this.selected(questionId).filter((id) => id !== optionId);
                this.picks[questionId] = checked ? [...current, optionId] : current;
            },

            answeredCount() {
                return this.quiz.items.filter((item) => this.selected(item.id).length > 0).length;
            },

            answeredLabel() {
                return this.format(this.labels.answered ?? '%1$d of %2$d answered', this.answeredCount(), this.quiz.items.length);
            },

            scoreLabel(attempt) {
                return this.format(this.labels.scoreOf ?? '%1$d of %2$d correct', attempt.score, attempt.total);
            },

            format(template, first, second) {
                return template.replace('%1$d', first).replace('%2$d', second);
            },

            isCorrect(questionId) {
                const question = this.question(questionId);

                if (! question) {
                    return false;
                }

                const chosen = [...this.selected(questionId)].sort();
                const key = [...question.correct].sort();

                if (key.length === 0) {
                    return false;
                }

                return chosen.length === key.length && chosen.every((id, index) => id === key[index]);
            },

            outcome(questionId) {
                if (this.selected(questionId).length === 0) {
                    return 'unanswered';
                }

                return this.isCorrect(questionId) ? 'correct' : 'incorrect';
            },

            isKey(questionId, optionId) {
                if (this.reviewing === null) {
                    return false;
                }

                return (this.question(questionId)?.correct ?? []).includes(optionId);
            },

            submit() {
                const total = this.quiz.items.length;
                const score = this.quiz.items.filter((item) => this.isCorrect(item.id)).length;
                const attempt = {
                    at: new Date().toISOString(),
                    answers: JSON.parse(JSON.stringify(this.picks)),
                    score,
                    total,
                    passed: score >= this.quiz.passMark,
                };

                this.attempts = [...this.attempts, attempt];
                Alpine.store('learner').record(`${this.quiz.key}.attempts`, this.attempts);
                this.reviewing = attempt;

                this.$nextTick(() => this.$refs.resultHeading?.focus());
            },

            tryAgain() {
                this.picks = {};
                this.reviewing = null;
            },

            formatWhen(iso) {
                const date = new Date(iso);

                return Number.isNaN(date.getTime()) ? '' : date.toLocaleString(document.documentElement.lang || undefined);
            },
        }));
    });
</script>
