<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use FernleafSystems\ShieldPlatform\Tooling\Process\BashCommandResolver;

class LocalWpTestsInstallerCommandBuilder {

	private BashCommandResolver $bashCommandResolver;

	public function __construct( ?BashCommandResolver $bashCommandResolver = null ) {
		$this->bashCommandResolver = $bashCommandResolver ?? new BashCommandResolver();
	}

	/**
	 * @return string[]
	 */
	public function build(
		string $dbName,
		string $dbUser,
		string $dbPass,
		string $dbHost,
		string $wpVersion,
		bool $skipDbCreate
	) :array {
		if ( \PHP_OS_FAMILY === 'Windows' ) {
			return $this->buildWindows( $dbName, $dbUser, $dbPass, $dbHost, $wpVersion );
		}

		return $this->buildNonWindows(
			$dbName,
			$dbUser,
			$dbPass,
			$dbHost,
			$wpVersion,
			$skipDbCreate
		);
	}

	/**
	 * @return string[]
	 */
	public function buildWindows(
		string $dbName,
		string $dbUser,
		string $dbPass,
		string $dbHost,
		string $wpVersion
	) :array {
		return [
			'powershell',
			'-NoProfile',
			'-ExecutionPolicy',
			'Bypass',
			'-File',
			'./bin/install-wp-tests.ps1',
			'-DB_NAME',
			$dbName,
			'-DB_USER',
			$dbUser,
			'-DB_PASS',
			$dbPass,
			'-DB_HOST',
			$dbHost,
			'-WP_VERSION',
			$wpVersion,
		];
	}

	/**
	 * @return string[]
	 */
	public function buildNonWindows(
		string $dbName,
		string $dbUser,
		string $dbPass,
		string $dbHost,
		string $wpVersion,
		bool $skipDbCreate
	) :array {
		return [
			$this->bashCommandResolver->resolve(),
			'./bin/install-wp-tests.sh',
			$dbName,
			$dbUser,
			$dbPass,
			$dbHost,
			$wpVersion,
			$skipDbCreate ? 'true' : 'false',
		];
	}
}
