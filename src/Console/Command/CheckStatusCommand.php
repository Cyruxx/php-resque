<?php


namespace ChrisBoulton\Resque\Console\Command;


use ChrisBoulton\Resque\Job\JobStatus;
use ChrisBoulton\Resque\Resque;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;

class CheckStatusCommand extends Command
{
    protected function configure()
    {
        $this->setName('job:status')
            ->setDescription('Check the status of a job.')
            ->addArgument('serverString', InputArgument::REQUIRED, 'The server string, eg: 127.0.0.1:6379')
            ->addArgument('jobId', InputArgument::REQUIRED, 'The job id to monitor');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger         = new ConsoleLogger($output, [LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL]);
        $jobId          = $input->getArgument('jobId');
        $serverString   = $input->getArgument('serverString');

        Resque::setBackend($serverString);

        $status = new JobStatus($jobId);

        if ($status->isTracking() == false) {
            $logger->info('Resque is not tracking the status of this job.');
            return false;
        }

        $logger->info('Tracking status of ' . $jobId . '. Press CTRL-C to stop.');
        $output->writeln('');

        while (true) {
            $logger->info('Status: ' . (string) $status->get());

            if (!$status->get()) {
                break;
            }
        }
    }
}