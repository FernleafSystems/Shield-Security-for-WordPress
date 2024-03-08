<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Exceptions;
use FernleafSystems\Wordpress\Services\Services;

class WpCoreFile extends BaseScan {

	protected function canScan() :bool {
		return parent::canScan() && Services::CoreFileHashes()->isCoreFile( $this->pathFull );
	}

	/**
	 * @throws Exceptions\WpCoreFileChecksumFailException
	 * @throws Exceptions\WpCoreFileMissingException
	 */
	protected function runScan() :bool {
		if ( !Services::WpFs()->isAccessibleFile( $this->pathFull ) ) {
			if ( !$this->isExcludedMissing() ) {
				throw new Exceptions\WpCoreFileMissingException( $this->pathFull );
			}
		}
		elseif ( !Services::CoreFileHashes()->isCoreFileHashValid( $this->pathFull ) ) {
			throw new Exceptions\WpCoreFileChecksumFailException( $this->pathFull );
		}

		return true;
	}

	protected function isFileExcluded() :bool {
		$exclusionsRegex = $this->getScanFileExclusions();
		return !empty( $exclusionsRegex ) && \preg_match( $exclusionsRegex, $this->pathFragment );
	}

	private function isExcludedMissing() :bool {
		$exclusionsRegex = $this->getScanExclusionsForMissingItems();
		return !empty( $exclusionsRegex ) && \preg_match( $exclusionsRegex, $this->pathFragment );
	}

	private function getScanFileExclusions() :string {
		$pattern = '';

		$exclusions = self::con()->cfg->configuration->def( 'wcf_exclusions' );
		// Flywheel specific mods
		if ( \defined( 'FLYWHEEL_PLUGIN_DIR' ) ) {
			$exclusions[] = 'wp-settings.php';
			$exclusions[] = 'wp-admin/includes/upgrade.php';
		}

		if ( !empty( $exclusions ) ) {
			$quoted = \array_map(
				function ( $exclusion ) {
					return \preg_quote( $exclusion, '#' );
				},
				$exclusions
			);
			$pattern = '#('.\implode( '|', $quoted ).')#i';
		}
		return $pattern;
	}

	/**
	 * Builds a regex-ready pattern for matching file names to exclude from scan if they're missing
	 */
	private function getScanExclusionsForMissingItems() :string {
		$pattern = '';
		$exclusions = self::con()->cfg->configuration->def( 'wcf_exclusions_missing_only' );
		if ( !empty( $exclusions ) ) {
			$quoted = \array_map(
				function ( $exclusion ) {
					return \preg_quote( $exclusion, '#' );
				},
				$exclusions
			);
			$pattern = '#('.\implode( '|', $quoted ).')#i';
		}
		return $pattern;
	}
}