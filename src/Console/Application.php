<?php


namespace ChrisBoulton\Resque\Console;

use ChrisBoulton\Resque\Console\Command\CheckStatusCommand;
use ChrisBoulton\Resque\Console\Command\PushJobCommand;
use Symfony\Component\Console;


class Application extends Console\Application
{
    protected function getDefaultCommands()
    {
        $commands = parent::getDefaultCommands();
        $commands[] = new CheckStatusCommand();
        $commands[] = new PushJobCommand();
        return $commands;
    }
}