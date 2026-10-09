<?php

namespace App\Support\Authorization;

/**
 * What a protected record belongs to: departments, halls, courses and the
 * users who own it. The Authorizer intersects this with the scopes of the
 * roles that grant a permission.
 */
final class ResourceScope
{
    /**
     * @param  list<int>  $departmentIds
     * @param  list<int>  $hallIds
     * @param  list<int>  $courseIds
     * @param  list<int>  $ownerUserIds
     */
    public function __construct(
        public readonly array $departmentIds = [],
        public readonly array $hallIds = [],
        public readonly array $courseIds = [],
        public readonly array $ownerUserIds = [],
    ) {}

    public static function none(): self
    {
        return new self;
    }

    public static function forDepartment(?int $departmentId): self
    {
        return new self(departmentIds: self::ids($departmentId));
    }

    public static function forHall(?int $hallId): self
    {
        return new self(hallIds: self::ids($hallId));
    }

    public static function forCourse(?int $courseId, ?int $departmentId = null): self
    {
        return new self(departmentIds: self::ids($departmentId), courseIds: self::ids($courseId));
    }

    public static function forOwner(?int $userId): self
    {
        return new self(ownerUserIds: self::ids($userId));
    }

    public function merge(self $other): self
    {
        return new self(
            array_values(array_unique([...$this->departmentIds, ...$other->departmentIds])),
            array_values(array_unique([...$this->hallIds, ...$other->hallIds])),
            array_values(array_unique([...$this->courseIds, ...$other->courseIds])),
            array_values(array_unique([...$this->ownerUserIds, ...$other->ownerUserIds])),
        );
    }

    /**
     * @return list<int>
     */
    private static function ids(?int $id): array
    {
        return $id === null ? [] : [$id];
    }
}
