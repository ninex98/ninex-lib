<?php

namespace Ninex\Lib\Core;

use InvalidArgumentException;
use JsonSerializable;

final class Page implements JsonSerializable
{
    public function __construct(public readonly array $items, public readonly int $total, public readonly int $pageSize, public readonly int $currentPage)
    {
        if ($total < 0 || $pageSize < 1 || $currentPage < 1) {
            throw new InvalidArgumentException('Invalid pagination values.');
        }
    }

    public function toArray(): array
    {
        return ['data' => $this->items, 'total' => $this->total, 'page_size' => $this->pageSize,
            'current_page' => $this->currentPage, 'total_pages' => max(1, (int) ceil($this->total / $this->pageSize))];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
