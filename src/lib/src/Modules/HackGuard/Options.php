<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Services\Services;

class Options extends Base\ShieldOptions {

	/**
	 * @return string[]
	 */
	public function getDbColumns_Scanner() {
		return $this->getDef( 'table_columns_scanner' );
	}

	/**
	 * @return string
	 */
	public function getDbTable_Scanner() {
		return $this->getCon()->prefixOption( $this->getDef( 'table_name_scanner' ) );
	}

	/**
	 * We do some WP Content dir replacement as there may be custom wp-content dir defines
	 * @return string[]
	 */
	public function getMalwareWhitelistPaths() {
		return array_map(
			function ( $sFragment ) {
				return str_replace(
					wp_normalize_path( ABSPATH.'wp-content' ),
					rtrim( wp_normalize_path( WP_CONTENT_DIR ), '/' ),
					wp_normalize_path( path_join( ABSPATH, ltrim( $sFragment, '/' ) ) )
				);
			},
			$this->getDef( 'malware_whitelist_paths' )
		);
	}

	/**
	 * @return string[]
	 * @throws \Exception
	 */
	public function getMalSignaturesSimple() {
		return $this->getMalSignatures( 'malsigs_simple.txt', $this->getDef( 'url_mal_sigs_simple' ) );
	}

	/**
	 * @return int
	 */
	public function getFileScanLimit() {
		return 300; // TODO: Def
	}

	/**
	 * @return string[]
	 * @throws \Exception
	 */
	public function getMalSignaturesRegex() {
		return $this->getMalSignatures( 'malsigs_regex.txt', $this->getDef( 'url_mal_sigs_regex' ) );
	}

	/**
	 * @param string $sFilename
	 * @param string $sUrl
	 * @return string[]
	 * @throws \Exception
	 */
	public function getMalSignatures( $sFilename, $sUrl ) {
		$oWpFs = Services::WpFs();
		$sFile = $this->getCon()->getPluginCachePath( $sFilename );
		if ( $oWpFs->exists( $sFile ) ) {
			$aSigs = explode( "\n", $oWpFs->getFileContent( $sFile, true ) );
		}
		else {
			$aSigs = array_filter(
				array_map( 'trim',
					explode( "\n", Services::HttpRequest()->getContent( $sUrl ) )
				),
				function ( $sLine ) {
					return ( ( strpos( $sLine, '#' ) !== 0 ) && strlen( $sLine ) > 0 );
				}
			);

			if ( !empty( $aSigs ) ) {
				$oWpFs->putFileContent( $sFile, implode( "\n", $aSigs ), true );
			}
		}
		return $aSigs;
	}

	/**
	 * @return string
	 */
	public function getWcfFileExclusions() {
		$sPattern = null;

		$aExclusions = $this->getMod()->getDef( 'wcf_exclusions' );
		$aExclusions = is_array( $aExclusions ) ? $aExclusions : [];
		// Flywheel specific mods
		if ( defined( 'FLYWHEEL_PLUGIN_DIR' ) ) {
			$aExclusions[] = 'wp-settings.php';
			$aExclusions[] = 'wp-admin/includes/upgrade.php';
		}

		if ( is_array( $aExclusions ) && !empty( $aExclusions ) ) {
			$aQuoted = array_map(
				function ( $sExcl ) {
					return preg_quote( $sExcl, '#' );
				},
				$aExclusions
			);
			$sPattern = '#('.implode( '|', $aQuoted ).')#i';
		}
		return $sPattern;
	}

	/**
	 * Builds a regex-ready pattern for matching file names to exclude from scan if they're missing
	 * @return string|null
	 */
	public function getWcfMissingExclusions() {
		$sPattern = null;
		$aExclusions = $this->getMod()->getDef( 'wcf_exclusions_missing_only' );
		if ( is_array( $aExclusions ) && !empty( $aExclusions ) ) {
			$aQuoted = array_map(
				function ( $sExcl ) {
					return preg_quote( $sExcl, '#' );
				},
				$aExclusions
			);
			$sPattern = '#('.implode( '|', $aQuoted ).')#i';
		}
		return $sPattern;
	}
}