<?php

declare(strict_types=1);

namespace Raid\Pillar\Actions;

use BadMethodCallException;
use Illuminate\Support\Facades\App;
use Throwable;

/**
 * Base action class providing static execution and dependency injection support.
 *
 * Actions encapsulate single business operations. Use static `exec()` for
 * automatic DI resolution, or instantiate directly with `execute()`.
 *
 * @example CreateUserAction::exec($userData);
 */
abstract class BaseAction implements Contracts\Action
{
    /**
     * Execute the action with automatic dependency injection.
     *
     * @param  mixed  ...$arguments  Arguments to pass to handle()
     * @return mixed The result of the handle() method
     *
     * @throws Throwable
     */
    public static function exec(...$arguments): mixed
    {
        return App::make(static::class)->execute(...$arguments);
    }

    /**
     * Execute the action instance.
     *
     * @param  mixed  ...$arguments  Arguments to pass to handle()
     * @return mixed The result of the handle() method
     *
     * @throws BadMethodCallException If handle() is not implemented
     * @throws Throwable
     */
    public function execute(...$arguments): mixed
    {
        if (! method_exists($this, 'handle')) {
            throw new BadMethodCallException(sprintf(
                '%s must implement the handle() method',
                static::class,
            ));
        }

        return $this->handle(...$arguments);
    }
}
