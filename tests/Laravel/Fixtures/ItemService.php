<?php

namespace Ninex\Lib\Tests\Fixtures;

class ItemService extends \Ninex\Lib\Http\Services\LibService
{
    protected ?string $modelClass = Item::class;
    protected array $allowedFilters = ['status'];
    public int $validations = 0;
    public function validateForm(array $data, ?string $id = null): void
    {
        $this->validations++;
        validator($data, ['name' => $id ? 'sometimes|required|string' : 'required|string', 'status' => 'sometimes|integer'])->validate();
    }
    public function cached(\Closure $callback)
    {
        return $this->remember('ttl-probe', $callback);
    }
}
