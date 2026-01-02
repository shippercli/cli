<?php

declare(strict_types=1);

namespace App\Commands\Concerns;

trait FormatsDeploymentPlan
{
    /**
     * Safely convert a plan value to string for display.
     *
     * @param array<string, mixed> $plan
     */
    private function getPlanValue(array $plan, string $key, string $default = 'unknown'): string
    {
        $value = $plan[$key] ?? $default;

        return \is_scalar($value) ? (string) $value : $default;
    }
}
