<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use App\Support\Money;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $money = fn (int $state): string => Money::format($state);

        return $schema
            ->columns(1)
            ->components([
                Section::make('Objednávka')
                    ->columns(['sm' => 2, 'lg' => 4])
                    ->schema([
                        TextEntry::make('number')->label('Číslo'),
                        TextEntry::make('status')->label('Stav')->badge(),
                        TextEntry::make('created_at')->label('Vytvořeno')->dateTime('j. n. Y H:i'),
                        TextEntry::make('note')->label('Poznámka')->placeholder('–'),
                    ]),
                Section::make('Zákazník')
                    ->columns(['sm' => 2, 'lg' => 4])
                    ->schema([
                        TextEntry::make('customer_name')->label('Jméno'),
                        TextEntry::make('email')->label('E-mail'),
                        TextEntry::make('phone')->label('Telefon'),
                        TextEntry::make('address')
                            ->label('Adresa')
                            ->state(fn (Order $record): string => "{$record->street}, {$record->zip} {$record->city}"),
                    ]),
                Section::make('Položky')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('Položky')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Produkt'),
                                TableColumn::make('Cena za kus'),
                                TableColumn::make('Množství'),
                                TableColumn::make('Celkem'),
                            ])
                            ->schema([
                                TextEntry::make('product_name'),
                                TextEntry::make('unit_price')->formatStateUsing($money),
                                TextEntry::make('quantity')->suffix(' ks'),
                                TextEntry::make('line_total')->formatStateUsing($money),
                            ]),
                    ]),
                Section::make('Souhrn')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('without_vat')
                            ->label('Bez DPH')
                            ->state(fn (Order $record): string => Money::format($record->total - $record->vat_total)),
                        TextEntry::make('vat_total')
                            ->label(fn (Order $record): string => "DPH {$record->vat_rate} %")
                            ->formatStateUsing($money),
                        TextEntry::make('total')
                            ->label('Celkem s DPH')
                            ->formatStateUsing($money)
                            ->weight('bold'),
                    ]),
            ]);
    }
}
