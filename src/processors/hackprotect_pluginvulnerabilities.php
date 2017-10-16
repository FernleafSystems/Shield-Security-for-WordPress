<?php

if ( !class_exists( 'ICWP_WPSF_Processor_HackProtect_PluginVulnerabilities', false ) ):

	require_once( dirname(__FILE__).DIRECTORY_SEPARATOR.'base_wpsf.php' );

	class ICWP_WPSF_Processor_HackProtect_PluginVulnerabilities extends ICWP_WPSF_Processor_BaseWpsf {

		const PvSourceKey = 'plugin-vulnerabilities';

		/**
		 * @var array
		 */
		protected $aPluginVulnerabilities;

		/**
		 * @var int
		 */
		protected $nColumnsCount;

		/**
		 * @var array
		 */
		protected $aPluginVulnerabilitiesEmailContents;

		/**
		 */
		public function run() {

			$this->setupNotificationsCron();

			// For display on the Plugins page
			add_filter( 'manage_plugins_columns', array( $this, 'fCountColumns' ), 1000 );
			add_action( 'admin_init', array( $this, 'addPluginVulnerabilityRows' ), 10, 2 );

		}

		protected function setupNotificationsCron() {
			$oWpCron = $this->loadWpCronProcessor();
			$oWpCron
				->setRecurrence( 'daily' )
				->createCronJob(
					$this->getCronName(),
					array( $this, 'cron_dailyPluginVulnerabilitiesScan' )
			);
			add_action( $this->getFeature()->prefix( 'delete_plugin' ), array( $this, 'deleteCron' )  );
		}

		/**
		 */
		public function deleteCron() {
			$this->loadWpCronProcessor()->deleteCronJob( $this->getCronName() );
		}

		public function cron_dailyPluginVulnerabilitiesScan() {

			$aPlugins = $this->loadWp()->getPlugins();

			$sRecipient = $this->getPluginDefaultRecipientAddress();
			foreach( $aPlugins as $sPluginFile => $aPluginData ) {
				$aPluginVulnerabilityData = $this->getPluginVulnerabilityData( $sPluginFile, $aPluginData );
				if ( is_array( $aPluginVulnerabilityData ) ) {
					$this->addPluginVulnerabilityToEmail( $aPluginData, $aPluginVulnerabilityData );
				}
			}

			$this->sendVulnerabilityNotification( $sRecipient );
		}

		/**
		 * @param array $aPluginData
		 * @param array $aVulnerabilityData
		 */
		protected function addPluginVulnerabilityToEmail( $aPluginData, $aVulnerabilityData ) {
			if ( !isset( $this->aPluginVulnerabilitiesEmailContents ) ) {
				$this->aPluginVulnerabilitiesEmailContents = array();
			}
			$this->aPluginVulnerabilitiesEmailContents = array_merge(
				$this->aPluginVulnerabilitiesEmailContents,
				array(
					'- ' . sprintf( _wpsf__( 'Plugin Name: %s' ), $aPluginData[ 'Name' ] ),
					'- ' . sprintf( _wpsf__( 'Vulnerability Type: %s' ), $aVulnerabilityData[ 'TypeOfVulnerability' ] ),
					'- ' . sprintf( _wpsf__( 'Vulnerable Plugin Version Range: %s' ), $aVulnerabilityData[ 'FirstVersion' ] . ' - ' . $aVulnerabilityData[ 'LastVersion' ] ),
					'- ' . sprintf( _wpsf__( 'Further Information: %s' ), $aVulnerabilityData[ 'URL' ] ),
					'',
				)
			);
		}

		/**
		 * @param string $sRecipient
		 * @return bool
		 */
		protected function sendVulnerabilityNotification( $sRecipient ) {

			if ( empty( $this->aPluginVulnerabilitiesEmailContents ) ) {
				return true;
			}

			$aPreamble = array(
				sprintf( _wpsf__( '%s has detected a plugin with a known security vulnerability on your site.' ), $this->getController()->getHumanName() ),
				_wpsf__( 'Details for the plugin(s) are below:' ),
				'',
			);

			$this->aPluginVulnerabilitiesEmailContents = array_merge( $aPreamble, $this->aPluginVulnerabilitiesEmailContents );
			$this->aPluginVulnerabilitiesEmailContents[ ] = _wpsf__( 'You should update or remove these plugins at your earliest convenience.' );

			$sEmailSubject = sprintf( _wpsf__( 'Warning - %s' ), _wpsf__( 'Plugin(s) Discovered With Known Security Vulnerabilities.' ) );

			$bSendSuccess = $this->getEmailProcessor()->sendEmailTo( $sRecipient, $sEmailSubject, $this->aPluginVulnerabilitiesEmailContents );

			if ( $bSendSuccess ) {
				$this->addToAuditEntry( sprintf( _wpsf__( 'Successfully sent Plugin Vulnerability Notification email alert to: %s' ), $sRecipient ) );
			}
			else {
				$this->addToAuditEntry( sprintf( _wpsf__( 'Failed to send Plugin Vulnerability Notification email alert to: %s' ), $sRecipient ) );
			}
			return $bSendSuccess;
		}

		public function addPluginVulnerabilityRows() {
			$aPlugins = $this->loadWp()->getPlugins();
			foreach( array_keys( $aPlugins ) as $sPluginFile ) {
				add_action( "after_plugin_row_$sPluginFile", array( $this, 'attachVulnerabilityWarning' ), 100, 2 );
			}
		}

		/**
		 * @param array $aColumns
		 * @return array
		 */
		public function fCountColumns( $aColumns ) {
			if ( !isset( $this->nColumnsCount ) ) {
				$this->nColumnsCount = count( $aColumns );
			}
			return $aColumns;
		}

		/**
		 * @param string $sPluginFile
		 * @param array $aPluginData
		 */
		public function attachVulnerabilityWarning( $sPluginFile, $aPluginData ) {

			$aPluginVulnerabilityData = $this->getPluginVulnerabilityData( $sPluginFile, $aPluginData );
			if ( is_array( $aPluginVulnerabilityData ) ) {
				$aRenderData = array(
					'strings' => array (
						'known_vuln' => sprintf( _wpsf__( '%s has discovered that the currently installed version of the "%s" plugin has a known security vulnerability.'), $this->getController()->getHumanName(), $aPluginData['Name'] ),
						'vuln_type' => _wpsf__( 'Vulnerability Type' ),
						'vuln_type_explanation' => ucfirst( $aPluginVulnerabilityData['TypeOfVulnerability'] ),
						'vuln_versions' => _wpsf__( 'Vulnerable Versions' ),
						'more_info' => _wpsf__( 'More Info' ),
						'first_version' => $aPluginVulnerabilityData['FirstVersion'],
						'last_version' => $aPluginVulnerabilityData['LastVersion'],
					),
					'hrefs' => array(
						'more_info' => $aPluginVulnerabilityData[ 'URL' ]
					),
					'nColspan' => $this->nColumnsCount
				);
				echo $this->getFeature()->renderTemplate( 'snippets'.DIRECTORY_SEPARATOR.'plugin-vulnerability.php', $aRenderData );
			}
		}

		/**
		 * @param string $sPluginFile
		 * @param array $aPluginData
		 * @return false|array			- array if a vulnerability exists
		 */
		protected function getPluginVulnerabilityData( $sPluginFile, $aPluginData ) {

			$aPV = $this->loadPluginVulnerabilities();
			if ( empty( $aPV ) ) {
				return false;
			}

			$sSlug = !empty( $aPluginData['slug'] ) ? $aPluginData['slug'] : substr( $sPluginFile, 0, strpos( $sPluginFile, DIRECTORY_SEPARATOR ) );
			if ( array_key_exists( $sSlug, $aPV ) ) {
				foreach( $aPV[$sSlug] as $aVulnerabilityItem ) {

					if ( version_compare( $aPluginData['Version'], $aVulnerabilityItem['FirstVersion'], '>=' )
						 && version_compare( $aPluginData['Version'], $aVulnerabilityItem['LastVersion'], '<=' ) ) {

						return $aVulnerabilityItem;
					}
				}
			}
			return false;
		}

		/**
		 * @return array|false
		 */
		protected function loadPluginVulnerabilities() {

			if ( !isset( $this->aPluginVulnerabilities ) ) {

				$oWp = $this->loadWp();
				$oFO = $this->getFeature();
				$this->aPluginVulnerabilities = $oWp->getTransient( $oFO->prefixOptionKey( self::PvSourceKey ) );
				if ( empty( $this->aPluginVulnerabilities ) ) {
					$this->aPluginVulnerabilities = $this->downloadPluginVulnerabilitiesFromSource();
				}
			}
			return $this->aPluginVulnerabilities;
		}

		/**
		 * @return array|false
		 */
		protected function downloadPluginVulnerabilitiesFromSource() {
			$oWp = $this->loadWp();
			$oFO = $this->getFeature();

			$sSource = $oFO->getDefinition( 'plugin_vulnerabilities_data_source' );
			$sRawSource = $this->loadFS()->getUrlContent( $sSource );
			if ( $sRawSource === false ) {
				return false;
			}

			$aPluginVulnerabilitiesParsed = $this->loadYamlProcessor()->parseYamlString( $sRawSource );
			if ( is_array( $aPluginVulnerabilitiesParsed ) ) {
				$oWp->setTransient( $oFO->prefixOptionKey( self::PvSourceKey ), $aPluginVulnerabilitiesParsed, DAY_IN_SECONDS );
				return $aPluginVulnerabilitiesParsed;
			}
			return false;
		}

		/**
		 * @return string
		 */
		protected function getCronName() {
			$oFO = $this->getFeature();
			return $oFO->prefixOptionKey( $oFO->getDefinition( 'notifications_cron_name' ) );
		}
	}

endif;