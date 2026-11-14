<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\TransitionOrder;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidOrderTransition;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function getTitle(): string
    {
        return "Objednávka {$this->getRecord()->getAttribute('number')}";
    }

    protected function getHeaderActions(): array
    {
        return array_map($this->transitionAction(...), OrderStatus::cases());
    }

    private function transitionAction(OrderStatus $to): Action
    {
        $label = match ($to) {
            OrderStatus::New => 'Vrátit mezi nové',
            OrderStatus::Processing => 'Začít zpracovávat',
            OrderStatus::Shipped => 'Odeslat',
            OrderStatus::Completed => 'Dokončit',
            OrderStatus::Cancelled => 'Zrušit objednávku',
        };

        return Action::make($to->value)
            ->label($label)
            ->color($to === OrderStatus::Cancelled ? 'danger' : 'primary')
            ->requiresConfirmation()
            // The default "Zrušit" (close) next to "Zrušit objednávku" reads as the opposite.
            ->modalCancelActionLabel('Zpět')
            ->modalSubmitActionLabel($label)
            ->modalDescription($to === OrderStatus::Cancelled
                ? 'Zboží z objednávky se vrátí na sklad.'
                : "Stav objednávky se změní na „{$to->label()}“.")
            ->visible(fn (Order $record): bool => $record->status->canTransitionTo($to))
            ->action(function (Order $record) use ($to): void {
                try {
                    app(TransitionOrder::class)->handle($record, $to);
                } catch (InvalidOrderTransition $e) {
                    // Someone else changed the order in the meantime.
                    $record->refresh();

                    Notification::make()
                        ->danger()
                        ->title('Stav se nezměnil')
                        ->body("Objednávka je mezitím ve stavu „{$e->from->label()}“.")
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title("Objednávka je ve stavu „{$to->label()}“.")
                    ->send();
            });
    }
}
