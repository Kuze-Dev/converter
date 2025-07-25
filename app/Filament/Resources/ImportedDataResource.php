<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\MapData;
use App\Models\Taxonomy;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\ImportedData;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use App\Filament\Exports\MapDataExporter;
use App\Services\Import\CsvImportService;
use Filament\Tables\Actions\ExportAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\ImportedDataResource\Pages;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use App\Filament\Resources\ImportedDataResource\RelationManagers;

class ImportedDataResource extends Resource
{
    protected static ?string $model = ImportedData::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static ?string $navigationGroup = 'Data Management';

    protected static ?string $navigationLabel = 'Content Entries Converter';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('content')
                    ->maxLength(255),
                Forms\Components\TextInput::make('data')
                    ->required(),
                Forms\Components\TextInput::make('taxonomy_terms')
                    ->maxLength(255),
                Forms\Components\TextInput::make('title')
                    ->maxLength(255),
                Forms\Components\TextInput::make('route_url')
                    ->maxLength(255),
                Forms\Components\Toggle::make('status'),
                Forms\Components\TextInput::make('sites')
                    ->maxLength(255),
                Forms\Components\TextInput::make('locale')
                    ->maxLength(255),
                Forms\Components\DatePicker::make('published_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('content')
                    ->searchable(),
                Tables\Columns\TextColumn::make('taxonomy_terms')
                    ->searchable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                Tables\Columns\TextColumn::make('route_url')
                    ->searchable(),
                Tables\Columns\IconColumn::make('status')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sites')
                    ->searchable(),
                Tables\Columns\TextColumn::make('locale')
                    ->searchable(),
                Tables\Columns\TextColumn::make('published_at')
                    ->date()
                    ->sortable(),
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

                ])
            ->headerActions([
                Action::make('import_data')
                ->slideOver()
                ->form([
                    Forms\Components\FileUpload::make('uploaded_file')
                        ->label('Upload CSV File')
                        ->acceptedFileTypes(['text/csv', 'text/plain'])
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, $set) {
                            if (empty($state)) {
                                $set('csv_headers', []);
                                return;
                            }

                            try {
                                $filePath = null;
                                
                                if ($state instanceof TemporaryUploadedFile) {
                                    $filePath = $state->getRealPath();
                                } elseif (is_string($state)) {

                                    $possiblePaths = [
                                        storage_path('app/livewire-tmp/' . $state),
                                        storage_path('app/public/' . $state),
                                        storage_path('app/' . $state),
                                        Storage::disk('public')->path($state),
                                        $state 
                                    ];
                                    
                                    foreach ($possiblePaths as $path) {
                                        if (file_exists($path)) {
                                            $filePath = $path;
                                            break;
                                        }
                                    }
                                } elseif (is_array($state) && !empty($state)) {

                                    $firstFile = $state[0];
                                    if ($firstFile instanceof TemporaryUploadedFile) {
                                        $filePath = $firstFile->getRealPath();
                                    } elseif (is_string($firstFile)) {
                                        $filePath = storage_path('app/livewire-tmp/' . $firstFile);
                                    }
                                }
                                
                                if (!$filePath || !file_exists($filePath)) {
                                    Log::warning('CSV file not found. State type: ' . gettype($state) . ', Value: ' . json_encode($state));
                                    return;
                                }

                                $handle = fopen($filePath, 'r');
                                if ($handle) {
                                    $headers = fgetcsv($handle);
                                    fclose($handle);
                                    
                                    if ($headers) {
                                        $cleanHeaders = array_map(function($header) {
                                            return trim(str_replace(["\xEF\xBB\xBF", "\uFEFF"], '', $header));
                                        }, $headers);
                                        
                                        $set('csv_headers', $cleanHeaders);
                                        Log::info('CSV headers extracted: ', $cleanHeaders);
                                    }
                                }
                            } catch (\Throwable $e) {
                                Log::error('Error reading CSV headers: ' . $e->getMessage());
                                Log::error('Stack trace: ' . $e->getTraceAsString());
                                $set('csv_headers', []);
                            }
                        }),

                    Forms\Components\TagsInput::make('csv_headers')
                        ->label('CSV Headers (Detected)')
                        ->disabled()
                        ->visible(function ($get) {
                            return !empty($get('csv_headers'));
                        }),
                    Forms\Components\TextInput::make('content')
                        ->required(),
                        
                    Forms\Components\Select::make('locale')
                        ->options([
                            'en' => 'English',
                            'fr' => 'French',
                            'zh' => 'Chinese',
                            'es' => 'Spanish',
                        ]),

                    Forms\Components\Textarea::make('json_input')
                        ->label('Enter JSON Format')
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, $set, $get) {
                            if (empty($state)) {
                                $set('field_definitions', []);
                                $set('available_fields', []);
                                return;
                            }
            
                            try {
                                $parsed = json_decode($state, true);
            
                                if (json_last_error() !== JSON_ERROR_NONE) {
                                    Log::error('JSON parsing error: ' . json_last_error_msg());
                                    return;
                                }
            
                                if (!is_array($parsed)) {
                                    return;
                                }

                                $csvHeaders = $get('csv_headers') ?? [];
            
                                $extractFields = function ($data, $parentPath = '') use (&$extractFields, $csvHeaders) {
                                    $fields = [];
                                    
                                    foreach ($data as $key => $value) {
                                        $currentPath = $parentPath ? $parentPath . '.' . $key : $key;
                                        
                                        $fields[] = [
                                            'parent' => $parentPath ?: 'root',
                                            'json_field' => $key,
                                            'path' => $currentPath,
                                            'type' => is_array($value) ? 'array' : gettype($value),
                                            'csv_column' => '', 
                                            'available_csv_headers' => $csvHeaders,
                                        ];
                                        
                                        if (is_array($value) && !empty($value)) {
                                            if (array_keys($value) !== range(0, count($value) - 1)) {
                                                $nestedFields = $extractFields($value, $currentPath);
                                                $fields = array_merge($fields, $nestedFields);
                                            }
                                        }
                                    }
                                    
                                    return $fields;
                                };
            
                                $result = $extractFields($parsed);
            
                                $set('field_definitions', $result);
                                
                                $fieldNames = array_column($result, 'json_field');
                                $set('available_fields', $fieldNames);
                                
                            } catch (\Throwable $e) {
                                Log::error('JSON parsing error: ' . $e->getMessage());
                                $set('field_definitions', []);
                                $set('available_fields', []);
                            }
                        }),
            
                        Forms\Components\Repeater::make('field_definitions')
                        ->label('Field Mappings')
                        ->schema([
                            Forms\Components\TextInput::make('parent')
                                ->disabled()
                                ->label('Parent')
                                ->dehydrated(true),
                            Forms\Components\TextInput::make('json_field')
                                ->disabled()
                                ->label('JSON Field')
                                ->dehydrated(true),
                            Forms\Components\Hidden::make('path')
                                ->dehydrated(true),
                            Forms\Components\Select::make('type')
                                ->options([
                                    'string' => 'String',
                                    'integer' => 'Integer',
                                    'double' => 'Float',
                                    'boolean' => 'Boolean',
                                    'array' => 'Array',
                                    'object' => 'Object',
                                    'NULL' => 'Null',
                                ])
                                ->label('Data Type')
                                ->dehydrated(true),
                            Forms\Components\Select::make('csv_column')
                                ->label('Map to CSV Column')
                                ->options(function ($get, $state) {
                                    $csvHeaders = data_get($get('../../csv_headers'), null, []);
                                    return array_combine($csvHeaders, $csvHeaders);
                                })
                                ->searchable()
                                ->placeholder('Select CSV column')
                                ->helperText('Choose which CSV column should populate this JSON field')
                                ->dehydrated(true),
                        ])
                        ->live()
                        ->visible(function ($get) {
                            return !empty($get('field_definitions'));
                        })
                        ->columns(5)
                        ->itemLabel(function (array $state): ?string {
                            return $state['json_field'] ?? null;
                        })
                        ->dehydrated(true) 
                        ->mutateDehydratedStateUsing(function ($state) {
                            return collect($state)->map(function ($item) {
                                return array_merge([
                                    'parent' => '',
                                    'json_field' => '',
                                    'path' => '',
                                    'type' => 'string',
                                    'csv_column' => '',
                                ], $item);
                            })->toArray();
                        }),
            
                    Forms\Components\TagsInput::make('available_fields')
                        ->label('Available JSON Fields')
                        ->disabled()
                        ->live()
                        ->visible(function ($get) {
                            return !empty($get('    '));
                        }),
                ])
            ->action(function (array $data) {
                $importService = app(CsvImportService::class);
                $importService->import($data);
                
            })
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
            'index' => Pages\ListImportedData::route('/'),
            'create' => Pages\CreateImportedData::route('/create'),
            'edit' => Pages\EditImportedData::route('/{record}/edit'),
        ];
    }
}