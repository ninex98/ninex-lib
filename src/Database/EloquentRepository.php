<?php

namespace Ninex\Lib\Database;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Ninex\Lib\Core\CrudRepository;
use Ninex\Lib\Core\Page;
use Ninex\Lib\Core\Query;
use Ninex\Lib\Core\ServiceException;

final class EloquentRepository implements CrudRepository
{
    /** @param class-string<Model> $modelClass */
    public function __construct(private string $modelClass, private array $readable, private ?Closure $scope = null, private array $createDefaults = [])
    {
        if (!is_subclass_of($modelClass, Model::class)) {
            throw new \InvalidArgumentException('Expected an Eloquent model class.');
        }
    }

    private function query(): Builder
    {
        $query = (new $this->modelClass())->newQuery();
        if ($this->scope) {
            ($this->scope)($query);
        }
        return $query;
    }

    private function present(Model $model): array
    {
        return array_intersect_key($model->toArray(), array_flip($this->readable));
    }

    public function paginate(Query $criteria): Page
    {
        $query = $this->query();
        foreach ($criteria->filters as $column => $value) {
            $query->where($column, $value);
        }
        foreach ($criteria->sorts as $column => $direction) {
            $query->orderBy($column, $direction);
        }
        $page = $query->paginate($criteria->pageSize, ['*'], 'page', $criteria->page);
        return new Page(array_map($this->present(...), $page->items()), $page->total(), $page->perPage(), $page->currentPage());
    }

    public function find(string|int $id): ?array
    {
        $model = $this->query()->find($id);
        return $model ? $this->present($model) : null;
    }

    public function create(array $data): array
    {
        // Data has passed the core whitelist. Trusted server defaults may include tenant ownership.
        $model = new $this->modelClass();
        $model->forceFill(array_merge($data, $this->createDefaults));
        if (!$model->save()) {
            throw new ServiceException('创建失败', 422);
        }
        return $this->find($model->getKey()) ?? throw new ServiceException('创建记录不在允许的数据范围内', 403);
    }

    public function update(string|int $id, array $data): array
    {
        $model = $this->query()->find($id) ?? throw new ServiceException('数据不存在', 404);
        if (!$model->forceFill($data)->save()) {
            throw new ServiceException('更新失败', 422);
        }
        return $this->find($id) ?? throw new ServiceException('数据不存在', 404);
    }

    public function delete(string|int $id): void
    {
        $model = $this->query()->find($id) ?? throw new ServiceException('数据不存在', 404);
        if (!$model->delete()) {
            throw new ServiceException('删除失败', 422);
        }
    }

    public function transaction(callable $callback): mixed
    {
        return (new $this->modelClass())->getConnection()->transaction($callback);
    }
}
