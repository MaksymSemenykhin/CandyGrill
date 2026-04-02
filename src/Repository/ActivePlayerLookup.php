<?php

declare(strict_types=1);

namespace Game\Repository;

/** TZ {@see LoginHandler}: разрешить логин только для `users.status = active`. */
interface ActivePlayerLookup
{
    public function findActiveInternalIdByPublicId(string $publicId): ?int;
}
