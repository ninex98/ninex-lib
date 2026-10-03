<?php

namespace Ninex\Lib\Core;

use Closure;

/** Validation and authorization are required application decisions, with no global framework state. */
class CrudService
{
    public function __construct(
        protected CrudRepository $repository,
        protected array $writable,
        protected array $filters,
        protected array $sorts,
        protected Closure $validate,
        protected Closure $authorize,
        protected int $maxPageSize = 100,
    ) {
    }

    public function paginate(array $input = []): Page
    {
        $this->checkAccess('index', null);
        return $this->repository->paginate(Query::fromArray($input, $this->filters, $this->sorts, $this->maxPageSize));
    }

    public function show(string|int $id): array
    {
        $record = $this->record($id);
        $this->checkAccess('show', $record);
        return $record;
    }

    public function store(array $input): array
    {
        return $this->repository->transaction(function () use ($input) {
            $this->checkAccess('store', null);
            return $this->repository->create($this->validated('store', $input, null));
        });
    }

    public function update(string|int $id, array $input): array
    {
        return $this->repository->transaction(function () use ($id, $input) {
            $this->checkAccess('update', $this->record($id));
            return $this->repository->update($id, $this->validated('update', $input, $id));
        });
    }

    public function destroy(string|int $id): void
    {
        $this->repository->transaction(function () use ($id) {
            $this->checkAccess('destroy', $this->record($id));
            $this->repository->delete($id);
        });
    }

    private function record(string|int $id): array
    {
        return $this->repository->find($id) ?? throw new ServiceException('数据不存在', 404);
    }

    private function checkAccess(string $operation, ?array $record): void
    {
        if (($this->authorize)($operation, $record) !== true) {
            throw new ServiceException('无权限访问', 403);
        }
    }

    private function validated(string $operation, array $input, string|int|null $id): array
    {
        $unknown = array_diff(array_keys($input), $this->writable);
        if ($unknown) {
            throw new ServiceException('存在不允许写入的字段', 422, ['fields' => array_values($unknown)]);
        }
        $data = ($this->validate)($operation, $input, $id);
        if (!is_array($data)) {
            throw new \LogicException('The validator must return an array of validated fields.');
        }
        return array_intersect_key($data, array_flip($this->writable));
    }
}
