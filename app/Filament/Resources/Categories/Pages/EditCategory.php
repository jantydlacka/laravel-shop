<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Category;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (DeleteAction $action, Category $record): void {
                    if ($record->products()->exists()) {
                        Notification::make()
                            ->danger()
                            ->title('Kategorii nelze smazat')
                            ->body('Obsahuje produkty. Nejdřív je přesuňte do jiné kategorie.')
                            ->send();

                        $action->cancel();
                    }
                }),
        ];
    }
}
