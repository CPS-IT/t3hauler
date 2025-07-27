<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Traits\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Provides standardized input/output handling for commands
 */
trait CommandInputOutputTrait
{
    private ?SymfonyStyle $io = null;

    /**
     * Initialize SymfonyStyle with consistent settings
     */
    protected function initializeIO(InputInterface $input, OutputInterface $output): SymfonyStyle
    {
        // Always create a fresh SymfonyStyle instance for each command execution
        // This ensures proper output capturing in tests and avoids state issues
        $this->io = new SymfonyStyle($input, $output);

        return $this->io;
    }

    /**
     * Get the current SymfonyStyle instance
     */
    protected function getIO(): SymfonyStyle
    {
        if ($this->io === null) {
            throw new \LogicException('IO not initialized. Call initializeIO() first.', 2086919673);
        }

        return $this->io;
    }

    /**
     * Display formatted command title
     */
    protected function displayCommandTitle(string $title): void
    {
        $this->getIO()->title($title);
    }

    /**
     * Display formatted section header
     */
    protected function displaySection(string $section): void
    {
        $this->getIO()->section($section);
    }

    /**
     * Report error message
     */
    protected function reportError(string $message): void
    {
        $this->getIO()->error($message);
    }
}
