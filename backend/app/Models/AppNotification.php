<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppNotification extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'body',
        'link',
        'subject_type',
        'subject_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function notifyUser(
        int $userId,
        string $title,
        ?string $body = null,
        ?string $link = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
    ): self {
        return static::create([
            'user_id' => $userId,
            'title' => $title,
            'body' => $body,
            'link' => $link,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
        ]);
    }

    public function markRead(): void
    {
        if (! $this->read_at) {
            $this->update(['read_at' => now()]);
        }
    }

    /** Clear (mark read + optional delete unread reject noise) for a subject. */
    public static function clearForSubject(int $userId, string $subjectType, int $subjectId): void
    {
        static::query()
            ->where('user_id', $userId)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
