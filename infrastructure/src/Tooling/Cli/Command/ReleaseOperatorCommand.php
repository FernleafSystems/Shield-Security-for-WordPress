<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Cli\Command;

use FernleafSystems\ShieldPlatform\Tooling\Process\ProcessRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Filesystem\Path;

class ReleaseOperatorCommand extends Command {

	private const ACTION_PACKAGE_SVN = 'package-svn';
	private const ACTION_PREPARE_RELEASE = 'prepare-release';
	private const ACTION_BUILD_ZIP = 'build-zip';
	private const ACTIONS = [
		self::ACTION_PACKAGE_SVN,
		self::ACTION_PREPARE_RELEASE,
		self::ACTION_BUILD_ZIP,
	];
	private const ACTION_CHOICES = [
		'Package for SVN' => self::ACTION_PACKAGE_SVN,
		'Prepare release' => self::ACTION_PREPARE_RELEASE,
		'Build ZIP' => self::ACTION_BUILD_ZIP,
		'Cancel' => 'cancel',
	];

	private string $projectRoot;

	private ?string $fixedAction;

	private ProcessRunner $processRunner;

	public function __construct( string $name, ?string $fixedAction, string $projectRoot, ?ProcessRunner $processRunner = null ) {
		if ( $fixedAction !== null && !\in_array( $fixedAction, self::ACTIONS, true ) ) {
			throw new \InvalidArgumentException( 'Unsupported release operation: '.$fixedAction );
		}

		$this->fixedAction = $fixedAction;
		$this->projectRoot = $this->canonicalDirectory( $projectRoot, 'The project root must be an existing directory.' );
		$this->processRunner = $processRunner ?? new ProcessRunner();
		parent::__construct( $name );
	}

	protected function configure() :void {
		$this->setDescription( $this->fixedAction === null
			? 'Interactively run a Shield release operation.'
			: 'Interactively run the Shield '.$this->fixedAction.' release operation.'
		);
	}

	protected function execute( InputInterface $input, OutputInterface $output ) :int {
		$statePath = $this->statePath();

		try {
			$questionHelper = $this->getHelper( 'question' );
			\assert( $questionHelper instanceof QuestionHelper );
			$action = $this->fixedAction ?? $this->askForAction( $input, $output, $questionHelper );
			if ( $action === null ) {
				$output->writeln( 'Release operation cancelled. State was not written: '.$statePath );
				return Command::SUCCESS;
			}

			$inputs = $this->askForInputs( $action, $input, $output, $questionHelper );
			$command = $this->buildCommand( $action, $inputs );
			$output->writeln( 'Command preview: '.\json_encode( $command, \JSON_UNESCAPED_SLASHES ) );

			if ( !$questionHelper->ask( $input, $output, new ConfirmationQuestion( 'Run this command? [y/N] ', false ) ) ) {
				$output->writeln( 'Release operation declined. State was not written: '.$statePath );
				return Command::SUCCESS;
			}

			if ( !$this->writeState( $statePath, $action, $inputs, $command ) ) {
				$output->writeln( '<error>Unable to write release operator state: '.$statePath.'</error>' );
				return Command::FAILURE;
			}

			$process = $this->processRunner->run(
				$command,
				$this->projectRoot,
				static function ( string $type, string $buffer ) use ( $output ) :void {
					$output->write( $buffer );
				}
			);
			$exitCode = $process->getExitCode();
			$output->writeln( 'Release operator state: '.$statePath );
			if ( $exitCode === 0 ) {
				$output->writeln( 'Release operation completed successfully.' );
				return Command::SUCCESS;
			}

			$output->writeln( '<error>Release operation failed with exit code '.( $exitCode ?? 'unknown' ).'.</error>' );
			return $exitCode ?? Command::FAILURE;
		}
		catch ( \Throwable $throwable ) {
			$output->writeln( '<error>Release operation failed: '.$throwable->getMessage().'</error>' );
			$output->writeln( 'Release operator state: '.$statePath );
			return Command::FAILURE;
		}
	}

	private function askForAction( InputInterface $input, OutputInterface $output, QuestionHelper $questionHelper ) :?string {
		$selection = (string)$questionHelper->ask(
			$input,
			$output,
			new ChoiceQuestion( 'Select a release operation', self::ACTION_CHOICES )
		);
		$action = self::ACTION_CHOICES[ $selection ] ?? $selection;

		return $action === 'cancel' ? null : (string)$action;
	}

