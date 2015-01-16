<?php

if ( !class_exists( 'ICWP_WPSF_Foundation', false ) ) :

class ICWP_WPSF_Foundation {

	/**
	 * @return ICWP_WPSF_DataProcessor
	 */
	public function loadDataProcessor() {
		require_once( 'icwp-data-processor.php' );
		return ICWP_WPSF_DataProcessor::GetInstance();
	}

	/**
	 * @return ICWP_WPSF_WpFilesystem
	 */
	public function loadFileSystemProcessor() {
		require_once( 'icwp-wpfilesystem.php' );
		return ICWP_WPSF_WpFilesystem::GetInstance();
	}

	/**
	 * @return ICWP_WPSF_WpFunctions
	 */
	public function loadWpFunctionsProcessor() {
		require_once( 'icwp-wpfunctions.php' );
		return ICWP_WPSF_WpFunctions::GetInstance();
	}

	/**
	 * @return ICWP_WPSF_WpDb
	 */
	public function loadDbProcessor() {
		require_once( 'icwp-wpdb.php' );
		return ICWP_WPSF_WpDb::GetInstance();
	}

	/**
	 * @return ICWP_WPSF_YamlProcessor
	 */
	public function loadYamlProcessor() {
		require_once( 'icwp-processor-yaml.php' );
		return ICWP_WPSF_YamlProcessor::GetInstance();
	}

	/**
	 * @return ICWP_Stats_WPSF
	 */
	public function loadStatsProcessor() {
		require_once( 'icwp-wpsf-stats.php' );
	}
}

endif;