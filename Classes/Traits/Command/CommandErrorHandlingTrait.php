<?php

declare(strict_types=1);

namespace Cpsit\T3hauler\Traits\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Provides standardized error handling and exception management
 */
trait CommandErrorHandlingTrait
{
    use CommandInputOutputTrait;

    /**
     * Handle exceptions with consistent error reporting
     */
    protected function handleCommandException(\Exception $e, OutputInterface $output, string $context = 'Command execution failed'): int
    {
        $io = $this->getIO();

        $io->error($context . ': ' . $e->getMessage());

        if ($output->isVerbose()) {
            $io->text('Exception: ' . get_class($e));
            $io->text('Stack trace:');
            $io->text($e->getTraceAsString());
        }

        return Command::FAILURE;
    }

    /**
     * Report validation errors with suggestions
     */
    protected function reportValidationError(string $message, ?string $suggestion = null): int
    {
        $io = $this->getIO();

        $io->error($message);

        if ($suggestion) {
            $io->note($suggestion);
        }

        return Command::FAILURE;
    }

    /**
     * Safe execution wrapper with error handling
     */
    protected function safeExecute(callable $operation, string $context = 'Operation'): int
    {
        try {
            return $operation();
        } catch (\InvalidArgumentException $e) {
            return $this->reportValidationError($e->getMessage());
        } catch (\LogicException $e) {
            // Re-throw LogicException as these indicate programming errors
            throw $e;
        } catch (\Exception $e) {
            $io = $this->getIO();
            $io->error($context . ' failed: ' . $e->getMessage());

            // Note: SymfonyStyle doesn't have isVerbose(), always show detailed error info
            $io->text('Exception: ' . get_class($e));
            $io->text('Stack trace:');
            $io->text($e->getTraceAsString());

            return Command::FAILURE;
        }
    }
}