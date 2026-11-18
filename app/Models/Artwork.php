<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['participant_name', 'title', 'description', 'image_path'])]
class Artwork extends Model
{
    use HasFactory;

    /**
     * All likes associated with this artwork.
     */
    public function likes(): HasMany
    {
        return $this->hasMany(ArtworkLike::class);
    }

    /**
     * Include aggregated likes count and whether a visitor hash already liked the artwork.
     */
    public function scopeWithVisitorLikeStatus(Builder $query, ?string $visitorTokenHash): Builder
    {
        if (! $visitorTokenHash) {
            return $query->withCount('likes');
        }

        return $query->withCount([
            'likes',
            'likes as is_liked_by_visitor' => function (Builder $likeQuery) use ($visitorTokenHash): void {
                $likeQuery->where('visitor_token_hash', $visitorTokenHash);
            },
        ]);
    }

    /**
     * Order artworks by highest like count, breaking ties with newest uploads first.
     */
    public function scopeOrderedByPopularity(Builder $query): Builder
    {
        return $query
            ->orderByDesc('likes_count')
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}
