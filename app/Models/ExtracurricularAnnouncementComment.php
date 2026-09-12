<?php

namespace App\Models;

use Database\Factories\ExtracurricularAnnouncementCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['extracurricular_announcement_id', 'user_id', 'body'])]
class ExtracurricularAnnouncementComment extends Model
{
    /** @use HasFactory<ExtracurricularAnnouncementCommentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $comment): void {
            $comment->user_id ??= auth()->id();
        });
    }

    /**
     * Get the announcement this comment was left on.
     *
     * @return BelongsTo<ExtracurricularAnnouncement, $this>
     */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(ExtracurricularAnnouncement::class, 'extracurricular_announcement_id');
    }

    /**
     * Get the user who left this comment.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
