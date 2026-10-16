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
        $redacted = \preg_replace('~\b(https?://)[^/\s<>"\x27?#]*@~i', '$1[redacted]@', $string);
        parent::line($redacted ?? '[Output withheld: URL credential redaction failed.]', $style, $verbosity);
    }
}
