<?php

namespace App\Filament\Resources\CurriculumSessionResource\Pages;

use App\Filament\Resources\CurriculumModuleResource;
use App\Filament\Resources\CurriculumSessionResource;
use App\Services\CurriculumSessionContentSync;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class EditCurriculumSession extends EditRecord
{
    use Translatable;

    protected static string $resource = CurriculumSessionResource::class;

    public ?string $activeLocale = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->activeLocale = static::getResource()::getDefaultTranslatableLocale();
    }

    /**
     * Every translatable field on this page (the session's own and those inside the content
     * blocks) is a TranslatableComboField holding a full locale dictionary. The lara-zeus
     * content driver would re-wrap that under the active locale and double-nest the JSON,
     * so it stays disabled here (see docs/change-logs/curriculum-sessions-module-page.md).
     */
    public function getFilamentTranslatableContentDriver(): ?string
    {
        return null;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['content'] = app(CurriculumSessionContentSync::class)->toBlocks($this->getRecord());

        return $data;
    }

    /**
     * Save the session's own fields and sync the content blocks to item rows in one
     * transaction. `content` is the Builder's dehydrated block list, not a session column,
     * so it is pulled out of $data before the model update.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $blocks = Arr::pull($data, 'content') ?? [];

        try {
            return DB::transaction(function () use ($record, $data, $blocks): Model {
                $record = parent::handleRecordUpdate($record, $data);

                app(CurriculumSessionContentSync::class)->apply($record, $blocks);

                return $record;
            });
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title('The content could not be saved')
                ->body(implode(' ', Arr::flatten($exception->errors())))
                ->persistent()
                ->send();

            throw new Halt;
        }
    }

    /**
     * Re-hydrate from the saved rows so the Builder holds every item's resolved key (a
     * block saved without a key, or with a duplicated one, was given a fresh uuid by the
     * sync and must not be re-created on the next save).
     */
    protected function afterSave(): void
    {
        $this->fillForm();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToModule')
                ->label('Back to module')
                ->url(fn () => CurriculumModuleResource::getUrl('edit', ['record' => $this->record->curriculum_module_id])),
        ];
    }
}
