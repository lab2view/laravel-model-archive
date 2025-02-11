<?php

namespace Lab2view\ModelArchive\Console\Commands\Base;

use Illuminate\Console\Command as IlluminateCommand;
use Illuminate\Support\Facades\Config;
use Lab2view\ModelArchive\Enums\BetweenScriptStep;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Command extends IlluminateCommand
{
    /**
     * List of callables to execute before commands
     *
     * @var array<callable>
     */
    protected array $after = [];

    /**
     * List of callables to execute after commands
     *
     * @var array<callable>
     */
    protected array $before = [];

    public function __construct()
    {
        parent::__construct();

        $between = Config::get('model-archive.between_commands');
        if ($between) {
            $this->before = array_merge(
                $between[BetweenScriptStep::ALL->value],
                $between[BetweenScriptStep::BEFORE->value][BetweenScriptStep::ALL->value],
                $between[BetweenScriptStep::BEFORE->value][$this->signature]
            );
            $this->after = array_merge(
                $between[BetweenScriptStep::ALL->value],
                $between[BetweenScriptStep::AFTER->value][BetweenScriptStep::ALL->value],
                $between[BetweenScriptStep::AFTER->value][$this->signature]
            );
        }

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->execute_between_scripts(BetweenScriptStep::BEFORE);
        $returned = parent::execute($input, $output);
        $this->execute_between_scripts(BetweenScriptStep::AFTER);

        return $returned;
    }

    /**
     * Execute collables before or after commmand
     */
    public function execute_between_scripts(BetweenScriptStep $step): void
    {
        $step_value = $step->value;
        /** @phpstan-ignore-next-line */
        foreach ($this->$step_value as $collable) {
            call_user_func($collable);
        }
    }
}
