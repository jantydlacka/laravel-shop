<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use App\Support\Money;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Název')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Kategorie'),
                TextColumn::make('price')
                    ->label('Cena s DPH')
                    ->formatStateUsing(fn (int $state): string => Money::format($state))
                    ->sortable(),
                TextColumn::make('stock')
                    ->label('Skladem')
                    ->sortable()
                    ->color(fn (Product $record): ?string => $record->isInStock() ? null : 'danger'),
                IconColumn::make('is_available')
                    ->label('Dostupný')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->label('Kategorie')
                    ->relationship('category', 'name'),
                TernaryFilter::make('is_available')
                    ->label('Dostupný'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
