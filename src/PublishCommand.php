<?php

namespace Roiwk\WebmanSail;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PublishCommand extends Command
{
    protected function configure()
    {
        $this->setName('sail:publish')
            ->setDescription('Publish Webman Sail\'s Docker file to base path.')
            ->setHelp('This Command Helps you to publish Docker file.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');
        $question = new Question(
            '<comment>This step will force overwrite existing "/docker/php" path(delete & re-publish to the path), are you sure to execute?</comment> [Y/N] (Default:Y)  ',
            'Y',
        );
        $answer = $helper->ask($input, $output, $question);

        if (strtoupper($answer) == 'Y') {
            remove_dir(base_path('/docker/php'));
            copy_dir(__DIR__ . '/../php', base_path('/docker/php'), true);
            file_put_contents(
                base_path('docker-compose.yml'),
                str_replace(
                    './vendor/roiwk/webman-sail/php/',
                    './docker/php',
                    file_get_contents(base_path('docker-compose.yml'))
                )
            );
            $output->writeln('<info>The publish was successful, please see the files in "/docker/php" path.</info>');
        } else {
            $output->writeln('Nothing to do.');
        }

        return self::SUCCESS;
    }
}