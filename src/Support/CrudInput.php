<?php

namespace Ninex\Lib\Support;

use Ninex\Lib\Core\Query;
use Ninex\Lib\Core\ServiceException;

/** 从规则推导默认字段；特殊限制才需要覆盖配置。 / Infer defaults from validation rules; overrides are optional. */
trait CrudInput
{
    protected ?array $inputFields = null;
    protected ?array $writable = null;
    protected ?array $readable = null;
    protected ?array $filters = null;
    protected ?array $sorts = null;
    protected string $defaultSort = '-id';
    protected int $maxPageSize = 100;
    protected ?string $ownerColumn = 'owner_id';

    /** 查询参数不直接用作列名；scopeQuery 校验剩余的默认条件。 / Custom filters are consumed before default column checks. */
    protected function queryCriteria(array $input): Query
    {
        $requested = $input['filter'] ?? [];
        $allowedFilters = $this->filters ?? (is_array($requested) ? array_keys($requested) : []);
        foreach ($allowedFilters as $field) {
            if (!is_string($field) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $field)) {
                throw new ServiceException('不允许的筛选字段', 422);
            }
        }
        $sorts = $this->sorts ?? array_values(array_unique([$this->primaryKey(), ...$this->defaultFields()]));
        return Query::fromArray($input + ['sort' => $this->defaultSort], $allowedFilters, $sorts, $this->maxPageSize);
    }

    /** 规则使用顶层表单字段名，兼容嵌套验证规则。 / Extract top-level fields from nested validation rules. */
    protected function ruleFields(array $rules): array
    {
        return array_values(array_unique(array_map(fn ($field) => explode('.', $field)[0], array_keys($rules))));
    }

    /** 只推导真实数据库字段，排除表单别名和归属字段。 / Infer real columns, excluding form aliases and ownership. */
    protected function defaultFields(): array
    {
        return array_values(array_diff(array_intersect($this->fieldNames(), $this->databaseFields()), array_filter([$this->ownerColumn])));
    }

    /** 输入字段默认来自本次验证规则，无需再列一份名单。 / Infer accepted form fields from this validation pass. */
    protected function assertInputFields(array $input, array $rules): void
    {
        $allowed = $this->inputFields ?? $this->ruleFields($rules);
        $allowed = array_diff($allowed, array_filter([$this->primaryKey(), $this->ownerColumn]));
        $this->assertFields($input, $allowed, '存在不允许写入的字段');
    }

    /** 默认使用规则字段过滤；自定义别名应先在 scopeQuery 中处理。 / Only default filters become direct column conditions. */
    protected function assertFilterFields(array $filters): void
    {
        $this->assertFields($filters, $this->filters ?? $this->defaultFields(), '不允许的筛选字段');
    }

    /** saving 已是可信业务转换；可选 writable 额外限制，归属和主键始终受保护。 / Optional persistence limits after trusted transformation. */
    protected function writeData(array $data): array
    {
        if ($this->writable !== null) {
            $this->assertFields($data, $this->writable, '存在不允许保存的字段');
        }
        $protected = array_filter([$this->primaryKey(), $this->ownerColumn]);
        if (array_intersect(array_keys($data), $protected)) {
            throw new ServiceException('不允许修改主键或归属字段', 422);
        }
        return $data;
    }

    /** 默认输出主键及规则字段，特殊输出再配置 readable 或 Resource。 / Output the key and declared fields by default. */
    protected function outputData(array $record): array
    {
        return array_intersect_key($record, array_flip($this->readable ?? [$this->primaryKey(), ...$this->defaultFields()]));
    }

    private function assertFields(array $data, array $allowed, string $message): void
    {
        $unknown = array_diff(array_keys($data), $allowed);
        if ($unknown) {
            throw new ServiceException($message, 422, ['fields' => array_values($unknown)]);
        }
    }

    /** 所有权模板采用正整数身份。 / The ownership schema requires a positive integer identity. */
    protected function integerIdentity(mixed $id): int
    {
        if ((!is_int($id) && !is_string($id)) || !ctype_digit((string) $id) || filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new \LogicException('owner_id requires a positive integer identity; adapt the schema and service for other identity types.');
        }
        return (int) $id;
    }
}
