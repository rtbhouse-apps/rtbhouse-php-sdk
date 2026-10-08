<?php
declare(strict_types=1);

/**
CLI for managing RTB House API tokens.

Usage:
    php vendor/bin/api-tokens <command> [options]

Commands:
    init-json         Initialize API token in JSON file storage.
    keep-alive-json   Refresh token's last activity timestamp from JSON file storage,
                      optionally rotating it if in the rotation window.

Note:
    Your API tokens can be created in the Clients Panel (https://panel.rtbhouse.com/user/api-tokens).

Examples:
    # Initialize token interactively (prompts for token):
    $ php vendor/bin/api-tokens init-json
    Paste your token: PASTE_YOUR_TOKEN_HERE

    # Initialize token via environment variable:
    $ php vendor/bin/api-tokens init-json <<< "$API_TOKEN"

    # Initialize token from a file:
    $ php vendor/bin/api-tokens init-json < token.txt

    # Initialize token in custom path:
    $ php vendor/bin/api-tokens init-json --path /custom/path/token.json

    # Keep alive:
    $ php vendor/bin/api-tokens keep-alive-json

    # Keep alive without auto-rotation:
    $ php vendor/bin/api-tokens keep-alive-json --skip-auto-rotate

    # Keep alive token stored in custom path:
    $ php vendor/bin/api-tokens keep-alive-json --path /custom/path/token.json
*/


namespace RTBHouse\ReportsApi\ApiTokens;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

final class ConsoleApplication extends Application
{
    public function __construct()
    {
        parent::__construct('rtbhouse-php-sdk');

        $this->add(new InitJsonCommand());
        $this->add(new KeepAliveJsonCommand());
    }
}


#[AsCommand(
    name: 'init-json',
    description: 'Initialize JSON file API token storage from a token.'
)]
final class InitJsonCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setHelp(<<<'HELP'
Initialize API token in JSON file storage.
Reads the token from stdin if input is piped, otherwise prompts interactively.

NOTE: First API token can be created in the Clients Panel under Account > API Tokens section.

  <info>%command.full_name%</info>                          prompt for the token
  <info>%command.full_name% < token.txt</info>              read the token from a file
  <info>%command.full_name% <<< "$API_TOKEN"</info>         read the token from a variable
  <info>%command.full_name% --path=/tmp/token.json</info>   use a custom storage path
HELP
            )
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Path to the token JSON file.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $path = readPath($input);
            $token = $this->readToken($input, $output);

            $storage = new JsonFileApiTokenStorage($path);
            $manager = new ApiTokenManager($storage);

            $manager->configure($token);
        } catch (\Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $output->writeln('<info>API token configured.</info>');

        return Command::SUCCESS;
    }

    private function readToken(InputInterface $input, OutputInterface $output): string
    {
        $stream = ($input instanceof StreamableInputInterface ? $input->getStream() : null) ?? STDIN;

        if (!stream_isatty($stream)) {
            $token = stream_get_contents($stream);
            if ($token === false) {
                throw new \RuntimeException('Failed to read token from stdin.');
            }
        } else {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new Question('Paste your token: ');
            $question->setHidden(true);

            $token = (string) $helper->ask($input, $output, $question);
        }

        $token = trim($token);

        if ($token === '') {
            throw new \RuntimeException('Token cannot be empty.');
        }

        return $token;
    }
}

function readPath(InputInterface $input): ?string
{
    $path = $input->getOption('path');

    if ($path === null) {
        return null;
    }

    if (!is_string($path) || trim($path) === '') {
        throw new \InvalidArgumentException('The --path option requires a non-empty path.');
    }

    return $path;
}



#[AsCommand(
    name: 'keep-alive-json',
    description: 'Keep the JSON file API token alive, rotating it if needed.'
)]
final class KeepAliveJsonCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setHelp(<<<'HELP'
Refresh the last activity timestamp of the API token stored in the JSON file.
If the token is in the rotation window, it will be rotated and saved
unless --skip-auto-rotate is set.
Can also be used to verify that the token is valid.

  <info>%command.full_name%</info>                          keep alive, auto-rotate when due
  <info>%command.full_name% --skip-auto-rotate</info>       keep alive without rotating
  <info>%command.full_name% --path=/tmp/token.json</info>   use a custom storage path
HELP
            )
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Path to the token JSON file.')
            ->addOption(
                'skip-auto-rotate',
                null,
                InputOption::VALUE_NONE,
                'Do not rotate even if inside the rotation window.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $path = readPath($input);
            $skipAutoRotate = (bool) $input->getOption('skip-auto-rotate');

            $storage = new JsonFileApiTokenStorage($path);
            $manager = new ApiTokenManager($storage);

            $manager->keepAlive(autoRotate: !$skipAutoRotate);
        } catch (\Throwable $exception) {
            $output->writeln('<error>' . $exception->getMessage() . '</error>');

            return Command::FAILURE;
        }

        $output->writeln('<info>Token valid.</info>');

        return Command::SUCCESS;
    }
}
