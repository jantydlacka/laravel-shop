<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Support\Money;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label('Číslo')
                    ->searchable(),
                TextColumn::make('customer_name')
                    ->label('Zákazník')
                    ->description(fn ($record): string => $record->email)
                    ->searchable(['customer_name', 'email']),
                TextColumn::make('total')
                    ->label('Celkem s DPH')
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Stav')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Vytvořeno')
                    ->dateTime('j. n. Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Stav')
                    ->options(OrderStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
