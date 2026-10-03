<?php

namespace Ninex\Lib\Examples\ThinkPhp;

use Ninex\Lib\Core\CrudService;
use Ninex\Lib\Core\ServiceException;
use Ninex\Lib\ThinkPhp\ThinkOrmRepository;
use think\DbManager;
use think\Request;
use think\Validate;

class ProductService extends CrudService
{
    public function __construct(DbManager $db, Request $request)
    {
        // Authentication middleware must set this from a trusted session/token, never request parameters.
        $actor = $request->middleware('actor');
        if (!is_array($actor) || !isset($actor['id'], $actor['tenant_id'])) {
            throw new ServiceException('请先登录', 401);
        }
        parent::__construct(
            new ThinkOrmRepository(
                $db,
                'products',
                ['id', 'name', 'status'],
                'id',
                fn ($query) => $query->where('tenant_id', $actor['tenant_id']),
                ['tenant_id' => $actor['tenant_id']]
            ),
            writable: ['name', 'status'],
            filters: ['status'],
            sorts: ['id', 'name'],
            validate: function ($operation, $data) {
                $rules = ['status' => 'integer|in:0,1'];
                if ($operation === 'store' || array_key_exists('name', $data)) {
                    $rules['name'] = 'require|string|max:100';
                }
                $validator = new Validate();
                if (!$validator->rule($rules)->batch(true)->check($data)) {
                    throw new ServiceException('数据验证失败', 422, ['errors' => $validator->getError()]);
                }
                return $data;
            },
            authorize: fn ($operation) => in_array($operation, ['index', 'show'], true) || ($actor['role'] ?? null) === 'editor',
        );
    }
}
