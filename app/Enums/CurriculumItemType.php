<?php

namespace App\Enums;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * The kind of content block a CurriculumSessionItem holds. The string value is stored in
 * curriculum_session_items.type and selects the item's Blade view, Filament Builder block
 * and config shape (see docs/specs/2026-09-21-curriculum-session-content-design.md §5).
 * Activity types collect learner input that is persisted client-side under the item's key.
 */
enum CurriculumItemType: string implements HasIcon, HasLabel
{
    case Prose = 'prose';
    case Callout = 'callout';
    case NotePrompt = 'note_prompt';
    case NoteCanvas = 'note_canvas';
    case NoteMatrix = 'note_matrix';
    case Quiz = 'quiz';
    case Trove = 'trove';

    public function label(): string
    {
        return match ($this) {
            self::Prose => 'Text',
            self::Callout => 'Callout',
            self::NotePrompt => 'Note prompt',
            self::NoteCanvas => 'Note canvas',
            self::NoteMatrix => 'Note matrix',
            self::Quiz => 'Quiz',
            self::Trove => 'Resource',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Prose => 'heroicon-o-document-text',
            self::Callout => 'heroicon-o-light-bulb',
            self::NotePrompt => 'heroicon-o-pencil-square',
            self::NoteCanvas => 'heroicon-o-view-columns',
            self::NoteMatrix => 'heroicon-o-table-cells',
            self::Quiz => 'heroicon-o-question-mark-circle',
            self::Trove => 'heroicon-o-book-open',
        };
    }

    /**
     * Whether learners type into this item (and so it owns a localStorage namespace).
     */
    public function isActivity(): bool
    {
        return match ($this) {
            self::NotePrompt, self::NoteCanvas, self::NoteMatrix, self::Quiz => true,
            self::Prose, self::Callout, self::Trove => false,
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getIcon(): string
    {
        return $this->icon();
    }
}
