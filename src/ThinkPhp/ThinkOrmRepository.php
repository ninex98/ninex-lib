<?php

namespace Ninex\Lib\ThinkPhp;

use Closure;
use Ninex\Lib\Core\CrudRepository;
use Ninex\Lib\Core\Page;
use Ninex\Lib\Core\Query;
use Ninex\Lib\Core\ServiceException;
use think\DbManager;

/** ThinkORM query-builder adapter. Does not invoke model events or mutators. */
final class ThinkOrmRepository implements CrudRepository
{
    public function __construct(private DbManager $db, private string $table, private array $readable, private string $primaryKey = 'id', private ?Closure $scope = null, private array $createDefaults = [], private ?string $connection = null)
    {
        foreach ([$table, $primaryKey] as $name) {
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name)) {
                throw new \InvalidArgumentException('Invalid table or primary key.');
            }
        }
    }

    private function query()
    {
        $query = $this->db->connect($this->connection)->table($this->table)->pk($this->primaryKey);
        if ($this->scope) {
            ($this->scope)($query);
        }
        return $query;
    }

    private function present(array $record): array
    {
        return array_intersect_key($record, array_flip($this->readable));
    }

    public function paginate(Query $criteria): Page
    {
        $query = $this->query();
        foreach ($criteria->filters as $column => $value) {
            if ($value === null) {
                $query->whereNull($column);
            } else {
                $query->where($column, '=', $value);
            }
        }
        $total = (clone $query)->count();
        foreach ($criteria->sorts as $column => $direction) {
            $query->order($column, $direction);
        }
        $rows = $query->page($criteria->page, $criteria->pageSize)->select()->toArray();
        return new Page(array_map($this->present(...), $rows), $total, $criteria->pageSize, $criteria->page);
    }

    public function find(string|int $id): ?array
    {
        $record = $this->query()->where($this->primaryKey, '=', $id)->find();
        return $record ? $this->present($record) : null;
    }

    public function create(array $data): array
    {
        $id = $this->query()->insertGetId(array_merge($data, $this->createDefaults));
        return $this->find($id) ?? throw new ServiceException('创建记录不在允许的数据范围内', 403);
    }

    public function update(string|int $id, array $data): array
    {
        if ($this->find($id) === null) {
            throw new ServiceException('数据不存在', 404);
        }
        if ($data) {
            $this->query()->where($this->primaryKey, '=', $id)->update($data);
        }
        return $this->find($id) ?? throw new ServiceException('数据不存在', 404);
    }

    public function delete(string|int $id): void
    {
        if (!$this->query()->where($this->primaryKey, '=', $id)->delete()) {
            throw new ServiceException('数据不存在', 404);
        }
    }

    public function transaction(callable $callback): mixed
    {
        return $this->db->connect($this->connection)->transaction($callback);
    }
}
