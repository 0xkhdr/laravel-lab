<?php

declare(strict_types=1);

namespace Raid\Pillar\Actions\Contracts;

/**
 * Contract for action classes.
 *
 * Actions are single-purpose classes that encapsulate business logic.
 */
interface Action
{
    /**
     * Execute the action with automatic dependency injection.
     */
    public static function exec(mixed ...$arguments): mixed;

    /**
     * Execute the action instance.
     */
    public function execute(mixed ...$arguments): mixed;
}
