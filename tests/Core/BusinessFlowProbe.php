<?php

namespace Ninex\Lib\Core\Tests;

/** 测试用钩子记录。 / Observe hook order and inject transaction failures. */
trait BusinessFlowProbe
{
    public array $events = [];
    public ?string $failAt = null;
    public bool $moveOutsideScope = false;
    public bool $injectForbiddenField = false;

    public function restrictFields(array $writable, array $readable): void
    {
        $this->writable = $writable;
        $this->readable = $readable;
    }

    protected function checkpoint(string $event): void
    {
        $this->events[] = $event;
        if ($this->failAt === $event) {
            throw new \RuntimeException($event);
        }
    }

    protected function saving(array &$data, string|int|null $id = null): void
    {
        $this->checkpoint('saving');
        if (isset($data['form_name'])) {
            $data['name'] = strtoupper($data['form_name']);
            unset($data['form_name']);
        }
        if (isset($data['name'])) {
            $data['name'] .= '!';
        }
        if ($this->injectForbiddenField) {
            $data['owner_id'] = 9;
        }
    }
}
