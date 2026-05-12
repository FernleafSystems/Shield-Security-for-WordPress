<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use Symfony\Component\Filesystem\Path;

class SourceSetupCacheCoordinator {

	private const STATE_SCHEMA_VERSION = 1;
	private const STATE_DIR_RELATIVE_PATH = 'tmp/.source-setup-cache';
	private const STATE_FILE_NAME = 'state.v1.json';
	private const NODE_IMAGE_TAG = 'node:20.10';
	private const NODE_MODULES_VOLUME_PREFIX = 'shield-source-node-modules-';

	public function getNodeImageTag() :string {
		return self::NODE_IMAGE_TAG;
	}

	public function getNodeModulesVolumeName( string $rootDir ) :string {
		$hash = \substr( \md5( Path::normalize( $rootDir ) ), 0, 10 );
		return self::NODE_MODULES_VOLUME_PREFIX.$hash;
	}

	public function clearState( string $rootDir ) :void {
		$statePath = $this->buildStatePath( $rootDir );
		if ( \is_file( $statePath ) ) {
			@unlink( $statePath );
		}
	}

	/**
	 * @return array{
	 *   needs_composer_install:bool,
	 *   needs_build_config:bool,
	 *   needs_npm_install:bool,
	 *   needs_npm_build:bool,
	 *   node_modules_volume:string,
	 *   fingerprints:array{
	 *     composer:string,
	 *     build_config:string,
	 *     node_deps:string,
	 *     asset_inputs:string
	 *   }
	 * }
	 */
	public function evaluateRuntimeSetup( string $rootDir, string $phpVersion, bool $refreshSetup = false ) :array {
		$state = $refreshSetup ? [] : $this->loadState( $rootDir );
		$fingerprints = $this->computeRuntimeFingerprints( $rootDir, $phpVersion );

		$needsComposerInstall = $refreshSetup
								|| !\is_file( Path::join( $rootDir, 'vendor', 'autoload.php' ) )
								|| ( $state[ 'composer_fingerprint' ] ?? '' ) !== $fingerprints[ 'composer' ];

		$needsBuildConfig = $refreshSetup
							|| !\is_file( Path::join( $rootDir, 'plugin.json' ) )
							|| ( $state[ 'build_config_fingerprint' ] ?? '' ) !== $fingerprints[ 'build_config' ];

		$needsNpmInstall = $refreshSetup
						   || ( $state[ 'node_deps_fingerprint' ] ?? '' ) !== $fingerprints[ 'node_deps' ];

		$distFiles = $this->collectDistFiles( $rootDir );
		$hasDistFiles = !empty( $distFiles );

		$recordedDistFiles = \is_array( $state[ 'dist_files' ] ?? null ) ? $state[ 'dist_files' ] : [];
		$hasRecordedDistFiles = !empty( $recordedDistFiles );

		$missingRecordedDistFile = false;
		foreach ( $recordedDistFiles as $distFile ) {
			if ( !\is_string( $distFile ) ) {
				continue;
			}
			if ( !\is_file( Path::join( $rootDir, $distFile ) ) ) {
				$missingRecordedDistFile = true;
				break;
			}
		}

		$needsNpmBuild = $refreshSetup
						 || $needsNpmInstall
						 || !$hasDistFiles
						 || !$hasRecordedDistFiles
						 || $missingRecordedDistFile
						 || ( $state[ 'asset_inputs_fingerprint' ] ?? '' ) !== $fingerprints[ 'asset_inputs' ];

		return [
			'needs_composer_install' => $needsComposerInstall,
			'needs_build_config' => $needsBuildConfig,
			'needs_npm_install' => $needsNpmInstall,
			'needs_npm_build' => $needsNpmBuild,
			'node_modules_volume' => $this->getNodeModulesVolumeName( $rootDir ),
			'fingerprints' => $fingerprints,
		];
	}

	/**
	 * @return array{
	 *   needs_build_config:bool,
	 *   fingerprint:string
	 * }
	 */
	public function evaluateAnalyzeSetup( string $rootDir, bool $refreshSetup = false ) :array {
		$state = $refreshSetup ? [] : $this->loadState( $rootDir );
		$fingerprint = $this->computeBuildConfigFingerprint( $rootDir );

		$needsBuildConfig = $refreshSetup
							|| !\is_file( Path::join( $rootDir, 'plugin.json' ) )
							|| ( $state[ 'build_config_fingerprint' ] ?? '' ) !== $fingerprint;

		return [
			'needs_build_config' => $needsBuildConfig,
			'fingerprint' => $fingerprint,
		];
	}

	/**
	 * @param array{
	 *   composer:string,
	 *   build_config:string,
	 *   node_deps:string,
	 *   asset_inputs:string
	 * } $fingerprints
	 */
	public function persistRuntimeSetupState( string $rootDir, array $fingerprints ) :void {
		$state = $this->loadState( $rootDir );
		$state[ 'schema_version' ] = self::STATE_SCHEMA_VERSION;
		$state[ 'composer_fingerprint' ] = $fingerprints[ 'composer' ];
		$state[ 'build_config_fingerprint' ] = $fingerprints[ 'build_config' ];
		$state[ 'node_deps_fingerprint' ] = $fingerprints[ 'node_deps' ];
		$state[ 'asset_inputs_fingerprint' ] = $fingerprints[ 'asset_inputs' ];
		$state[ 'dist_files' ] = $this->collectDistFiles( $rootDir );
		$state[ 'updated_at_unix' ] = \time();

		$this->writeState( $rootDir, $state );
	}

