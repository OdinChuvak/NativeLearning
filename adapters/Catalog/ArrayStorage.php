<?php
declare(strict_types=1);
namespace app\adapters\Catalog;
use Service\Catalog\Contract\Storage;
final readonly class ArrayStorage implements Storage
{
    public function __construct(private array $products) {}
    public function all(): array
    {
        return $this->products;
    }
}
