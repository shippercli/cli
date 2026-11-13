<?php

declare(strict_types=1);

namespace App\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;

abstract class ShipperCommand extends Command
{
    /**
     * Redact HTTP URL user information at the display boundary only.
     * Providers must still redact other secret forms in their own output.
     *
     * @param string $string
     * @param 'info'|'comment'|'question'|'error'|'warn'|'alert'|null $style
     * @param 'v'|'vv'|'vvv'|'quiet'|'normal'|OutputInterface::VERBOSITY_*|null $verbosity
     */
    public function line($string, $style = null, $verbosity = null): void
    {
        parent::line($this->redactUrlCredentials($string), $style, $verbosity);
    }

    protected function redactUrlCredentials(string $text): string
    {
        return \preg_replace('~(https?://)[^/\s<>"?#]*@~i', '$1[redacted]@', $text)
            ?? '[Output withheld: URL credential redaction failed.]';
    }
}
