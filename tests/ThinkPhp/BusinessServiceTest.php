<?php

namespace Ninex\Lib\ThinkPhp\Tests;

use Ninex\Lib\Contracts\CrudActions;
use Ninex\Lib\Core\Tests\{BusinessFlowContract, BusinessFlowProbe};
use Ninex\Lib\Scaffolding\{CrudGenerator, ResourceDefinition};
use think\{DbManager, Request};
use think\db\Query;

class BusinessServiceTest extends TestCase
{
    use BusinessFlowContract;

    private DbManager $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new DbManager();
        $this->db->setConfig($this->databaseFixture->think());
        $plan = (new CrudGenerator())->plan(new ResourceDefinition('BusinessRecord', 'thinkphp', 'name:string,status:integer', 'business_records', namespace: 'BusinessThinkApp'), sys_get_temp_dir());
        if (!class_exists(\BusinessThinkApp\service\BusinessRecordService::class)) {
            $file = tempnam(sys_get_temp_dir(), 'ninex-business-');
            try {
                file_put_contents($file, $plan['app/service/BusinessRecordService.php']);
                require $file;
            } finally {
                unlink($file);
            }
        }
        $sql = $plan['database/ninex/business_records.'.$this->databaseFixture->driver().'.sql'];
        foreach (explode(';', $sql) as $statement) {
            if (trim($statement) !== '') {
                $this->db->connect()->execute($statement);
            }
        }
        $this->db->connect()->execute('CREATE TABLE business_audits (record_id INTEGER NOT NULL)');
    }

    protected function businessService(): CrudActions
    {
        $request = (new Request())->withMiddleware(['actor' => ['id' => 1]]);
        return new class ($this->db, $request) extends \BusinessThinkApp\service\BusinessRecordService {
            use BusinessFlowProbe;

            public function validateForm(array $data, string|int|null $id = null): array
            {
                $this->checkpoint('validate');
                return parent::validateForm($data, $id);
            }

            protected function rules(array $data, string|int|null $id = null): array
            {
                $rules = parent::rules($data, $id);
                if (array_key_exists('form_name', $data)) {
                    $rules['form_name'] = 'require|string';
                    if (!array_key_exists('name', $data)) {
                        unset($rules['name']);
                    }
                }
                return $rules;
            }

            public function scopeQuery(Query $query, array $filters): void
            {
                if (isset($filters['either_name'])) {
                    $query->where('name', $filters['either_name'])->whereOr('status', 0);
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

            protected function saved(array $record, bool $isEdit = false): void
            {
                $this->db->connect($this->connection)->table('business_audits')->insert(['record_id' => $record['id']]);
                if ($this->moveOutsideScope) {
                    $this->query()->where('id', $record['id'])->update(['owner_id' => 9]);
                }
                $this->checkpoint('saved');
            }

            protected function deleting(array $record): void
            {
                $this->checkpoint('deleting');
            }

            protected function deleted(array $record): void
            {
                $this->db->connect($this->connection)->table('business_audits')->where('record_id', $record['id'])->delete();
                $this->checkpoint('deleted');
            }
        };
    }

    protected function recordCount(): int
    {
        return $this->db->table('business_records')->count();
    }

    protected function insertOutsideRecord(): void
    {
        $this->db->table('business_records')->insert(['name' => 'foreign', 'status' => 0, 'owner_id' => 2]);
    }

    protected function auditCount(): int
    {
        return $this->db->table('business_audits')->count();
    }
}
