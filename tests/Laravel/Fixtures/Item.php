<?php

namespace Ninex\Lib\Tests\Fixtures;

class Item extends \Ninex\Lib\Models\LibModel
{
    protected $table = 'items';
    protected $fillable = ['name', 'status', 'tenant_id'];
    public $timestamps = false;
}
