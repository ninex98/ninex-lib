<?php

namespace Ninex\Lib\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Gate};
use Ninex\Lib\Http\Controllers\LibController;
use Ninex\Lib\Http\Services\LibService;
use Ninex\Lib\Models\LibModel;

class LegacyOpenItem extends LibModel
{
    protected $table = 'items';
    public $timestamps = false;
}

class LegacyOpenService extends LibService
{
    // Existing applications often override model() instead of using naming conventions.
    public function model(): \Illuminate\Database\Eloquent\Model
    {
        return new LegacyOpenItem();
    }
}

class LegacyCrudController extends LibController
{
    public function __construct(Request $request, LegacyOpenService $service)
    {
        parent::__construct($request);
        $this->service = $service;
    }
}

class PolicyCrudController extends LegacyCrudController
{
    protected bool $usePolicy = true;
}

class LegacyItemPolicy
{
    public function viewAny($user): bool
    {
        return $user->role === 'editor';
    }
    public function create($user): bool
    {
        return $this->viewAny($user);
    }
    public function view($user, LegacyOpenItem $item): bool
    {
        return $this->viewAny($user) && $user->id === $item->tenant_id;
    }
    public function update($user, LegacyOpenItem $item): bool
    {
        return $this->view($user, $item);
    }
    public function delete($user, LegacyOpenItem $item): bool
    {
        return $this->view($user, $item);
    }
}

class LegacyCrudTest extends TestCase
{
    protected function defineRoutes($router): void
    {
        $router->apiResource('api/legacy/items', LegacyCrudController::class);
        $router->apiResource('api/policy/items', PolicyCrudController::class);
    }

    public function testLegacyModelsAndOptionalValidatorsWorkWithoutFillableDeclarations(): void
    {
        $service = new LegacyOpenService();
        $item = $service->validateStore(['id' => 999, 'name' => 'legacy', 'status' => 0]);
        $this->assertNotSame(999, $item->id);
        $this->assertSame([], $item->getFillable());
        $this->assertSame(['id'], $item->getGuarded());
        $this->assertSame('changed', $service->validateUpdate((string) $item->id, ['name' => 'changed'])->name);
    }

    public function testNormalizedDataIsNotPassedThroughInputValidationAgain(): void
    {
        $service = new class () extends LegacyOpenService {
            public int $validations = 0;
            public function validateForm(array $data, ?string $id = null): void
            {
                $this->validations++;
                validator($data, ['name' => 'required|string', 'status' => 'required|array', 'status.*' => 'integer'])->validate();
            }
            public function saveInput(array $input, ?string $id = null)
            {
                $this->validateForm($input, $id);
                $input['status'] = end($input['status']);
                return $id === null ? $this->create($input) : $this->update($id, $input);
            }
        };
        $item = $service->saveInput(['name' => 'path', 'status' => [10, 0]]);
        $this->assertSame(0, $item->status);
        $this->assertSame(1, $service->validations);
        $item = $service->saveInput(['name' => 'updated path', 'status' => [10, 2]], (string) $item->id);
        $this->assertSame(2, $item->status);
        $this->assertSame(2, $service->validations);
    }

    public function testSavingHookCanExtractDatabaseFieldsBeforePersistence(): void
    {
        $service = new class () extends LegacyOpenService {
            public function saving(&$data, $primaryKey = '')
            {
                $data = ['name' => $data['form']['name']];
            }
        };
        $item = $service->store(['form' => ['name' => 'nested input']]);
        $this->assertSame('nested input', $item->name);
        $this->assertSame('updated', $service->update((string) $item->id, ['form' => ['name' => 'updated']])->name);
    }

    public function testLegacyControllerDoesNotAddGateChecksOrExtraRecordLookups(): void
    {
        $this->actingAs(new GenericUser(['id' => 1, 'role' => 'editor']));
        Gate::before(fn () => throw new \LogicException('Unexpected automatic Gate call'));
        $id = $this->postJson('/api/legacy/items', ['name' => 'created'])->assertSuccessful()->json('data.id');
        $this->getJson('/api/legacy/items')->assertOk();
        $this->getJson('/api/legacy/items/'.$id)->assertOk();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->putJson('/api/legacy/items/'.$id, ['name' => 'updated'])->assertOk();
        $this->assertCount(1, array_filter(DB::getQueryLog(), fn ($query) => str_starts_with(strtolower($query['query']), 'select')));
        DB::flushQueryLog();
        $this->deleteJson('/api/legacy/items/'.$id)->assertOk();
        $this->assertCount(1, array_filter(DB::getQueryLog(), fn ($query) => str_starts_with(strtolower($query['query']), 'select')));
        DB::disableQueryLog();
        $this->assertSame(0, LegacyOpenItem::count());
    }

    public function testPolicyOptInRejectsEveryActionWhenNoRuleIsRegistered(): void
    {
        $this->actingAs(new GenericUser(['id' => 1, 'role' => 'editor']));
        $item = LegacyOpenItem::create(['name' => 'unchanged']);
        $url = '/api/policy/items';
        $this->getJson($url)->assertForbidden();
        $this->getJson($url.'/'.$item->id)->assertForbidden();
        $this->postJson($url, ['name' => 'forbidden'])->assertForbidden();
        $this->putJson($url.'/'.$item->id, ['name' => 'forbidden'])->assertForbidden();
        $this->deleteJson($url.'/'.$item->id)->assertForbidden();
        $this->assertSame(1, LegacyOpenItem::count());
        $this->assertSame('unchanged', $item->fresh()->name);
    }

    public function testRegisteredPolicyAllowsAuthorizedActionsAndRejectsOtherOwners(): void
    {
        Gate::policy(LegacyOpenItem::class, LegacyItemPolicy::class);
        $this->actingAs(new GenericUser(['id' => 1, 'role' => 'editor']));
        $url = '/api/policy/items';
        $id = $this->postJson($url, ['name' => 'allowed'])->assertSuccessful()->json('data.id');
        $this->getJson($url)->assertOk();
        $this->getJson($url.'/'.$id)->assertOk();
        $this->putJson($url.'/'.$id, ['name' => 'updated'])->assertOk();
        $this->actingAs(new GenericUser(['id' => 2, 'role' => 'editor']));
        $this->putJson($url.'/'.$id, ['name' => 'forbidden'])->assertForbidden();
        $this->deleteJson($url.'/'.$id)->assertForbidden();
        $this->assertSame('updated', LegacyOpenItem::find($id)->name);
        $this->actingAs(new GenericUser(['id' => 1, 'role' => 'editor']));
        $this->deleteJson($url.'/'.$id)->assertOk();
    }
}
