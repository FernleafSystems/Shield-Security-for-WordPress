<?php

if ( !class_exists( 'ICWP_WPSF_Processor_HackProtect_PluginVulnerabilities_V1', false ) ):

	require_once( dirname(__FILE__).ICWP_DS.'base.php' );

	class ICWP_WPSF_Processor_HackProtect_PluginVulnerabilities_V1 extends ICWP_WPSF_Processor_Base {

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
		 */
		public function run() {

			$this->setupNotificationsCron();

			// For display on the Plugins page
			add_filter( 'manage_plugins_columns', array( $this, 'fCountColumns' ), 1000 );
			add_action( 'after_plugin_row', array( $this, 'attachVulnerabilityWarning' ), 10, 2 );
		}

		protected function setupNotificationsCron() {
			$oWpCron = $this->loadWpCronProcessor();
			$oWpCron->createCronJob(
				$this->getFeatureOptions()->prefixOptionKey( $this->getOption( 'notifications_cron_name' ) ),
				array( $this, 'cron_dailyPluginVulnerabilitiesScan' ),
				'daily'
			);
			add_action( $this->getFeatureOptions()->doPluginPrefix( 'delete_plugin' ), array( $this, 'deleteCron' )  );
		}

		/**
		 */
		public function deleteCron() {
			$oWpCron = $this->loadWpCronProcessor();
			$oWpCron->deleteCronJob( $this->getFeatureOptions()->prefixOptionKey( $this->getOption( 'notifications_cron_name' ) ) );
		}

		public function cron_dailyPluginVulnerabilitiesScan() {

			$oWp = $this->loadWpFunctionsProcessor();
			$aPlugins = $oWp->getPlugins();

			$sRecipient = $this->getPluginDefaultRecipientAddress();
			foreach( $aPlugins as $sPluginFile => $aPluginData ) {
				$aPluginVulnerabilityData = $this->getPluginVulnerabilityData( $sPluginFile, $aPluginData );

				if ( is_array( $aPluginVulnerabilityData ) ) {
					$bSendSuccess = $this->sendVulnerabilityNotification( $sRecipient, $aPluginData, $aPluginVulnerabilityData );
					if ( $bSendSuccess ) {
						$this->addToAuditEntry( sprintf( _wpsf__( 'Successfully sent Plugin Vulnerability Notification email alert to: %s' ), $sRecipient ) );
					}
					else {
						$this->addToAuditEntry( sprintf( _wpsf__( 'Failed to send Plugin Vulnerability Notification email alert to: %s' ), $sRecipient ) );
					}
				}
			}
		}

		/**
		 * @param string $sRecipient
		 * @param array $aPluginData
		 * @param array $aVulnerabilityData
		 * @return bool
		 */
		protected function sendVulnerabilityNotification( $sRecipient, $aPluginData, $aVulnerabilityData ) {

			$aMessage = array(
				sprintf( _wpsf__( '%s has detected a plugin with a known security vulnerability on your site.' ), $this->getController()->getHumanName() ),
				_wpsf__( 'Details for this plugin are below:' ),
				'- ' . sprintf( _wpsf__( 'Plugin Name: %s' ), $aPluginData[ 'Name' ] ),
				'- ' . sprintf( _wpsf__( 'Vulnerability Type: %s' ), $aVulnerabilityData[ 'TypeOfVulnerability' ] ),
				'- ' . sprintf( _wpsf__( 'Vulnerable Plugin Version Range: %s' ), $aVulnerabilityData[ 'FirstVersion' ] . ' - ' . $aVulnerabilityData[ 'LastVersion' ] ),
				'- ' . sprintf( _wpsf__( 'Further Information: %s' ), $aVulnerabilityData[ 'URL' ] ),
				_wpsf__( 'You should update or remove this plugin at your earliest convenience.' ),
			);
			$sEmailSubject = _wpsf__( 'Warning: Plugin Discovered With Known Security Vulnerability' );

			$bSendSuccess = $this->getEmailProcessor()->sendEmailTo( $sRecipient, $sEmailSubject, $aMessage );
			return $bSendSuccess;
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
				echo $this->getFeatureOptions()->renderTemplate( 'snippets'.ICWP_DS.'plugin-vulnerability.php', $aRenderData );
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

			$sPluginDir = substr( $sPluginFile, 0, strpos( $sPluginFile, ICWP_DS ) );
			if ( array_key_exists( $sPluginDir, $aPV ) ) {

				foreach( $aPV[$sPluginDir] as $aVulnerabilityItem ) {

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

				$oWp = $this->loadWpFunctionsProcessor();
				$oFO = $this->getFeatureOptions();
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
			$oWp = $this->loadWpFunctionsProcessor();
			$oFO = $this->getFeatureOptions();

			$sSource = $this->getOption( 'plugin_vulnerabilities_data_source' );
			$sRawSource = $this->loadFileSystemProcessor()->getUrlContent( $sSource );
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
	}

endif;

if ( !class_exists( 'ICWP_WPSF_Processor_HackProtect_PluginVulnerabilities', false ) ):
	class ICWP_WPSF_Processor_HackProtect_PluginVulnerabilities extends ICWP_WPSF_Processor_HackProtect_PluginVulnerabilities_V1 { }
endif;