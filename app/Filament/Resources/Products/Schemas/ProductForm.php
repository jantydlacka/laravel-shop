<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Product;
use App\Support\Money;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Název')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state, string $operation): void {
                        if ($operation === 'create') {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                TextInput::make('slug')
                    ->label('Adresa (slug)')
                    ->required()
                    ->alphaDash()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('category_id')
                    ->label('Kategorie')
                    ->relationship('category', 'name')
                    ->required(),
                // Entered in crowns, stored in hellers.
                TextInput::make('price')
                    ->label('Cena s DPH')
                    ->suffix('Kč')
                    ->required()
                    ->regex(Money::CROWNS_PATTERN)
                    ->validationMessages(['regex' => 'Zadejte cenu v korunách, např. 1299 nebo 1299,50.'])
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (is_string($value) && preg_match(Money::CROWNS_PATTERN, trim($value)) === 1
                            && Money::parseCrowns($value) > Product::MAX_PRICE) {
                            $fail('Cena může být nejvýš '.Money::format(Product::MAX_PRICE).'.');
                        }
                    })
                    ->formatStateUsing(fn (?int $state): ?string => $state === null ? null : Money::toCrowns($state))
                    ->dehydrateStateUsing(fn (string $state): int => Money::parseCrowns($state)),
                TextInput::make('stock')
                    ->label('Skladem (ks)')
                    ->integer()
                    ->minValue(0)
                    ->maxValue(Product::MAX_STOCK)
                    ->default(0)
                    ->required(),
                Toggle::make('is_available')
                    ->label('Dostupný v obchodě')
                    ->default(true),
                Textarea::make('description')
                    ->label('Popis')
                    ->required()
                    ->rows(5)
                    ->columnSpanFull(),
                FileUpload::make('image_path')
                    ->label('Obrázek')
                    ->image()
                    ->disk('public')
                    ->directory('products')
                    ->columnSpanFull(),
            ]);
    }
}
