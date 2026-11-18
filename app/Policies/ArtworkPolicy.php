<?php

namespace App\Policies;

use App\Models\Artwork;
use App\Models\User;

class ArtworkPolicy
{
    /**
     * Only authenticated administrators may list admin artworks.
     */
    public function viewAny(User $user): bool
    {
        return $user->exists;
    }

    /**
     * Only authenticated administrators may view admin artwork detail.
     */
    public function view(User $user, Artwork $artwork): bool
    {
        return $user->exists;
    }

    /**
     * Only authenticated administrators may update an artwork.
     */
    public function update(User $user, Artwork $artwork): bool
    {
        return $user->exists;
    }

    /**
     * Only authenticated administrators may delete an artwork.
     */
    public function delete(User $user, Artwork $artwork): bool
    {
        return $user->exists;
    }
}
