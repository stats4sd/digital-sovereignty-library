<?php

namespace App\Curriculum\Items;

use App\Enums\CurriculumItemType;
use App\Support\Curriculum\ItemImages;
use App\Support\TranslatableText;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * A single uploaded image with translatable alt text, an optional translatable caption and a
 * display size. `image` is a path string on the media disk (see ItemImages).
 */
class ImageItem extends ItemDefinition
{
    public const SIZES = [
        'wide' => 'Wide',
        'medium' => 'Medium',
        'small' => 'Small',
    ];

    public function type(): CurriculumItemType
    {
        return CurriculumItemType::Image;
    }

    public function configKeys(): array
    {
        return ['image', 'alt', 'caption', 'size'];
    }

    public function translatableLeaves(): array
    {
        return ['alt', 'caption'];
    }

    public function htmlLeaves(): array
    {
        return [];
    }

    public function rules(): array
    {
        return [
            'image' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! ItemImages::isManaged(is_string($value) ? $value : null)) {
                        $fail('The image must be an uploaded curriculum image.');
                    }
                },
            ],
            'alt' => ['required', 'array'],
            'alt.*' => ['string'],
            'caption' => ['nullable', 'array'],
            'caption.*' => ['string'],
            'size' => ['nullable', Rule::in(array_keys(self::SIZES))],
        ];
    }

    /**
     * The upload normally dehydrates to a path string; defensively accept a list (take the
     * first scalar) and treat '' as missing before validation runs.
     */
    public function normalise(array $config): array
    {
        $image = $config['image'] ?? null;

        if (is_array($image)) {
            $image = collect($image)->first(fn ($value) => is_scalar($value));
        }

        $config['image'] = ($image === null || $image === '') ? null : $image;

        return parent::normalise($config);
    }

    public function blockLabel(array $state): string
    {
        $label = parent::blockLabel($state);

        foreach (['caption', 'alt'] as $leaf) {
            $text = is_array($state[$leaf] ?? null) ? TranslatableText::pick($state[$leaf]) : null;

            if (filled($text)) {
                return $label.' ('.$text.')';
            }
        }

        return $label;
    }

    protected function fields(): array
    {
        return [
            // Not ->image(): that admits SVG, which would be served from the app's origin (stored XSS).
            // Not ->moveFiles(): it skips setVisibility('public'), leaving S3 objects private.
            // Not ->multiple(), ->preventFilePathTampering() (checks the session record, so would
            // reject every path on a second save) or ->deleteUploadedFileUsing() (removing in the
            // UI is not saving; cleanup runs in CurriculumSessionContentSync).
            FileUpload::make('image')
                ->label('Image')
                ->disk(ItemImages::diskName())
                ->directory(ItemImages::DIRECTORY)
                ->visibility('public')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif'])
                // acceptedFileTypes checks content, but Filament names the stored file after the
                // client's extension. Validate and name from the sniffed type instead, so a GIF/HTML
                // polyglot called `x.html` cannot land on the disk as `<ulid>.html`.
                ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail): void {
                    foreach ((array) $value as $file) {
                        if ($file instanceof TemporaryUploadedFile && ! in_array(strtolower((string) $file->guessExtension()), ItemImages::EXTENSIONS, true)) {
                            $fail('The image must be a JPEG, PNG, WebP or GIF file.');
                        }
                    }
                })
                ->getUploadedFileNameForStorageUsing(static function (TemporaryUploadedFile $file): string {
                    $extension = strtolower((string) $file->guessExtension());

                    return Str::ulid().'.'.(in_array($extension, ItemImages::EXTENSIONS, true) ? $extension : 'bin');
                })
                ->maxSize(5120)
                ->imageResizeTargetWidth('2000')
                ->imageResizeMode('contain')
                ->required(),

            $this->translatable('alt', 'Alt text', TextInput::make('alt')->maxLength(255), required: true)
                ->description('Describe the image for screen readers; shown if the image cannot load.'),

            $this->translatable('caption', 'Caption', Textarea::make('caption')->rows(2))
                ->description('Optional text shown under the image.'),

            Select::make('size')
                ->label('Size')
                ->options(self::SIZES)
                ->default('wide')
                ->native(false),
        ];
    }
}
