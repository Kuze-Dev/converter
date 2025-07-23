<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Taxonomy;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ImportAction;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\TaxonomyResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\TaxonomyResource\RelationManagers;

class TaxonomyResource extends Resource
{
    protected static ?string $model = Taxonomy::class;

    protected static ?string $navigationLabel = 'Taxonomy Terms';

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Data Mapping';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //
            ])
            ->filters([
                //
            ])
            ->headerActions([
                ImportAction::make('import data')
            ])
            ->actions([
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
            'index' => Pages\ListTaxonomies::route('/'),
            'create' => Pages\CreateTaxonomy::route('/create'),
            'edit' => Pages\EditTaxonomy::route('/{record}/edit'),
        ];
    }
}
