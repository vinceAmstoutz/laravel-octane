<?php

namespace Laravel\Octane\Concerns;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;
use Laravel\Octane\Events\TickReceived;
use Laravel\Octane\FrankenPhp\InvokeTickCallable as FrankenPhpInvokeTickCallable;
use Laravel\Octane\FrankenPhp\ServerProcessInspector as FrankenPhpServerProcessInspector;
use Laravel\Octane\Swoole\InvokeTickCallable as SwooleInvokeTickCallable;
use Laravel\Octane\Swoole\ServerProcessInspector as SwooleServerProcessInspector;
use RuntimeException;

trait RegistersTickHandlers
{
    /**
     * Register a callback to be called every N seconds.
     *
     * @return \Laravel\Octane\Swoole\InvokeTickCallable|\Laravel\Octane\FrankenPhp\InvokeTickCallable
     */
    public function tick(string $key, callable $callback, int $seconds = 1, bool $immediate = true)
    {
        $store = Cache::store('octane');
        $exceptionHandler = app(ExceptionHandler::class);

        $listener = match (true) {
            $this->isSwooleServerRunning() => new SwooleInvokeTickCallable(
                $key,
                $callback,
                $seconds,
                $immediate,
                $store,
                $exceptionHandler
            ),
            $this->isFrankenPhpServerRunning() => new FrankenPhpInvokeTickCallable(
                $key,
                $callback,
                $seconds,
                $immediate,
                $store,
                $exceptionHandler
            ),
            default => throw new RuntimeException('Tick functionality is not supported in this environment.'),
        };

        app(Dispatcher::class)->listen(
            TickReceived::class,
            $listener
        );

        return $listener;
    }

    /**
     * Check if the Swoole server is running.
     */
    protected function isSwooleServerRunning(): bool
    {
        return app(SwooleServerProcessInspector::class)
            ->serverIsRunning();
    }

    /**
     * Check if the FrankenPHP server is running.
     */
    protected function isFrankenPhpServerRunning(): bool
    {
        return app(FrankenPhpServerProcessInspector::class)
            ->serverIsRunning();
    }
}
