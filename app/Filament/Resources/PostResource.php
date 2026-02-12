<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\Post;
use App\Models\FacebookImages;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Http;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\PostResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Notifications\Actions\Action as UrlAction;

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Facebook Posts';

    protected static ?string $modelLabel = 'Facebook Post';

    protected static ?string $pluralModelLabel = 'Facebook Posts';

    protected static ?string $navigationGroup = 'Social Media';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Post Content')
                    ->description('Create your Facebook post content')
                    ->schema([
                        Textarea::make('title')
                            ->label('Post Message')
                            
                            ->required()
                            ->rows(5)
                            ->maxLength(5000)
                            ->placeholder('Write your Facebook post message here...')
                            ->helperText('This will be the main text content of your Facebook post.')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Media & Link')
                    ->description('Add images and external links')
                    ->schema([
                        Repeater::make('images')
                            ->relationship()
                            ->schema([
                                FileUpload::make('image_path')
                                    ->label('Image')
                                    ->image()
                                    ->required()
                                    ->imageEditor()
                                    ->imageEditorAspectRatios([
                                        '16:9',
                                        '4:3',
                                        '1:1',
                                    ])
                                    ->disk('public')
                                    ->directory('post-images')
                                    ->visibility('public')
                                    ->columnSpanFull(),
                            ])
                            ->reorderable('order')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['image_path'] ? 'Image' : null)
                            ->addActionLabel('Add Image')
                            ->defaultItems(0)
                            ->maxItems(10)
                            ->columnSpanFull()
                            ->helperText('Upload up to 10 images. Drag to reorder.'),

                        TextInput::make('link')
                            ->label('External Link')
                            ->url()
                            ->prefix('https://')
                            ->placeholder('example.com/article')
                            ->helperText('Add a link to share with your post.')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Post Message')
                    ->searchable()
                    ->limit(50)
                    ->wrap()
                    ->tooltip(function ($record) {
                        return $record->title;
                    }),

                Tables\Columns\ImageColumn::make('images.image_path')
                    ->label('Images')
                    ->circular()
                    ->stacked()
                    ->limit(3)
                    ->defaultImageUrl(url('/images/placeholder.png')),

                Tables\Columns\TextColumn::make('images_count')
                    ->counts('images')
                    ->label('Images')
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('link')
                    ->label('Link')
                    ->searchable()
                    ->limit(30)
                    ->url(fn ($record) => $record->link, shouldOpenInNewTab: true)
                    ->icon('heroicon-m-link')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Action::make('post_to_facebook')
                    ->label('Post to Facebook')
                    ->color('primary')
                    ->icon('heroicon-o-share')
                    ->requiresConfirmation()
                    ->modalHeading('Post to Facebook')
                    ->modalDescription('Are you sure you want to publish this post to Facebook?')
                    ->modalSubmitActionLabel('Yes, Post Now')
                    ->action(function ($record) {
                        try {
                            // Get all image URLs
                            $imageUrls = $record->images->map(function ($image) {
                                return asset('/storage/' . $image->image_path);
                            })->toArray();

                            $response = Http::post('https://maykel.app.n8n.cloud/webhook-test/facebook-post', [
                                'page_id' => '124476754867858',
                                'message' => $record->title,
                                'link' => $record->link,
                                'images' => $imageUrls,
                            ]);

                            $result = $response->json();
                            $facebookUrl = $result['facebook_url'] ?? null;

                            if ($response->successful() && ($result['success'] ?? false)) {
                                Notification::make()
                                    ->title('Success! Post Published')
                                    ->body('Your content has been successfully posted to Facebook.')
                                    ->actions([
                                        UrlAction::make('view_post')
                                            ->label('View Post')
                                            ->url($facebookUrl)
                                            ->openUrlInNewTab()
                                    ])
                                    ->success()
                                    ->duration(10000)
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Failed to Post')
                                    ->body($result['message'] ?? 'Unknown error occurred'
                                    )
                                    ->danger()
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Error Posting to Facebook')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No Facebook posts yet')
            ->emptyStateDescription('Create your first Facebook post to get started.')
            ->emptyStateIcon('heroicon-o-document-text');
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
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
