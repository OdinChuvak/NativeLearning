<?php

declare(strict_types=1);

namespace app\components\db;

/** Standard Yii execution restricted to the migrations assigned to one connection. */
class MigrationRunner extends \yii\console\controllers\MigrateController
{
    public ?array $migrationClasses = null;
    public bool $cancelled = false;
    private bool $discovering = false;

    public function discoverMigrations(): array
    {
        $this->discovering = true;
        try {
            return parent::getNewMigrations();
        } finally {
            $this->discovering = false;
        }
    }

    protected function getNewMigrations(): array
    {
        $classes = parent::getNewMigrations();
        return $this->migrationClasses === null ? $classes
            : array_values(array_filter($classes, fn ($class) => isset($this->migrationClasses[$class])));
    }

    protected function getMigrationHistory($limit): array
    {
        if ($this->discovering) {
            return [];
        }
        $history = parent::getMigrationHistory(null);
        if ($this->migrationClasses !== null) {
            $history = array_intersect_key($history, $this->migrationClasses);
        }
        return $limit === null ? $history : array_slice($history, 0, $limit, true);
    }

    public function confirm($message, $default = false): bool
    {
        $result = parent::confirm($message, $default);
        $this->cancelled = $this->cancelled || !$result;
        return $result;
    }
}
