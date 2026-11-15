<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;

/**
 * Orders and cancellations change the stock while the form is open, so the form writes
 * the stock only when the admin changed it, and only if it still holds the loaded value.
 *
 * @property-read Product $record
 */
class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    #[Locked]
    public int $loadedStock = 0;

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

    protected function afterFill(): void
    {
        $this->loadedStock = $this->record->stock;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $entered = (int) $data['stock'];
        unset($data['stock']);

        return DB::transaction(function () use ($record, $data, $entered): Model {
            if ($entered !== $this->loadedStock) {
                $written = Product::query()
                    ->whereKey($record->getKey())
                    ->where('stock', $this->loadedStock)
                    ->update(['stock' => $entered]);

                if ($written === 0) {
                    $current = (int) Product::query()->whereKey($record->getKey())->value('stock');

                    Notification::make()
                        ->danger()
                        ->title('Sklad se mezitím změnil')
                        ->body("Objednávka nebo storno změnily sklad na {$current} ks. Nic se neuložilo – zkontrolujte počet a uložte znovu.")
                        ->persistent()
                        ->send();

                    $this->syncStock($current);
                    $this->halt();
                }
            }

            $record->update($data);

            return $record;
        });
    }

    protected function afterSave(): void
    {
        $this->syncStock($this->record->refresh()->stock);
    }

    private function syncStock(int $stock): void
    {
        $this->loadedStock = $stock;
        $this->data['stock'] = $stock;
    }
}
