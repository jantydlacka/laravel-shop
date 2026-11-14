<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (DeleteAction $action, Product $record): void {
                    if ($record->orderItems()->exists()) {
                        Notification::make()
                            ->danger()
                            ->title('Produkt nelze smazat')
                            ->body('Je v objednávkách. Místo smazání vypněte „Dostupný v obchodě“.')
                            ->send();

                        $action->cancel();
                    }
                }),
        ];
    }
}
