@props(['publicUrl'])

<div class="bg-brand-primary text-white py-4 px-6 font-semibold flex flex-wrap justify-center items-center gap-x-3 gap-y-1" data-curriculum-preview>
    <x-heroicon-o-exclamation-circle class="w-6 h-6 text-white" />
    <span>{{ t('Preview') }}</span>
    <span class="font-normal">{{ t('Draft content is shown. Only editors can see this page.') }}</span>
    <a href="{{ $publicUrl }}" class="underline">{{ t('View public page') }}</a>
</div>
