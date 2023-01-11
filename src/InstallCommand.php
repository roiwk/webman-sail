<?php

namespace Roiwk\WebmanSail;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Process\Process;
use RuntimeException;

class InstallCommand extends Command
{

    protected $services = [
        'mysql',
        'pgsql',
        'mariadb',
        'redis',
        'memcached',
        'meilisearch',
        'minio',
    ];

    /**
     * @return void
     */
    protected function configure()
    {
        $this->setName('sail:install')
            ->setDescription('Install Webman Sail\'s default Docker Compose file.')
            ->setHelp('This Command Helps you to install default Docker Compose file.')
            ->addOption('with', 'w', InputOption::VALUE_REQUIRED, 'The services that should be included in the installation.', null)
            ->addOption('devcontainer', 'devc', InputOption::VALUE_REQUIRED, 'Create a .devcontainer configuration directory.', null);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        if ($input->getOption('with')) {
            $services = $input->getOption('with') == 'none' ? [] : explode(',', $input->getOption('with'));
        } elseif ($input->getOption('no-interaction')) {
            $services = ['mysql', 'redis',];
        } else {
            $helper = $this->getHelper('question');
            // question, info, comment, error
            $question = new Question(
                    "<question>Which services would you like to install?</question>" . PHP_EOL
                    . '<comment>eg:' . implode(',', $this->services) . '</comment>'  . PHP_EOL,
                    'mysql,redis',
                );
            $message = $helper->ask($input, $output, $question);
            $services = explode(',', $message);
        }

        $this->buildDockerCompose($services);
        $this->replaceEnvVariables($services);
        $this->configurePhpUnit();

        if ($input->getOption('devcontainer')) {
            $this->installDevContainer();
        }

        $output->writeln("<info>Sail scaffolding installed successfully.</info>");

        $this->prepareInstallation($services, $output);

        return self::SUCCESS;
    }

    /**
     * Build the Docker Compose file.
     *
     * @param  array  $services
     * @return void
     */
    protected function buildDockerCompose(array $services)
    {
        $depends = collect($services)
            ->map(function ($service) {
                return "            - {$service}";
            })->whenNotEmpty(function ($collection) {
                return $collection->prepend('depends_on:');
            })->implode(PHP_EOL);

        $stubs = rtrim(collect($services)->map(function ($service) {
            return file_get_contents(__DIR__ . "/../stubs/{$service}.stub");
        })->implode(PHP_EOL));

        $volumes = collect($services)
            ->filter(function ($service) {
                return in_array($service, $this->services);
            })->map(function ($service) {
                return "    sail-{$service}:\n        driver: local";
            })->whenNotEmpty(function ($collection) {
                return $collection->prepend('volumes:');
            })->implode(PHP_EOL);

        $dockerCompose = file_get_contents(__DIR__ . '/../stubs/docker-compose.stub');

        $dockerCompose = str_replace('{{depends}}', empty($depends) ? '' : '        '.$depends, $dockerCompose);
        $dockerCompose = str_replace('{{services}}', $stubs, $dockerCompose);
        $dockerCompose = str_replace('{{volumes}}', $volumes, $dockerCompose);


        // Remove empty lines...
        $dockerCompose = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $dockerCompose);

        file_put_contents(base_path('docker-compose.yml'), $dockerCompose);
    }

    /**
     * Replace the Host environment variables in the app's .env file.
     *
     * @param  array  $services
     * @return void
     */
    protected function replaceEnvVariables(array $services)
    {
        $environment = file_get_contents(base_path('.env'));

        if (in_array('pgsql', $services)) {
            $environment = str_replace('DB_DRIVER=mysql', "DB_DRIVER=pgsql", $environment);
            $environment = str_replace('DB_HOST=127.0.0.1', "DB_HOST=pgsql", $environment);
            $environment = str_replace('DB_PORT=3306', "DB_PORT=5432", $environment);
        } elseif (in_array('mariadb', $services)) {
            $environment = str_replace('DB_HOST=127.0.0.1', "DB_HOST=mariadb", $environment);
        } else {
            $environment = str_replace('DB_HOST=127.0.0.1', "DB_HOST=mysql", $environment);
        }

        $environment = str_replace('DB_USERNAME=root', "DB_USERNAME=sail", $environment);
        $environment = preg_replace("/DB_PASSWORD=(.*)/", "DB_PASSWORD=password", $environment);

        $environment = str_replace('MEMCACHED_HOST=127.0.0.1', 'MEMCACHED_HOST=memcached', $environment);
        $environment = str_replace('REDIS_HOST=127.0.0.1', 'REDIS_HOST=redis', $environment);

        if (in_array('meilisearch', $services)) {
            $environment .= "\nSCOUT_DRIVER=meilisearch";
            $environment .= "\nMEILISEARCH_HOST=http://meilisearch:7700\n";
        }

        file_put_contents(base_path('.env'), $environment);
    }

    /**
     * Configure PHPUnit to use the dedicated testing database.
     *
     * @return void
     */
    protected function configurePhpUnit()
    {
        if (! file_exists($path = base_path('phpunit.xml'))) {
            $path = base_path('phpunit.xml.dist');
        }

        $phpunit = file_get_contents($path);

        $phpunit = preg_replace('/^.*DB_CONNECTION.*\n/m', '', $phpunit);
        $phpunit = str_replace('<!-- <env name="DB_DATABASE" value=":memory:"/> -->', '<env name="DB_DATABASE" value="testing"/>', $phpunit);

        file_put_contents(base_path('phpunit.xml'), $phpunit);
    }

    /**
     * Prepare the installation by pulling and building any necessary images.
     *
     * @param  array  $services
     * @param OutputInterface $output
     * @return void
     */
    protected function prepareInstallation($services, OutputInterface $output)
    {
        // Ensure docker is installed...
        if ($this->runCommands(['docker info > /dev/null 2>&1'], $output) !== 0) {
            return;
        }

        if (count($services) > 0) {
            $status = $this->runCommands([
                './vendor/bin/sail pull '.implode(' ', $services),
            ], $output);

            if ($status === 0) {
                $output->writeln('<info>Sail images installed successfully.</info>');
            }
        }

        $status = $this->runCommands([
            './vendor/bin/sail build',
        ], $output);

        if ($status === 0) {
            $output->writeln('<info>Sail build successful.</info>');
        }
    }

    /**
     * Install the devcontainer.json configuration file.
     *
     * @return void
     */
    protected function installDevContainer()
    {
        if (! is_dir(base_path('.devcontainer'))) {
            mkdir(base_path('.devcontainer'), 0755, true);
        }

        file_put_contents(
            base_path('.devcontainer/devcontainer.json'),
            file_get_contents(__DIR__.'/../stubs/devcontainer.stub')
        );

        $environment = file_get_contents(base_path('.env'));

        $environment .= "\nWWWGROUP=1000";
        $environment .= "\nWWWUSER=1000\n";

        file_put_contents(base_path('.env'), $environment);
    }

    /**
     * Run the given commands.
     *
     * @param  array  $commands
     * @param OutputInterface $output
     * @return int
     */
    protected function runCommands($commands, OutputInterface $output)
    {
        $process = Process::fromShellCommandline(implode(' && ', $commands), null, null, null, null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            try {
                $process->setTty(true);
            } catch (RuntimeException $e) {
                $output->writeln('  <bg=yellow;fg=black> WARN </> '.$e->getMessage().PHP_EOL);
            }
        }

        return $process->run(function ($type, $line) use ($output) {
            $output->write('    '.$line);
        });
    }


}