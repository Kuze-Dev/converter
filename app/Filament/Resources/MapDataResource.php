<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\MapData;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Resources\Resource;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use App\Filament\Imports\MapDataImporter;
use Filament\Tables\Actions\ImportAction;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
use App\Filament\Resources\MapDataResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\MapDataResource\RelationManagers;

class MapDataResource extends Resource
{
    protected static ?string $model = MapData::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Data Mapping';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
               Section::make('Map Data')
                ->schema([
                TextInput::make('original_data')
                    ->label('Original Data')
                    ->maxLength(255)
                    ->required()
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        if ($get('auto_slug')) {
                            $set('mapped_data', Str::slug($state));
                        }
                    })
                    ->live(onBlur: true), 
                Toggle::make('auto_slug')
                    ->label('Auto-Slug')
                    ->live() 
                    ->afterStateUpdated(function ($state, callable $get, callable $set) {
                        if ($state && $get('original_data')) {
                            $set('mapped_data', Str::slug($get('original_data')));
                        }
                    }),
                    
                TextInput::make('mapped_data')
                    ->label('Mapped Data')
                    ->maxLength(255)
                    ->required(),
                    ]),

                Section::make('Preview')
                ->schema([
                Placeholder::make('preview_mapping')
                    ->label('Preview')
                    ->content(fn ($get) => $get('original_data') || $get('mapped_data')
                        ? "{$get('original_data')} => {$get('mapped_data')}"
                        : '—'),
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('original_data')
                    ->searchable(),
                Tables\Columns\TextColumn::make('mapped_data')
                    ->label('Value')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                ImportAction::make()
                ->importer(MapDataImporter::class)
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMapData::route('/'),
            // 'create' => Pages\CreateMapData::route('/create'),
            'view' => Pages\ViewMapData::route('/{record}'),
            'edit' => Pages\EditMapData::route('/{record}/edit'),
        ];
    }
}
