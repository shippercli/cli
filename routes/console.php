<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your console based routes.
| Each closure is bound to a command instance allowing a simple
| approach to interacting with each command's input/output.
|
*/

Artisan::command('inspire', function (): void {
    /** @phpstan-ignore variable.undefined */
    $this->comment('Simplicity is the ultimate sophistication. - Leonardo da Vinci');
})->purpose('Display an inspiring quote');
