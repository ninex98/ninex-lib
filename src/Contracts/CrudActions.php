<?php

namespace Ninex\Lib\Contracts;

use Ninex\Lib\Core\Page;

/** 自行执行验证与授权的 CRUD 服务。 / CRUD services enforce their own validation and authorization. */
interface CrudActions
{
    /** 分页结果。 / Return a page. */
    public function paginate(array $input = []): Page;

    /** 单条可见记录。 / Return a visible record. */
    public function show(string|int $id): array;

    /** 创建并返回记录。 / Create and return a record. */
    public function store(array $input): array;

    /** 更新并返回记录。 / Update and return a record. */
    public function update(string|int $id, array $input): array;

    /** 删除记录。 / Delete a record. */
    public function destroy(string|int $id): void;
}
