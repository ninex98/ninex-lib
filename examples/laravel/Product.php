<?php

namespace Ninex\Lib\Examples\Laravel;

class Product extends \Ninex\Lib\Models\LibModel
{
    /** 商品表；关联和转换器可在此扩展。 / Product table; add relations and casts here. */
    protected $table = 'products';
    public $timestamps = false;
}
