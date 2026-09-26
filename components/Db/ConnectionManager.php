<?php

declare(strict_types=1);

namespace app\components\db;

use yii\base\BootstrapInterface;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\db\Connection;
use yii\di\Instance;

final class ConnectionManager extends Component implements BootstrapInterface
{
    /** @var array<string, array<string, array|Connection|string>> */
    public array $groups = [];
    /** @var array<string, array|Connection|string> Standalone connections or component references. */
    public array $connections = ['db' => 'db'];
    private array $instances = [];
    private array $groupInstances = [];

    /**
     * @throws InvalidConfigException
     */
    public function init(): void
    {
        parent::init();
        foreach ($this->groups as $group => $definitions) {
            $this->validateName($group);
            if (!is_array($definitions) || $definitions === [] || array_key_exists($group, $this->connections)) {
                throw new InvalidConfigException("Invalid or ambiguous connection group: {$group}");
            }
            foreach (array_keys($definitions) as $name) {
                $this->validateName($name);
            }
        }
        foreach (array_keys($this->connections) as $name) {
            $this->validateName($name);
        }
    }

    /**
     * @throws InvalidConfigException
     */
    private function validateName($name): void
    {
        if (!is_string($name) || $name === '' || str_contains($name, ':')) {
            throw new InvalidConfigException('Connection and group names must be nonempty strings without a colon.');
        }
    }

    /**
     * @throws InvalidConfigException
     */
    public function getConnection(string $name): Connection
    {
        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }
        if (str_contains($name, ':')) {
            [$group, $member] = explode(':', $name, 2);
            $definition = $this->groups[$group][$member] ?? null;
        } else {
            $definition = $this->connections[$name] ?? null;
        }
        if ($definition === null) {
            throw new InvalidConfigException("Unknown connection: {$name}");
        }
        return $this->instances[$name] = Instance::ensure($definition, Connection::class);
    }

    /**
     * @throws InvalidConfigException
     */
    public function getGroup(string $name): ConnectionGroup
    {
        if (!isset($this->groups[$name])) {
            throw new InvalidConfigException("Unknown connection group: {$name}");
        }
        if (!isset($this->groupInstances[$name])) {
            $connections = [];
            foreach (array_keys($this->groups[$name]) as $member) {
                $connections[$member] = $this->getConnection($name . ':' . $member);
            }
            $this->groupInstances[$name] = new ConnectionGroup($connections);
        }
        return $this->groupInstances[$name];
    }

    /**
     * @throws InvalidConfigException
     */
    public function bootstrap($app): void
    {
        $components = [];
        foreach ($this->groups as $name => $definitions) {
            $components[$name] = fn () => $this->getGroup($name);
            foreach (array_keys($definitions) as $member) {
                $id = $name . ':' . $member;
                $components[$id] = fn () => $this->getConnection($id);
            }
        }
        foreach ($components as $id => $definition) {
            if ($app->has($id)) {
                throw new InvalidConfigException("Database component conflicts with existing component: {$id}");
            }
        }
        $app->setComponents($components);
    }
}
