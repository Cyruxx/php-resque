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

class PushJobCommand extends Command
{
    protected function configure()
    {
        $this->setName('job:push')
            ->setDescription('Push a job to a queue.')
            ->addArgument('serverString', InputArgument::REQUIRED, 'The server string, eg: 127.0.0.1:6379')
            ->addArgument('queue', InputArgument::REQUIRED, 'The job id to monitor')
            ->addArgument('class', InputArgument::REQUIRED, 'The class of the processor.')
            ->addArgument('args', InputArgument::OPTIONAL, 'Arguments as json format eg: "{\"test\": \"test\"}"');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $logger         = new ConsoleLogger($output, [LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL]);
        $serverString   = $input->getArgument('serverString');
        $queue          = $input->getArgument('queue');
        $class          = $input->getArgument('class');
        $args           = $input->getArgument('args') ?: null;

        if ($args) {
            $args = json_decode($args, true);

            if (!$args) {
                $logger->error('Invalid json given.');
                return;
            }
        }

        Resque::setBackend($serverString);

        $jobId = Resque::enqueue($queue, $class, $args, true);
        $logger->info('Enqueued message. Metadata: ' . json_encode([
            'queue' => $queue,
            'class' => $class,
            'args'  => $args
        ]));
        $logger->info('The jobId is: ' . $jobId);
    }
}