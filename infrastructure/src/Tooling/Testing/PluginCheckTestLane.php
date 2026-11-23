<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use Symfony\Component\Filesystem\Path;

/**
 * Runs WordPress.org Plugin Check against a release-shaped Shield package in a disposable
 * Docker site. The Docker kit (compose file, container script, report) lives in tests/plugin-check.
 */
class PluginCheckTestLane {

	public const EXIT_SETUP_FAILURE = 2;

	private const PLUGIN_SLUG = 'wp-simple-firewall';

	private const COMPOSE_PROJECT = 'shield-plugin-check';

	private const COMPOSE_FILE = 'tests/plugin-check/docker-compose.yml';

	private PackagePathResolver $packagePathResolver;

	private TestingEnvironmentResolver $environmentResolver;

	private DockerComposeExecutor $composeExecutor;

	public function __construct(
		?PackagePathResolver $packagePathResolver = null,
		?TestingEnvironmentResolver $environmentResolver = null,
		?DockerComposeExecutor $composeExecutor = null
	) {
		$this->packagePathResolver = $packagePathResolver ?? new PackagePathResolver();
		$this->environmentResolver = $environmentResolver ?? new TestingEnvironmentResolver();
		$this->composeExecutor = $composeExecutor ?? new DockerComposeExecutor();
	}

	public function run( string $rootDir, ?string $packagePath, string $mode, bool $updateBaseline ) :int {
		if ( !\in_array( $mode, [ 'new', 'update' ], true ) ) {
			throw new \InvalidArgumentException( 'Plugin Check mode must be "new" or "update".' );
		}

		$this->environmentResolver->assertDockerReady( $rootDir );
		$resolvedPackagePath = $this->packagePathResolver->resolve( $rootDir, $packagePath );

		$outputDir = Path::join( $rootDir, 'tmp', 'plugin-check' );
		if ( !\is_dir( $outputDir ) && !\mkdir( $outputDir, 0777, true ) && !\is_dir( $outputDir ) ) {
			throw new \RuntimeException( 'Unable to create Plugin Check output directory: '.$outputDir );
		}

		$env = [
			'COMPOSE_PROJECT_NAME'         => self::COMPOSE_PROJECT,
			'COMPOSE_PROGRESS'             => 'quiet',
			'PLUGIN_CHECK_SLUG'            => self::PLUGIN_SLUG,
			'PLUGIN_CHECK_PACKAGE_DIR'     => $resolvedPackagePath,
			'PLUGIN_CHECK_OUTPUT_DIR'      => $outputDir,
			'PLUGIN_CHECK_MODE'            => $mode,
			'PLUGIN_CHECK_UPDATE_BASELINE' => $updateBaseline ? '1' : '0',
		];
		$composeFiles = [ self::COMPOSE_FILE ];

		echo 'Mode: plugin-check ('.$mode.')'.\PHP_EOL;
		echo '   Using package path: '.$resolvedPackagePath.\PHP_EOL;

		try {
			// Clear anything left behind by an interrupted run.
			$this->composeExecutor->runIgnoringFailure( $rootDir, $composeFiles, [ 'down', '-v', '--remove-orphans' ], $env, false );
			$exitCode = $this->composeExecutor->run(
				$rootDir,
				$composeFiles,
				[ 'run', '--rm', '-T', 'wp-cli', 'sh', '/plugin-check/check.sh' ],
				$env
			);
		}
		finally {
			$this->composeExecutor->runIgnoringFailure( $rootDir, $composeFiles, [ 'down', '-v', '--remove-orphans' ], $env, false );
		}

		if ( !\in_array( $exitCode, [ 0, 1 ], true ) ) {
			return self::EXIT_SETUP_FAILURE;
		}
		echo 'Plugin Check report: '.Path::join( $outputDir, 'findings.json' ).\PHP_EOL;
		return $exitCode;
	}
}
