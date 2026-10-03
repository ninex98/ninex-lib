<?php

namespace Ninex\Lib\Core;

/** Implementations must apply the same server-side scope to reads and writes. */
interface CrudRepository
{
    public function paginate(Query $query): Page;
    public function find(string|int $id): ?array;
    public function create(array $data): array;
    public function update(string|int $id, array $data): array;
    public function delete(string|int $id): void;
    /** Run against the same connection used by the repository. */
    public function transaction(callable $callback): mixed;
}
