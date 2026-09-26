<?php

declare(strict_types=1);

namespace app\components\db;

use yii\db\Connection;

/** A named collection, not a database connection or shared transaction. */
final readonly class ConnectionGroup
{
    public static function from(Connection|self $connections): self
    {
        return $connections instanceof self ? $connections : new self(['default' => $connections]);
    }

    public function __construct(private array $connections)
    {
        if ($connections === []) {
            throw new \InvalidArgumentException('A connection group must not be empty.');
        }
        foreach ($connections as $name => $connection) {
            if (!is_string($name) || $name === '' || str_contains($name, ':') || !$connection instanceof Connection) {
                throw new \InvalidArgumentException('A group must contain named Yii connections.');
            }
        }
    }

    public function getConnection(string $name): Connection
    {
        return $this->connections[$name] ?? throw new \InvalidArgumentException("Unknown connection: {$name}");
    }

    public function iterate(): ConnectionCursor
    {
        return new ConnectionCursor($this->connections);
    }

    public function contains(Connection $connection): bool
    {
        return in_array($connection, $this->connections, true);
    }
}
