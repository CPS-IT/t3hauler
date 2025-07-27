<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Traits\Command;

/**
 * Provides standardized progress reporting and status messages
 */
trait CommandProgressTrait
{
    use CommandInputOutputTrait;

    /**
     * Report operation success with details
     */
    protected function reportSuccess(string $message, ?array $details = null): void
    {
        $io = $this->getIO();

        $io->success($message);

        if ($details) {
            foreach ($details as $key => $value) {
                $io->text("  {$key}: {$value}");
            }
        }
    }

    /**
     * Report operation warning with context
     */
    protected function reportWarning(string $message, ?string $context = null): void
    {
        $io = $this->getIO();

        $io->warning($message);

        if ($context) {
            $io->text($context);
        }
    }

    /**
     * Display dry-run status message
     */
    protected function reportDryRunMode(): void
    {
        $this->getIO()->note('DRY RUN MODE - No changes will be applied');
    }

    /**
     * Display operation progress with counts
     */
    protected function reportProgress(string $operation, int $processed, int $total): void
    {
        $percentage = $total > 0 ? round(($processed / $total) * 100, 1) : 0;
        $this->getIO()->text("{$operation}: {$processed}/{$total} ({$percentage}%)");
    }

    /**
     * Display summary table with consistent formatting
     */
    protected function displaySummaryTable(array $headers, array $rows, string $title = 'Summary'): void
    {
        $io = $this->getIO();

        $io->section($title);
        $io->table($headers, $rows);
    }

    /**
     * Display information message
     */
    protected function reportInfo(string $message): void
    {
        $this->getIO()->text($message);
    }

    /**
     * Display note with highlighting
     */
    protected function reportNote(string $message): void
    {
        $this->getIO()->note($message);
    }

    /**
     * Display comment/additional information
     */
    protected function reportComment(string $message): void
    {
        $this->getIO()->comment($message);
    }

    /**
     * Ask confirmation with default response
     */
    protected function askConfirmation(string $question, bool $default = false): bool
    {
        return $this->getIO()->confirm($question, $default);
    }

    /**
     * Display operation statistics in formatted table
     */
    protected function displayStatistics(array $statistics, string $title = 'Statistics'): void
    {
        $rows = [];
        foreach ($statistics as $key => $value) {
            $rows[] = [ucfirst(str_replace('_', ' ', $key)), $value];
        }

        $this->displaySummaryTable(['Metric', 'Value'], $rows, $title);
    }
}
