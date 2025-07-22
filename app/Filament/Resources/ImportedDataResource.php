<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\ImportedData;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\ImportedDataResource\Pages;
use App\Filament\Resources\ImportedDataResource\RelationManagers;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImportedDataResource extends Resource
{
    protected static ?string $model = ImportedData::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static ?string $navigationLabel = 'Data import converter';

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
                //
            ])
            ->headerActions([
                Action::make('import_data')
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
                                
                                // Handle TemporaryUploadedFile objects from Livewire
                                if ($state instanceof TemporaryUploadedFile) {
                                    $filePath = $state->getRealPath();
                                } elseif (is_string($state)) {
                                    // Try different possible paths for Filament uploads
                                    $possiblePaths = [
                                        storage_path('app/livewire-tmp/' . $state),
                                        storage_path('app/public/' . $state),
                                        storage_path('app/' . $state),
                                        Storage::disk('public')->path($state),
                                        $state // In case it's already a full path
                                    ];
                                    
                                    foreach ($possiblePaths as $path) {
                                        if (file_exists($path)) {
                                            $filePath = $path;
                                            break;
                                        }
                                    }
                                } elseif (is_array($state) && !empty($state)) {
                                    // Sometimes Filament returns an array
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

                                // Read CSV headers
                                $handle = fopen($filePath, 'r');
                                if ($handle) {
                                    $headers = fgetcsv($handle);
                                    fclose($handle);
                                    
                                    if ($headers) {
                                        // Clean up headers (remove BOM, trim whitespace)
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

                    // Display CSV headers for reference
                    Forms\Components\TagsInput::make('csv_headers')
                        ->label('CSV Headers (Detected)')
                        ->disabled()
                        ->visible(function ($get) {
                            return !empty($get('csv_headers'));
                        }),
            
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
            
                                // Extract all field names recursively
                                $extractFields = function ($data, $parentPath = '') use (&$extractFields, $csvHeaders) {
                                    $fields = [];
                                    
                                    foreach ($data as $key => $value) {
                                        $currentPath = $parentPath ? $parentPath . '.' . $key : $key;
                                        
                                        // Add the current field with CSV header options
                                        $fields[] = [
                                            'parent' => $parentPath ?: 'root',
                                            'json_field' => $key,
                                            'path' => $currentPath,
                                            'type' => is_array($value) ? 'array' : gettype($value),
                                            'csv_column' => '', // This will be populated by user selection
                                            'available_csv_headers' => $csvHeaders,
                                        ];
                                        
                                        // If it's an array/object, recursively extract its fields
                                        if (is_array($value) && !empty($value)) {
                                            // Check if it's an associative array (object-like)
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
                                
                                // Also set a simple list of all field names for easy access
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
                                ->label('Parent'),
                            Forms\Components\TextInput::make('json_field')
                                ->disabled()
                                ->label('JSON Field'),
                            Forms\Components\TextInput::make('path')
                                ->disabled()
                                ->label('Full Path'),
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
                                ->label('Data Type'),
                            Forms\Components\Select::make('csv_column')
                                ->label('Map to CSV Column')
                                ->options(function ($get, $state) {
                                    // Get CSV headers from the parent form state
                                    $csvHeaders = data_get($get('../../csv_headers'), null, []);
                                    return array_combine($csvHeaders, $csvHeaders);
                                })
                                ->searchable()
                                ->placeholder('Select CSV column')
                                ->helperText('Choose which CSV column should populate this JSON field'),
                        ])
                        ->live()
                        ->visible(function ($get) {
                            return !empty($get('field_definitions'));
                        })
                        ->columns(5)
                        ->itemLabel(function (array $state): ?string {
                            return $state['json_field'] ?? null;
                        }),
            
                    // Optional: Display extracted field names as a simple list
                    Forms\Components\TagsInput::make('available_fields')
                        ->label('Available JSON Fields')
                        ->disabled()
                        ->live()
                        ->visible(function ($get) {
                            return !empty($get('available_fields'));
                        }),
                ])
                ->action(function (array $data) {
                    Log::info('Import data received:', [
                        'uploaded_file_type' => gettype($data['uploaded_file']),
                        'uploaded_file_value' => is_object($data['uploaded_file']) ? get_class($data['uploaded_file']) : $data['uploaded_file'],
                        'field_definitions_count' => count($data['field_definitions'] ?? [])
                    ]);
                    
                    try {
                        // Find the correct file path
                        $filePath = null;
                        $uploadedFile = $data['uploaded_file'];
                        
                        if ($uploadedFile instanceof TemporaryUploadedFile) {
                            $filePath = $uploadedFile->getRealPath();
                        } elseif (is_string($uploadedFile)) {
                            $possiblePaths = [
                                storage_path('app/livewire-tmp/' . $uploadedFile),
                                storage_path('app/public/' . $uploadedFile),
                                storage_path('app/' . $uploadedFile),
                                Storage::disk('public')->path($uploadedFile),
                                $uploadedFile
                            ];
                            
                            foreach ($possiblePaths as $path) {
                                if (file_exists($path)) {
                                    $filePath = $path;
                                    break;
                                }
                            }
                        } elseif (is_array($uploadedFile) && !empty($uploadedFile)) {
                            $firstFile = $uploadedFile[0];
                            if ($firstFile instanceof TemporaryUploadedFile) {
                                $filePath = $firstFile->getRealPath();
                            } elseif (is_string($firstFile)) {
                                $filePath = storage_path('app/livewire-tmp/' . $firstFile);
                            }
                        }
                        
                        if (!$filePath || !file_exists($filePath)) {
                            throw new \Exception('CSV file not found for import. File type: ' . gettype($uploadedFile));
                        }
                        
                        $mappings = collect($data['field_definitions'] ?? []);
                        
                        if ($mappings->isEmpty()) {
                            throw new \Exception('No field mappings defined. Please map CSV columns to JSON fields.');
                        }
                        
                        // Read CSV data
                        $handle = fopen($filePath, 'r');
                        if (!$handle) {
                            throw new \Exception('Could not open CSV file for reading.');
                        }
                        
                        $headers = fgetcsv($handle);
                        if (!$headers) {
                            fclose($handle);
                            throw new \Exception('Could not read CSV headers.');
                        }
                        
                        // Clean headers
                        $headers = array_map(function($header) {
                            return trim(str_replace(["\xEF\xBB\xBF", "\uFEFF"], '', $header));
                        }, $headers);
                        
                        $importCount = 0;
                        $errors = [];
                        
                        while (($row = fgetcsv($handle)) !== false) {
                            try {
                                if (count($row) !== count($headers)) {
                                    $errors[] = "Row " . ($importCount + 2) . ": Column count mismatch";
                                    continue;
                                }
                                
                                $csvData = array_combine($headers, $row);
                                
                                // Build the data array based on mappings
                                $importData = [];
                                foreach ($mappings as $mapping) {
                                    if (!empty($mapping['csv_column']) && isset($csvData[$mapping['csv_column']])) {
                                        // Handle nested JSON paths
                                        $path = explode('.', $mapping['path']);
                                        $current = &$importData;
                                        
                                        // Navigate to the correct nested location
                                        for ($i = 0; $i < count($path) - 1; $i++) {
                                            if (!isset($current[$path[$i]])) {
                                                $current[$path[$i]] = [];
                                            }
                                            $current = &$current[$path[$i]];
                                        }
                                        
                                        // Set the value at the final location
                                        $finalKey = end($path);
                                        $value = $csvData[$mapping['csv_column']];
                                        
                                        // Type casting based on mapping type
                                        switch ($mapping['type']) {
                                            case 'integer':
                                                $value = (int) $value;
                                                break;
                                            case 'double':
                                                $value = (float) $value;
                                                break;
                                            case 'boolean':
                                                $value = in_array(strtolower($value), ['true', '1', 'yes', 'on']);
                                                break;
                                            case 'array':
                                                $value = json_decode($value, true) ?? explode(',', $value);
                                                break;
                                        }
                                        
                                        $current[$finalKey] = $value;
                                    }
                                }

                                dd($importData);
                                
                                // Create ImportedData record
                                ImportedData::create([
                                    'data' => json_encode($importData),
                                    // Map other fields - make these configurable if needed
                                    'content' => $csvData['content'] ?? null,
                                    'title' => $csvData['title'] ?? null,
                                    'route_url' => $csvData['route_url'] ?? null,
                                    'status' => isset($csvData['status']) ? 
                                        in_array(strtolower($csvData['status']), ['true', '1', 'yes', 'on', 'active']) : true,
                                    'sites' => $csvData['sites'] ?? null,
                                    'locale' => $csvData['locale'] ?? 'en',
                                    'taxonomy_terms' => $csvData['taxonomy_terms'] ?? null,
                                    'published_at' => isset($csvData['published_at']) ? 
                                        \Carbon\Carbon::parse($csvData['published_at']) : now(),
                                ]);
                                
                                $importCount++;
                            } catch (\Exception $e) {
                                $errors[] = "Row " . ($importCount + 2) . ": " . $e->getMessage();
                            }
                        }
                        
                        fclose($handle);
                        
                        // Show results
                        if ($importCount > 0) {
                            $message = "Successfully imported {$importCount} records.";
                            if (!empty($errors)) {
                                $message .= " " . count($errors) . " rows had errors.";
                            }
                            
                            Notification::make()
                                ->title('Import Completed')
                                ->body($message)
                                ->success()
                                ->send();
                                
                            if (!empty($errors)) {
                                Log::warning('Import errors:', $errors);
                            }
                        } else {
                            throw new \Exception('No records were imported. Errors: ' . implode(', ', $errors));
                        }
                            
                    } catch (\Throwable $e) {
                        Log::error('Import failed: ' . $e->getMessage());
                        Log::error('Stack trace: ' . $e->getTraceAsString());
                        
                        Notification::make()
                            ->title('Import Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
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