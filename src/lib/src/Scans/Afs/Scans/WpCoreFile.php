<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Exceptions;
use FernleafSystems\Wordpress\Services\Services;

class WpCoreFile extends BaseScan {

	/**
	 * @throws Exceptions\WpCoreFileChecksumFailException
	 * @throws Exceptions\WpCoreFileMissingException
	 */
	public function scan() :bool {
		$valid = false;

		$WPH = Services::CoreFileHashes();
		if ( $WPH->isCoreFile( $this->pathFull ) && !$this->isExcluded() ) {
			if ( !Services::WpFs()->isFile( $this->pathFull ) ) {
				if ( !$this->isExcludedMissing() ) {
					throw new Exceptions\WpCoreFileMissingException( $this->pathFull );
				}
			}
			elseif ( !$WPH->isCoreFileHashValid( $this->pathFull ) ) {
				throw new Exceptions\WpCoreFileChecksumFailException( $this->pathFull );
			}
			$valid = true;
		}

		return $valid;
	}

	protected function isExcluded() :bool {
		$exclusionsRegex = $this->getScanFileExclusions();
		return !empty( $exclusionsRegex ) && preg_match( $exclusionsRegex, $this->pathFragment );
	}

	private function isExcludedMissing() :bool {
		$exclusionsRegex = $this->getScanExclusionsForMissingItems();
		return !empty( $exclusionsRegex ) && preg_match( $exclusionsRegex, $this->pathFragment );
	}

	private function getScanFileExclusions() :string {
		$pattern = '';

		$exclusions = $this->opts()->getDef( 'wcf_exclusions' );
		// Flywheel specific mods
		if ( defined( 'FLYWHEEL_PLUGIN_DIR' ) ) {
			$exclusions[] = 'wp-settings.php';
			$exclusions[] = 'wp-admin/includes/upgrade.php';
		}

		if ( !empty( $exclusions ) ) {
			$quoted = array_map(
				function ( $exclusion ) {
					return preg_quote( $exclusion, '#' );
				},
				$exclusions
			);
			$pattern = '#('.implode( '|', $quoted ).')#i';
		}
		return $pattern;
	}

	/**
	 * Builds a regex-ready pattern for matching file names to exclude from scan if they're missing
	 */
	private function getScanExclusionsForMissingItems() :string {
		$pattern = '';
		$exclusions = $this->opts()->getDef( 'wcf_exclusions_missing_only' );
		if ( !empty( $exclusions ) ) {
			$quoted = array_map(
				function ( $exclusion ) {
					return preg_quote( $exclusion, '#' );
				},
				$exclusions
			);
			$pattern = '#('.implode( '|', $quoted ).')#i';
		}
		return $pattern;
	}
}