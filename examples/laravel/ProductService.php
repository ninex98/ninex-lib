<?php

namespace Ninex\Lib\Examples\Laravel;

use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Validation\Factory as Validator;
use Ninex\Lib\Core\CrudService;
use Ninex\Lib\Core\ServiceException;
use Ninex\Lib\Database\EloquentRepository;

class ProductService extends CrudService
{
    public function __construct(Auth $auth, Validator $validator)
    {
        $actor = $auth->guard()->user();
        if (!$actor) {
            throw new ServiceException('请先登录', 401);
        }
        if (!$actor->tenant_id) {
            throw new ServiceException('缺少租户身份', 403);
        }
        parent::__construct(
            new EloquentRepository(
                Product::class,
                ['id', 'name', 'status'],
                fn ($query) => $query->where('tenant_id', $actor->tenant_id),
                ['tenant_id' => $actor->tenant_id]
            ),
            writable: ['name', 'status'],
            filters: ['status'],
            sorts: ['id', 'name'],
            validate: fn ($operation, $data) => $validator->make($data, [
                'name' => ($operation === 'store' ? 'required' : 'sometimes|required').'|string|max:100',
                'status' => 'sometimes|integer|in:0,1',
            ])->validate(),
            authorize: fn ($operation) => in_array($operation, ['index', 'show'], true) || $actor->role === 'editor',
        );
    }
}
