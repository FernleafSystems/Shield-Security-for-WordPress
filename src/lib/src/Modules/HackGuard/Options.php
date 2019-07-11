<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

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
	 * @return string
	 */
	public function getUrlMalSigsSimple() {
		return;
	}

	/**
	 * @return string
	 */
	public function getUrlMalSigsRegEx() {
		return $this->getDef( 'url_mal_sigs_regex' );
	}
}