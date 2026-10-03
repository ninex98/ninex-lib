<?php

namespace Ninex\Lib\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\{Builder, Model};
use Illuminate\Support\Facades\{DB, Schema};
use Ninex\Lib\Contracts\CrudActions;
use Ninex\Lib\Core\Tests\{BusinessFlowContract, BusinessFlowProbe};
use Ninex\Lib\Scaffolding\{CrudGenerator, ResourceDefinition};

class BusinessServiceTest extends TestCase
{
    use BusinessFlowContract;

    protected function setUp(): void
    {
        parent::setUp();
        $plan = (new CrudGenerator())->plan(new ResourceDefinition('BusinessRecord', 'laravel', 'name:string,status:integer', 'business_records'), sys_get_temp_dir());
        foreach ($plan as $path => $code) {
            $isModel = str_starts_with($path, 'app/Models/');
            $isService = str_starts_with($path, 'app/Services/');
            $isMigration = str_starts_with($path, 'database/migrations/');
            if ($isMigration || (($isModel || $isService) && !class_exists($isModel ? \App\Models\BusinessRecord::class : \App\Services\BusinessRecordService::class))) {
                $file = tempnam(sys_get_temp_dir(), 'ninex-business-');
                try {
                    file_put_contents($file, $code);
                    $result = require $file;
                    if ($isMigration) {
                        $result->up();
                    }
                } finally {
                    unlink($file);
                }
            }
        }
        Schema::create('business_audits', function ($table) {
            $table->integer('record_id');
        });
        $this->actingAs(new GenericUser(['id' => 1]));
    }

    protected function businessService(): CrudActions
    {
        return new class ($this->app->make(\Illuminate\Contracts\Auth\Factory::class), $this->app->make(\Illuminate\Contracts\Validation\Factory::class)) extends \App\Services\BusinessRecordService {
            use BusinessFlowProbe;

            public function validateForm(array $data, string|int|null $id = null): array
            {
                $this->checkpoint('validate');
                return parent::validateForm($data, $id);
            }

            protected function rules(string|int|null $id = null): array
            {
                return array_replace(parent::rules($id), [
                    'name' => ($id === null ? 'required_without:form_name' : 'sometimes|required').'|string',
                    'form_name' => 'sometimes|required|string',
                ]);
            }

            public function scopeQuery(Builder $query, array $filters): void
            {
                if (isset($filters['either_name'])) {
                    $query->where('name', $filters['either_name'])->orWhere('status', 0);
                    unset($filters['either_name']);
                }
                if (isset($filters['keyword'])) {
                    $this->scopeWhereLike($query, ['name' => $filters['keyword']]);
                    unset($filters['keyword']);
                }
                if (isset($filters['min_status'])) {
                    $query->where('status', '>=', $filters['min_status']);
                    unset($filters['min_status']);
                }
                $this->scopeWhere($query, $filters);
            }

            protected function saved(Model $record, bool $isEdit = false): void
            {
                $record->getConnection()->table('business_audits')->insert(['record_id' => $record->getKey()]);
                if ($this->moveOutsideScope) {
                    $record->forceFill(['owner_id' => 9])->save();
                }
                $this->checkpoint('saved');
            }

            protected function deleting(Model $record): void
            {
                $this->checkpoint('deleting');
            }

            protected function deleted(Model $record): void
            {
                $record->getConnection()->table('business_audits')->where('record_id', $record->getKey())->delete();
                $this->checkpoint('deleted');
            }
        };
    }

    protected function recordCount(): int
    {
        return DB::table('business_records')->count();
    }

    protected function insertOutsideRecord(): void
    {
        DB::table('business_records')->insert(['name' => 'foreign', 'status' => 0, 'owner_id' => 2]);
    }

    protected function auditCount(): int
    {
        return DB::table('business_audits')->count();
    }
}
