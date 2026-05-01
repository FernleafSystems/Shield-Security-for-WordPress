<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\PluginPackager;

use Symfony\Component\Filesystem\Path;

class PackagerConfigResolver {

	/**
	 * @return array{strauss_version:?string,strauss_fork_repo:?string,strauss_fork_branch:?string}
	 */
	public function resolve( string $projectRoot ) :array {
		$configValues = $this->readConfigFile( $projectRoot );

		$straussVersion = $this->envOrConfigValue( 'SHIELD_STRAUSS_VERSION', $configValues[ 'STRAUSS_VERSION' ] ?? null );
		$straussForkRepo = $this->envOrConfigValue( 'SHIELD_STRAUSS_FORK_REPO', $configValues[ 'STRAUSS_FORK_REPO' ] ?? null );
		$straussForkBranch = $this->envOrConfigValue( 'SHIELD_STRAUSS_FORK_BRANCH', $configValues[ 'STRAUSS_FORK_BRANCH' ] ?? null );

		$values = [
			'strauss_version' => $straussVersion !== null ? \ltrim( $straussVersion, 'v' ) : null,
			'strauss_fork_repo' => $straussForkRepo,
			'strauss_fork_branch' => $straussForkBranch,
		];

		if ( !\is_string( $values[ 'strauss_fork_repo' ] ) || $values[ 'strauss_fork_repo' ] === '' ) {
			$values[ 'strauss_fork_repo' ] = null;
			$values[ 'strauss_fork_branch' ] = null;
		}
		elseif ( !\is_string( $values[ 'strauss_fork_branch' ] ) || $values[ 'strauss_fork_branch' ] === '' ) {
			$values[ 'strauss_fork_branch' ] = 'develop';
		}

		return $values;
	}

	/**
	 * @return array<string,string>
	 */
	private function readConfigFile( string $projectRoot ) :array {
		$configPath = Path::normalize( Path::join( Path::normalize( $projectRoot ), '.github', 'config', 'packager.conf' ) );
		if ( !\is_file( $configPath ) ) {
			return [];
		}

		$lines = \file( $configPath, \FILE_IGNORE_NEW_LINES | \FILE_SKIP_EMPTY_LINES );
		if ( !\is_array( $lines ) ) {
			return [];
		}

		$values = [];
		foreach ( $lines as $line ) {
			$trimmed = \trim( $line );
			if ( $trimmed === '' || \strpos( $trimmed, '#' ) === 0 ) {
				continue;
			}

			$separatorPosition = \strpos( $trimmed, '=' );
			if ( $separatorPosition === false ) {
				continue;
			}

			$key = \trim( \substr( $trimmed, 0, $separatorPosition ) );
			$value = $this->normalizeValue( \substr( $trimmed, $separatorPosition + 1 ) );
			if ( $key !== '' && $value !== '' ) {
				$values[ $key ] = $value;
			}
		}

		return $values;
	}

	private function envOrConfigValue( string $envName, ?string $configValue ) :?string {
		$envValue = \getenv( $envName );
		if ( \is_string( $envValue ) && $envValue !== '' ) {
			return $this->normalizeValue( $envValue );
		}

		return $configValue !== null && $configValue !== '' ? $this->normalizeValue( $configValue ) : null;
	}

	private function normalizeValue( string $value ) :string {
		return \trim( $value, " \t\n\r\0\x0B\"'" );
	}
}
