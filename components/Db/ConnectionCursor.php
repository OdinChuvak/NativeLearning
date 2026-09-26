<?php

declare(strict_types=1);

namespace app\components\db;

use yii\db\Connection;

/** An independent traversal; reaching the end never repeats the last connection. */
final class ConnectionCursor
{
    private int $position = 0;
    private array $names;

    public function __construct(private readonly array $connections)
    {
        $this->names = array_keys($connections);
    }

    public function current(): Connection
    {
        return $this->connections[$this->key()];
    }

    public function key(): string
    {
        return $this->names[$this->position] ?? throw new \OutOfBoundsException('Connection traversal has ended.');
    }

    public function next(): bool
    {
        ++$this->position;
        return isset($this->names[$this->position]);
    }

    public function rewind(): void
    {
        $this->position = 0;
    }
}
