<?php

declare(strict_types=1);

namespace Game\Repository;

/** {@see LoginHandler}: allow login only when `users.status = active`. */
interface ActivePlayerLookup
{
    public function findActiveInternalIdByPublicId(string $publicId): ?int;
}
