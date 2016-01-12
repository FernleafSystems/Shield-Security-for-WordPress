<?php

if ( !class_exists( 'ICWP_WPSF_Processor_HackProtect_CoreChecksum', false ) ):

	require_once( dirname(__FILE__).ICWP_DS.'base.php' );

	class ICWP_WPSF_Processor_HackProtect_CoreChecksum extends ICWP_WPSF_Processor_Base {

		/**
		 */
		public function run() {
			$this->cron_dailyChecksumScan();
		}

		protected function setupChecksumCron() {
			$oWpCron = $this->loadWpCronProcessor();
			$oWpCron->createCronJob(
				$this->getCronName(),
				array( $this, 'cron_dailyChecksumScan' ),
				'daily'
			);
			add_action( $this->getFeatureOptions()->doPluginPrefix( 'delete_plugin' ), array( $this, 'deleteCron' )  );
		}

		/**
		 */
		public function deleteCron() {
			$this->loadWpCronProcessor()->deleteCronJob( $this->getCronName() );
		}

		public function cron_dailyChecksumScan() {
			var_dump( $this->getChecksumUrl() );
			$sChecksumContent = $this->loadFileSystemProcessor()->getUrlContent( $this->getChecksumUrl() );

			$oFS = $this->loadFileSystemProcessor();

			if ( !empty( $sChecksumContent ) ) {
				$oChecksumData = json_decode( $sChecksumContent );
				if ( is_object( $oChecksumData ) && isset( $oChecksumData->checksums ) && is_object( $oChecksumData->checksums ) ) {
					foreach ( $oChecksumData->checksums as $sFilePath => $sChecksum ) {
						if ( $oFS->isFile( ABSPATH.$sFilePath ) ) {
							if ( $sChecksum != md5_file( ABSPATH . $sFilePath ) ) {
								echo $sFilePath;
								var_dump( $this->downloadSingleWordPressCoreFile( $sFilePath ) );
							}
						}
					}
				}
				die('dione');
			}
//			$sRecipient = $this->getPluginDefaultRecipientAddress();
//			$this->sendVulnerabilityNotification( $sRecipient );
		}

		protected function downloadSingleWordPressCoreFile( $sPath ) {
			$sBaseSvnUrl = $this->getFeatureOptions()->getDefinition( 'url_wordress_core_svn' ).'tags/'.$this->loadWpFunctionsProcessor()->getWordpressVersion().'/';
			$sFileUrl = path_join( $sBaseSvnUrl, $sPath );
			var_dump( $sFileUrl );
			$sData = $this->loadFileSystemProcessor()->getUrlContent( $sFileUrl );
			return $sData;
		}

		/**
		 * @return string
		 */
		protected function getChecksumUrl() {
			$oFO = $this->getFeatureOptions();
			$sBaseUrl = $oFO->getDefinition( 'url_checksum_api' );
			$aQueryArgs = array(
				'version' 	=> $this->loadWpFunctionsProcessor()->getWordpressVersion(),
				'locale'	=> get_locale()
			);
			return add_query_arg( $aQueryArgs, $sBaseUrl );
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

		/**
		 * @return string
		 */
		protected function getCronName() {
			$oFO = $this->getFeatureOptions();
			return $oFO->prefixOptionKey( $oFO->getDefinition( 'corechecksum_cron_name' ) );
		}
	}

endif;