<?php

namespace Ninex\Lib\Core;

final class Query
{
    private function __construct(public readonly array $filters, public readonly array $sorts, public readonly int $page, public readonly int $pageSize)
    {
    }

    public static function fromArray(array $input, array $allowedFilters, array $allowedSorts, int $maxPageSize = 100): self
    {
        if ($maxPageSize < 1) {
            throw new \InvalidArgumentException('maxPageSize must be positive.');
        }
        foreach (array_merge($allowedFilters, $allowedSorts) as $field) {
            if (!is_string($field) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $field)) {
                throw new \InvalidArgumentException('Only simple column names are supported by the portable query.');
            }
        }
        if (array_diff(array_keys($input), ['filter', 'sort', 'page', 'page_size'])) {
            throw new ServiceException('不支持的查询参数', 422);
        }
        $filters = $input['filter'] ?? [];
        if (!is_array($filters) || array_diff(array_keys($filters), $allowedFilters)) {
            throw new ServiceException('不允许的筛选字段', 422);
        }
        foreach ($filters as $value) {
            if (!is_scalar($value) && $value !== null) {
                throw new ServiceException('筛选值必须为标量或 null', 422);
            }
        }
        $sort = $input['sort'] ?? (isset($allowedSorts[0]) ? '-'.$allowedSorts[0] : '');
        if (!is_string($sort)) {
            throw new ServiceException('排序参数格式错误', 422);
        }
        $sorts = [];
        foreach ($sort === '' ? [] : explode(',', $sort) as $field) {
            $descending = str_starts_with($field, '-');
            $column = $descending ? substr($field, 1) : $field;
            if (!in_array($column, $allowedSorts, true)) {
                throw new ServiceException('不允许的排序字段', 422);
            }
            $sorts[$column] = $descending ? 'desc' : 'asc';
        }
        $page = self::positiveInteger($input['page'] ?? 1);
        $pageSize = self::positiveInteger($input['page_size'] ?? min(15, $maxPageSize));
        if ($pageSize > $maxPageSize || $page > intdiv(PHP_INT_MAX, $pageSize)) {
            throw new ServiceException('分页参数超出范围', 422);
        }
        return new self($filters, $sorts, $page, $pageSize);
    }

    private static function positiveInteger(mixed $value): int
    {
        if ((!is_int($value) && !is_string($value)) || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new ServiceException('分页参数必须为正整数', 422);
        }
        return (int) $value;
    }
}
