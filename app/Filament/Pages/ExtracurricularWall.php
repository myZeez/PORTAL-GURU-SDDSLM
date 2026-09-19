<?php

namespace App\Filament\Pages;

use App\Models\ExtracurricularAnnouncement;
use App\Models\ExtracurricularAnnouncementComment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

/**
 * The extracurricular announcement wall: a shared feed anyone can read and comment on,
 * with posting reserved for administrators, the ekskul coordinator, or an
 * announcement's own author (for editing their own comments' visibility rules).
 *
 * @property-read Schema $form
 */
class ExtracurricularWall extends Page
{
    protected string $view = 'filament.pages.extracurricular-wall';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Papan Ekstrakurikuler';

    protected static ?string $title = 'Papan Ekstrakurikuler';

    protected static ?int $navigationSort = 10;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * @var array<int, string>
     */
    public array $commentBodies = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function canPost(): bool
    {
        return auth()->user()->can('create', ExtracurricularAnnouncement::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    TextInput::make('title')
                        ->label('Judul')
                        ->required(),
                    Textarea::make('body')
                        ->label('Isi Pengumuman')
                        ->required()
                        ->rows(3),
                    TextInput::make('image_drive_url')
                        ->label('Link Gambar (Google Drive)')
                        ->url()
                        ->rule('url:http,https')
                        ->maxLength(255),
                ])
                    ->livewireSubmitHandler('postAnnouncement')
                    ->footer([
                        SchemaActions::make([
                            Action::make('post')
                                ->label('Posting')
                                ->submit('postAnnouncement'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function postAnnouncement(): void
    {
        abort_unless($this->canPost(), 403);

        ExtracurricularAnnouncement::create($this->form->getState());

        $this->form->fill();

        Notification::make()->success()->title('Pengumuman diposting.')->send();
    }

    /**
     * Get every announcement, newest first, with its comment thread.
     *
     * @return Collection<int, ExtracurricularAnnouncement>
     */
    public function getAnnouncements(): Collection
    {
        return ExtracurricularAnnouncement::query()
            ->with(['creator', 'comments.user'])
            ->latest()
            ->get();
    }

    public function postComment(int $announcementId): void
    {
        abort_unless(auth()->user()->can('create', ExtracurricularAnnouncementComment::class), 403);

        $body = trim($this->commentBodies[$announcementId] ?? '');

        if ($body === '') {
            return;
        }

        ExtracurricularAnnouncementComment::create([
            'extracurricular_announcement_id' => $announcementId,
            'body' => $body,
        ]);

        unset($this->commentBodies[$announcementId]);
    }

    public function deleteAnnouncement(int $id): void
    {
        $announcement = ExtracurricularAnnouncement::findOrFail($id);

        abort_unless(auth()->user()->can('delete', $announcement), 403);

        $announcement->delete();
    }

    public function deleteComment(int $id): void
    {
        $comment = ExtracurricularAnnouncementComment::findOrFail($id);

        abort_unless(auth()->user()->can('delete', $comment), 403);

        $comment->delete();
    }

    public function canDeleteAnnouncement(ExtracurricularAnnouncement $announcement): bool
    {
        return auth()->user()->can('delete', $announcement);
    }

    public function canDeleteComment(ExtracurricularAnnouncementComment $comment): bool
    {
        return auth()->user()->can('delete', $comment);
    }
}
