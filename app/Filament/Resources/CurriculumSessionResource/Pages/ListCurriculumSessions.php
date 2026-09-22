<?php

namespace App\Filament\Resources\CurriculumSessionResource\Pages;

use App\Filament\Resources\CurriculumSessionResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Not linked from navigation (this resource's edit page is only reached from the module's
 * SessionsRelationManager "Resources" row action) — this index page exists only because
 * Filament requires resources to have an [index] page to generate breadcrumbs/back links.
 */
class ListCurriculumSessions extends ListRecords
{
    protected static string $resource = CurriculumSessionResource::class;
}
