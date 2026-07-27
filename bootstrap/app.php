<?php

declare(strict_types=1);

use App\Kernel;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;
use LaravelZero\Framework\Application;

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new Laravel Zero application
| instance which serves as the "glue" for all the components.
|
*/

$app = new Application(
    \dirname(__DIR__),
);

/*
|--------------------------------------------------------------------------
| Bind Important Interfaces
|--------------------------------------------------------------------------
|
| Next, we need to bind some important interfaces into the container so
| we will be able to resolve them when needed.
|
*/

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    Kernel::class,
);

$app->singleton(
    ExceptionHandler::class,
    Handler::class,
);

/*
|--------------------------------------------------------------------------
| Return The Application
|--------------------------------------------------------------------------
|
| This script returns the application instance. The instance is given to
| the calling script so we can separate the building of the instances
| from the actual running of the application and sending responses.
|
*/

return $app;