	/**
	 * @return array<string,string|int>
	 */
	private function askForInputs( string $action, InputInterface $input, OutputInterface $output, QuestionHelper $questionHelper ) :array {
		if ( $action === self::ACTION_PACKAGE_SVN ) {
			$question = new Question( 'Existing SVN target directory: ' );
			$question->setValidator( function ( $target ) :string {
				return $this->externalPackageTarget( (string)$target );
			} );
			return [ 'target' => $questionHelper->ask( $input, $output, $question ) ];
		}

		if ( $action === self::ACTION_PREPARE_RELEASE ) {
			return [
				'version' => (string)$questionHelper->ask( $input, $output, new Question( 'Release version: ', $this->configuredVersion() ) ),
				'release_timestamp' => (int)$questionHelper->ask( $input, $output, new Question( 'Release timestamp: ', \time() ) ),
				'build' => (string)$questionHelper->ask( $input, $output, new Question( 'Build: ', 'auto' ) ),
			];
		}

		if ( $action === self::ACTION_BUILD_ZIP ) {
			return [];
		}

		throw new \LogicException( 'Unsupported release operation: '.$action );
	}

	/**
	 * @param array<string,string|int> $inputs
	 * @return string[]
	 */
	private function buildCommand( string $action, array $inputs ) :array {
		if ( $action === self::ACTION_PACKAGE_SVN ) {
			return [ 'composer', 'package-plugin', '--', '--output='.$inputs[ 'target' ] ];
		}

		if ( $action === self::ACTION_PREPARE_RELEASE ) {
			return [
				\PHP_BINARY,
				'bin/prepare-release.php',
				'--version='.$inputs[ 'version' ],
				'--release-timestamp='.$inputs[ 'release_timestamp' ],
				'--build='.$inputs[ 'build' ],
			];
		}

		if ( $action === self::ACTION_BUILD_ZIP ) {
			return [ 'composer', 'build-zip' ];
		}

		throw new \LogicException( 'Unsupported release operation: '.$action );
	}

	/**
	 * @param array<string,string|int> $inputs
	 * @param string[] $command
	 */
	private function writeState( string $statePath, string $action, array $inputs, array $command ) :bool {
		$stateDirectory = \dirname( $statePath );
		if ( !\is_dir( $stateDirectory ) && !\mkdir( $stateDirectory, 0777, true ) && !\is_dir( $stateDirectory ) ) {
			return false;
		}

		try {
			$state = \json_encode(
				[ 'action' => $action, 'inputs' => $inputs, 'command' => $command ],
				\JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR
		);
		}
		catch ( \JsonException $exception ) {
			return false;
		}

		return \file_put_contents( $statePath, $state."\n" ) !== false;
	}

	private function configuredVersion() :string {
		$properties = \file_get_contents( $this->projectRoot.'/plugin-spec/01_properties.json' );
		if ( $properties === false ) {
			throw new \RuntimeException( 'Unable to read plugin version configuration.' );
		}
		$decoded = \json_decode( $properties, true, 512, \JSON_THROW_ON_ERROR );
		if ( !\is_array( $decoded ) || !isset( $decoded[ 'version' ] ) ) {
			throw new \RuntimeException( 'Plugin version configuration is invalid.' );
		}

		return (string)$decoded[ 'version' ];
	}

	private function statePath() :string {
		return $this->projectRoot.'/tmp/operator-state.json';
	}

	private function externalPackageTarget( string $target ) :string {
		$target = \trim( $target );
		if ( !Path::isAbsolute( $target ) ) {
			$target = Path::join( $this->projectRoot, $target );
		}

		$target = $this->canonicalDirectory( $target, 'The package target must be an existing directory.' );
		if ( Path::isBasePath( $this->projectRoot, $target ) ) {
			throw new \RuntimeException( 'The package target must be outside the project directory.' );
		}

		return $target;
	}

	private function canonicalDirectory( string $directory, string $errorMessage ) :string {
		$realPath = \realpath( $directory );
		if ( $realPath === false || !\is_dir( $realPath ) ) {
			throw new \RuntimeException( $errorMessage );
		}

		return Path::normalize( $realPath );
	}
}
