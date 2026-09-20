# Work Patterns

How Claude should track plans, change logs, reviews and issues in this repo. Referenced from `CLAUDE.md`.

## Plans

1. Save all accepted plans to `docs/plans/`.
2. Every plan file must have a **Status:** line indicating the current status of the plan. One of: "Not Started", "In Progress", "Completed", "Abandoned".
    - In Progress plans must have a short paragraph explaining what has been done and what is left to do.
    - Abandoned plans must have a short paragraph explaining the reason for not completing the plan.
    - Completed plans must have a link to the corresponding change log file.
3. Design specs written before a plan (e.g. from a brainstorming session) live in `docs/specs/`, named `YYYY-MM-DD-<topic>-design.md`.

## Change logs

4. After completing a piece of work (e.g. finishing a plan), save a summary of the changes into `docs/change-logs/`. Change log files linked to a plan must reference the plan file in the intro.

## Code reviews

5. Code reviews conducted via the `/code-review` skill or via explicit "feature review" requests must be saved to `docs/code-reviews/`, with the following metadata at the top of the document:

    **Date**: 2026-05-27
    **Branch**: `review-roles-and-permissions`
    **Reviewer**: Claude (automated multi-angle review)
    **Scope**: 56 files, 1,945 insertions — new Spatie role/permission model with 5 roles and policies across three Filament panels.

## Issues

6. If a user asks you to capture, note or save an issue, store it in `docs/issues/` as a new `.md` file. If this is a Git repository with a GitHub origin, also create a new issue on GitHub.
