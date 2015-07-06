<?php

if ( !class_exists( 'ICWP_WPSF_Processor_HackProtect_PluginVulnerabilities_V1', false ) ):

	require_once( dirname(__FILE__).ICWP_DS.'base.php' );

	class ICWP_WPSF_Processor_HackProtect_PluginVulnerabilities_V1 extends ICWP_WPSF_Processor_Base {

		const PvSourceKey = 'plugin-vulnerabilities';

		/**
		 * @var array
		 */
		static protected $aPluginVulnerabilities;

		/**
		 * @var int
		 */
		static protected $nColumnsCount;

		/**
		 * Override to set what this processor does when it's "run"
		 */
		public function run() {
			add_filter( 'manage_plugins_columns', array( $this, 'fCountColumns' ), 1000 );
			add_action( 'after_plugin_row', array( $this, 'attachVulnerabilityWarning' ), 10, 2 );
		}

		/**
		 * @param array $aColumns
		 * @return array
		 */
		public function fCountColumns( $aColumns ) {
			if ( !isset( self::$nColumnsCount ) ) {
				self::$nColumnsCount = count( $aColumns );
			}
			return $aColumns;
		}

		/**
		 * @param string $sPluginFile
		 * @param array $aPluginData
		 */
		public function attachVulnerabilityWarning( $sPluginFile, $aPluginData ) {

			if ( !isset( self::$aPluginVulnerabilities ) ) {
				self::$aPluginVulnerabilities = $this->loadPluginVulnerabilities();
			}
			else if ( empty( self::$aPluginVulnerabilities ) ) {
				return;
			}

			$sPluginDir = substr( $sPluginFile, 0, strpos( $sPluginFile, ICWP_DS ) );

			if ( array_key_exists( $sPluginDir, self::$aPluginVulnerabilities ) ) {
				foreach( self::$aPluginVulnerabilities[$sPluginDir] as $aVulnerabilityItem ) {
					if ( version_compare( $aPluginData['Version'], $aVulnerabilityItem['FirstVersion'], '>=' )
						 && version_compare( $aPluginData['Version'], $aVulnerabilityItem['LastVersion'], '<=' ) ) {

						$aRenderData = array(
							'strings' => array (
								'known_vuln' => sprintf( _wpsf__( '%s has discovered the "%s" plugin has a known vulnerability.'), $this->getController()->getHumanName(), $aPluginData['Name'] ),
								'vuln_type' => sprintf( _wpsf__( 'Vulnerability Type: "%s".'), $aVulnerabilityItem['TypeOfVulnerability'] ),
								'more_info' => _wpsf__( 'More Info' )
							),
							'hrefs' => array(
								'more_info' => $aVulnerabilityItem[ 'URL' ]
							),
							'nColspan' => self::$nColumnsCount
						);
						echo $this->getFeatureOptions()->renderTemplate( 'snippets'.ICWP_DS.'plugin-vulnerability.php', $aRenderData );
					}
				}
			}
		}

		/**
		 * @return array|false
		 */
		protected function loadPluginVulnerabilities() {
			$oWp = $this->loadWpFunctionsProcessor();
			$oFO = $this->getFeatureOptions();

			$aPv = $oWp->getTransient( $oFO->prefixOptionKey( self::PvSourceKey ) );
			if ( empty( $aPv ) ) {
				$aPv = $this->downloadPluginVulnerabilitiesFromSource();
			}
			return $aPv;
		}

		/**
		 * @return array|false
		 */
		protected function downloadPluginVulnerabilitiesFromSource() {
			$oWp = $this->loadWpFunctionsProcessor();
			$oFO = $this->getFeatureOptions();

			$sSource = 'https://raw.githubusercontent.com/FernleafSystems/wp-plugin-vulnerabilities/master/vulnerabilities.yaml';
			$oFs = $this->loadFileSystemProcessor();
			$sRawSource = $oFs->getUrlContent( $sSource );
			if ( $sRawSource === false ) {
				return false;
			}

			$aPluginVulnerabilitiesParsed = $this->loadYamlProcessor()->parseYamlString( $sRawSource );
			if ( is_array( $aPluginVulnerabilitiesParsed ) ) {
				$oWp->setTransient( $oFO->prefixOptionKey( self::PvSourceKey ), $aPluginVulnerabilitiesParsed, DAY_IN_SECONDS * 3 );
				return $aPluginVulnerabilitiesParsed;
			}
			return false;
		}
	}

endif;

if ( !class_exists( 'ICWP_WPSF_Processor_HackProtect_PluginVulnerabilities', false ) ):
	class ICWP_WPSF_Processor_HackProtect_PluginVulnerabilities extends ICWP_WPSF_Processor_HackProtect_PluginVulnerabilities_V1 { }
endif;