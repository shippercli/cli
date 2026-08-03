<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use ShipperCli\Contracts\ShipperPluginInterface;

final class TestShipperPlugin implements ShipperPluginInterface
{
    public function providers(): array
    {
        return ['contract-test' => TestContractProvider::class];
    }
}
