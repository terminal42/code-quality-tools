<?php

declare(strict_types=1);

namespace Terminal42\CodeQualityTools\Composer;

use Composer\IO\IOInterface;
use Symfony\Component\Process\Process;

final class ProcessManager
{
    /**
     * @var array<string, Process>
     */
    private array $processes = [];

    public function add(Process $process, string $name): void
    {
        if (isset($this->processes[$name])) {
            throw new \InvalidArgumentException(\sprintf('A process named "%s" already exists.', $name));
        }

        $this->processes[$name] = $process;
    }

    public function run(IOInterface $io): void
    {
        foreach ($this->processes as $process) {
            $process->start();
        }

        $failedProcesses = $this->waitForProcesses($io);
        if ([] !== $failedProcesses) {
            throw new ProcessExecutionException('Processes failed: '.implode(', ', $failedProcesses));
        }
    }

    private function writeProcessOutput(IOInterface $io, string $name, Process $process): void
    {
        if ('' !== $process->getOutput()) {
            $io->write($this->prefixOutput($name, $process->getOutput()), false);
        }

        if ('' !== $process->getErrorOutput()) {
            $io->writeError($this->prefixOutput($name, $process->getErrorOutput()), false);
        }
    }

    private function prefixOutput(string $name, string $output): string
    {
        $prefix = \sprintf('<info>[%s]</info> ', $name);

        return preg_replace('{^(?!\z)}m', $prefix, $output) ?? $output;
    }

    /**
     * @return list<string>
     */
    private function waitForProcesses(IOInterface $io): array
    {
        $failedProcesses = [];
        $runningProcesses = $this->processes;

        while ([] !== $runningProcesses) {
            foreach ($runningProcesses as $name => $process) {
                if ($process->isRunning()) {
                    $process->checkTimeout();

                    continue;
                }

                unset($runningProcesses[$name]);
                $this->writeProcessOutput($io, $name, $process);
                if (!$process->isSuccessful()) {
                    $failedProcesses[] = $name;
                }
            }

            if ([] !== $runningProcesses) {
                usleep(10_000);
            }
        }

        return $failedProcesses;
    }
}