	public function persistBuildConfigState( string $rootDir, string $buildConfigFingerprint ) :void {
		$state = $this->loadState( $rootDir );
		$state[ 'schema_version' ] = self::STATE_SCHEMA_VERSION;
		$state[ 'build_config_fingerprint' ] = $buildConfigFingerprint;
		$state[ 'updated_at_unix' ] = \time();

		$this->writeState( $rootDir, $state );
	}

	/**
	 * @return array{composer:string,build_config:string,node_deps:string,asset_inputs:string}
	 */
	private function computeRuntimeFingerprints( string $rootDir, string $phpVersion ) :array {
		$composerFingerprint = $this->computeFingerprint(
			$rootDir,
			[
				'composer.json',
				'composer.lock',
			],
			[
				'php_version='.$phpVersion,
			]
		);

		$buildConfigFingerprint = $this->computeBuildConfigFingerprint( $rootDir );

		$nodeDepsFingerprint = $this->computeFingerprint(
			$rootDir,
			[
				'package.json',
				'package-lock.json',
			],
			[
				'node_image='.self::NODE_IMAGE_TAG,
			]
		);

		$assetInputsFingerprint = $this->computeFingerprint(
			$rootDir,
			[
				'assets/js',
				'assets/css',
				'assets/images',
				'webpack.config.js',
				'postcss.config.js',
			],
			[
				'node_deps_fingerprint='.$nodeDepsFingerprint,
			]
		);

		return [
			'composer' => $composerFingerprint,
			'build_config' => $buildConfigFingerprint,
			'node_deps' => $nodeDepsFingerprint,
			'asset_inputs' => $assetInputsFingerprint,
		];
	}

	private function computeBuildConfigFingerprint( string $rootDir ) :string {
		return $this->computeFingerprint(
			$rootDir,
			[
				'plugin-spec',
				'bin/build-config.php',
			]
		);
	}

	/**
	 * @param string[] $relativePaths
	 * @param string[] $extraValues
	 */
	private function computeFingerprint( string $rootDir, array $relativePaths, array $extraValues = [] ) :string {
		$entries = [];
		foreach ( $relativePaths as $relativePath ) {
			$normalizedRelativePath = $this->normalizePath( $relativePath );
			$absolutePath = Path::join( $rootDir, $relativePath );

			if ( \is_file( $absolutePath ) ) {
				$entries[] = 'file:'.$normalizedRelativePath.':'.$this->hashFile( $absolutePath );
				continue;
			}
			if ( \is_dir( $absolutePath ) ) {
				$entries = \array_merge( $entries, $this->collectDirectoryEntries( $rootDir, $absolutePath ) );
				continue;
			}

			$entries[] = 'missing:'.$normalizedRelativePath;
		}

		foreach ( $extraValues as $extraValue ) {
			$entries[] = 'extra:'.$extraValue;
		}

		\sort( $entries );
		return \hash( 'sha256', \implode( "\n", $entries ) );
	}

	/**
	 * @return string[]
	 */
	private function collectDirectoryEntries( string $rootDir, string $directoryPath ) :array {
		$entries = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $directoryPath, \FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $fileInfo ) {
			if ( !$fileInfo->isFile() ) {
				continue;
			}
			$filePath = $fileInfo->getPathname();
			$relativePath = $this->normalizePath( Path::makeRelative( $filePath, $rootDir ) );
			$entries[] = 'file:'.$relativePath.':'.$this->hashFile( $filePath );
		}

		return $entries;
	}

	private function hashFile( string $filePath ) :string {
		$hash = @\hash_file( 'sha256', $filePath );
		return \is_string( $hash ) ? $hash : 'unreadable';
	}

	/**
	 * @return string[]
	 */
	private function collectDistFiles( string $rootDir ) :array {
		$distDir = Path::join( $rootDir, 'assets', 'dist' );
		if ( !\is_dir( $distDir ) ) {
			return [];
		}

		$files = [];
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $distDir, \FilesystemIterator::SKIP_DOTS )
		);
		foreach ( $iterator as $fileInfo ) {
			if ( $fileInfo->isFile() ) {
				$files[] = $this->normalizePath( Path::makeRelative( $fileInfo->getPathname(), $rootDir ) );
			}
		}

		\sort( $files );
		return $files;
	}

	/**
	 * @return array<string,mixed>
	 */
	private function loadState( string $rootDir ) :array {
		$statePath = $this->buildStatePath( $rootDir );
		if ( !\is_file( $statePath ) ) {
			return [];
		}

		$content = @\file_get_contents( $statePath );
		if ( !\is_string( $content ) || $content === '' ) {
			return [];
		}

		$decoded = \json_decode( $content, true );
		if ( !\is_array( $decoded ) ) {
			return [];
		}
		if ( ( $decoded[ 'schema_version' ] ?? null ) !== self::STATE_SCHEMA_VERSION ) {
			return [];
		}

		return $decoded;
	}

	/**
	 * @param array<string,mixed> $state
	 */
	private function writeState( string $rootDir, array $state ) :void {
		$statePath = $this->buildStatePath( $rootDir );
		$stateDir = \dirname( $statePath );
		if ( !\is_dir( $stateDir ) ) {
			if ( !@mkdir( $stateDir, 0777, true ) && !\is_dir( $stateDir ) ) {
				return;
			}
		}

		$json = \json_encode( $state, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES );
		if ( !\is_string( $json ) ) {
			return;
		}

		@\file_put_contents( $statePath, $json."\n" );
	}

	private function buildStatePath( string $rootDir ) :string {
		return Path::join( $rootDir, self::STATE_DIR_RELATIVE_PATH, self::STATE_FILE_NAME );
	}

	private function normalizePath( string $path ) :string {
		return \str_replace( '\\', '/', $path );
	}
}
