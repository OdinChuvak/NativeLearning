<?php

declare(strict_types=1);

namespace app\commands;

use app\components\db\BaseMigration;
use app\components\db\ConnectionGroup;
use app\components\db\MigrationRunner;
use Yii;
use yii\base\InvalidConfigException;
use yii\console\Exception;
use yii\console\ExitCode;
use yii\db\Connection;
use yii\di\Instance;

/** Applies migrations to their declared connections, with a separate history in each database. */
class MigrateController extends MigrationRunner
{
    private ConnectionGroup $defaultConnections;
    private string $connectionSelector = 'db';

    /**
     * @throws InvalidConfigException
     */
    public function beforeAction($action): bool
    {
        $this->connectionSelector = is_string($this->db) ? $this->db : 'db';
        $target = is_string($this->db) ? Yii::$app->get($this->db) : $this->db;
        $this->defaultConnections = ConnectionGroup::from(
            $target instanceof ConnectionGroup ? $target : Instance::ensure($target, Connection::class),
        );
        // Standard Yii preparation still requires one concrete connection.
        $this->db = $this->defaultConnections->iterate()->current();
        return parent::beforeAction($action);
    }

    /**
     * @throws Exception
     */
    public function actionUp($limit = 0): int
    {
        return $this->runForConnections('up', [$limit]);
    }

    /**
     * @throws Exception
     */
    public function actionDown($limit = 1): int
    {
        return $this->runForConnections('down', [$limit]);
    }

    /**
     * @throws Exception
     */
    public function actionRedo($limit = 1): int
    {
        return $this->runForConnections('redo', [$limit]);
    }

    /**
     * @throws Exception
     */
    public function actionTo($version): int
    {
        return $this->runForConnections('to', [$version]);
    }

    /**
     * @throws Exception
     */
    public function actionMark($version): int
    {
        return $this->runForConnections('mark', [$version]);
    }

    /**
     * @throws Exception
     */
    public function actionFresh(): int
    {
        return $this->runForConnections('fresh', []);
    }

    /**
     * @throws Exception
     */
    public function actionHistory($limit = 10): int
    {
        return $this->runForConnections('history', [$limit]);
    }

    /**
     * @throws Exception
     */
    public function actionNew($limit = 10): int
    {
        return $this->runForConnections('new', [$limit]);
    }

    /**
     * @throws Exception
     */
    private function runForConnections(string $action, array $arguments): int
    {
        $plans = [];
        foreach ($this->discoverMigrations() as $class) {
            $this->includeMigrationFile($class);
            $group = $this->defaultConnections;
            if (is_subclass_of($class, BaseMigration::class)) {
                $group = $class::getConnections() ?? $this->defaultConnections;
            }
            if (in_array('db', $this->getPassedOptions(), true)) {
                $requestedCursor = $this->defaultConnections->iterate();
                do {
                    if (!$group->contains($requestedCursor->current())) {
                        throw new Exception("Миграция {$class} не предназначена для подключения {$this->connectionSelector} (элемент {$requestedCursor->key()}).");
                    }
                } while ($requestedCursor->next());
                $group = $this->defaultConnections;
            }
            $cursor = $group->iterate();
            do {
                $connection = $cursor->current();
                $id = spl_object_id($connection);
                $plans[$id] ??= ['db' => $connection, 'name' => $cursor->key(), 'classes' => []];
                $plans[$id]['classes'][$class] = true;
            } while ($cursor->next());
        }
        if ($plans === []) {
            $this->stdout("No migration files found.\n");
            return ExitCode::OK;
        }
        foreach ($plans as $plan) {
            $this->stdout("\n=== Connection: {$plan['name']} ===\n");
            $runner = new MigrationRunner($this->id, $this->module, [
                'db' => $plan['db'],
                'migrationPath' => $this->migrationPath,
                'migrationNamespaces' => $this->migrationNamespaces,
                'migrationTable' => $this->migrationTable,
                'migrationClasses' => $plan['classes'],
                'interactive' => $this->interactive,
                'color' => $this->color,
                'compact' => $this->compact,
                'silentExitOnException' => $this->silentExitOnException,
            ]);
            try {
                $result = $runner->runAction($action, $arguments);
            } catch (\Throwable $error) {
                throw new Exception("Migration failed on connection '{$plan['name']}': " . $error->getMessage(), 0, $error);
            }
            if ($result !== ExitCode::OK || $runner->cancelled) {
                $this->stderr("Stopped on connection '{$plan['name']}'.\n");
                return $runner->cancelled ? ExitCode::UNSPECIFIED_ERROR : (int) $result;
            }
        }
        return ExitCode::OK;
    }
}
