<?php

namespace Ninex\Lib\Contracts;


use Illuminate\Database\Eloquent\Builder;

/**
 * Interface FilterInterface
 * @package Ninex\Lib\Contracts
 */
interface FilterInterface
{
    /**
     * @param Builder $builder
     * @param array $input
     * @return Builder
     */
    public function handle(Builder $builder, array $input = []): Builder;
}
