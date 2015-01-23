<?php

if ( !class_exists( 'ICWP_WPSF_Foundation', false ) ) :

	class ICWP_WPSF_Foundation {

		/**
		 * @return ICWP_WPSF_DataProcessor
		 */
		static public function loadDataProcessor() {
			if ( ! class_exists( 'ICWP_WPSF_DataProcessor', false ) ) {
				require_once( dirname(__FILE__).ICWP_DS.'icwp-data.php' );
			}
			return ICWP_WPSF_DataProcessor::GetInstance();
		}

		/**
		 * @return ICWP_WPSF_WpFilesystem
		 */
		static public function loadFileSystemProcessor() {
			if ( ! class_exists( 'ICWP_WPSF_WpFilesystem', false ) ) {
				require_once( dirname(__FILE__).ICWP_DS.'icwp-wpfilesystem.php' );
			}
			return ICWP_WPSF_WpFilesystem::GetInstance();
		}

		/**
		 * @return ICWP_WPSF_WpFunctions
		 */
		static public function loadWpFunctionsProcessor() {
			if ( ! class_exists( 'ICWP_WPSF_WpFunctions', false ) ) {
				require_once( dirname(__FILE__).ICWP_DS.'icwp-wpfunctions.php' );
			}
			return ICWP_WPSF_WpFunctions::GetInstance();
		}

		/**
		 * @return ICWP_WPSF_WpDb
		 */
		static public function loadDbProcessor() {
			if ( ! class_exists( 'ICWP_WPSF_WpDb', false ) ) {
				require_once( dirname(__FILE__).ICWP_DS.'icwp-wpdb.php' );
			}
			return ICWP_WPSF_WpDb::GetInstance();
		}

		/**
		 * @return ICWP_WPSF_YamlProcessor
		 */
		static public function loadYamlProcessor() {
			if ( ! class_exists( 'ICWP_WPSF_YamlProcessor', false ) ) {
				require_once( dirname(__FILE__).ICWP_DS.'icwp-yaml.php' );
			}
			return ICWP_WPSF_YamlProcessor::GetInstance();
		}

		/**
		 * @return ICWP_Stats_APP
		 */
		public function loadStatsProcessor() {
			require_once( dirname(__FILE__).ICWP_DS.'icwp-stats.php' );
		}
	}

endif;