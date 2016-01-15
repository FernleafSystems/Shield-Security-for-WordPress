<?php

if ( !class_exists( 'ICWP_WPSF_Processor_HackProtect_CoreChecksumScan', false ) ):

	require_once( dirname(__FILE__).ICWP_DS.'base.php' );

	class ICWP_WPSF_Processor_HackProtect_CoreChecksumScan extends ICWP_WPSF_Processor_Base {

		/**
		 */
		public function run() {
			$this->setupChecksumCron();
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
			$sChecksumContent = $this->loadFileSystemProcessor()->getUrlContent( $this->getChecksumUrl() );

			$sExclusionsPattern = '#('.implode('|', $this->getExclusions() ).')#i';

			if ( !empty( $sChecksumContent ) ) {
				$oChecksumData = json_decode( $sChecksumContent );
				if ( is_object( $oChecksumData ) && isset( $oChecksumData->checksums ) && is_object( $oChecksumData->checksums ) ) {

					$aFiles = array(
						'checksum_mismatch' => array(),
						'missing' => array(),
					);

					$oFS = $this->loadFileSystemProcessor();
					foreach ( $oChecksumData->checksums as $sFilePath => $sChecksum ) {
						if ( preg_match( $sExclusionsPattern, $sFilePath ) ) {
							continue;
						}

						$bBad = false;

						$sFullPath = ABSPATH . $sFilePath;
						if ( $oFS->isFile( $sFullPath ) ) {
							if ( $sChecksum != md5_file( $sFullPath ) ) {
								$aFiles[ 'checksum_mismatch' ][] = $sFilePath;
								$bBad = true;
							}
						}
						else {
							$aFiles[ 'missing' ][] = $sFilePath;
							$bBad = true;
						}

						if ( $bBad && $this->getIsOption( 'attempt_auto_file_repair', 'Y' ) ) {
							$this->replaceFileContentsWithOfficial( $sFilePath );
						}
					}

					if ( !empty( $aFiles[ 'checksum_mismatch' ] ) || !empty( $aFiles[ 'missing' ] ) ) {
						$sRecipient = $this->getPluginDefaultRecipientAddress();
						$this->sendChecksumErrorNotification( $aFiles, $sRecipient );
					}
				}
			}
		}

		/**
		 * @return array
		 */
		protected function getExclusions() {
			$aExclusions = $this->getFeatureOptions()->getDefinition( 'corechecksum_exclusions' );
			if ( empty( $aExclusions ) || !is_array( $aExclusions ) ) {
				$aExclusions = array();
			}
			foreach ( $aExclusions as $nKey => $sExclusion ) {
				$aExclusions[ $nKey ] = preg_quote( $sExclusion, '#' );
			}
			return $aExclusions;
		}

		/**
		 * @param $sPath
		 * @return false|string
		 */
		protected function downloadSingleWordPressCoreFile( $sPath ) {
			$sBaseSvnUrl = $this->getFeatureOptions()->getDefinition( 'url_wordress_core_svn' ).'tags/'.$this->loadWpFunctionsProcessor()->getWordpressVersion().'/';
			$sFileUrl = path_join( $sBaseSvnUrl, $sPath );
			return $this->loadFileSystemProcessor()->getUrlContent( $sFileUrl );
		}

		/**
		 * @param string $sPath
		 * @return bool|null
		 */
		protected function replaceFileContentsWithOfficial( $sPath ) {
			$sOfficialContent = $this->downloadSingleWordPressCoreFile( $sPath );
			if ( !empty( $sOfficialContent ) ) {
				return $this->loadFileSystemProcessor()->putFileContent( path_join( ABSPATH, $sPath ), $sOfficialContent );
			}
			return false;
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
		 * @param array $aFiles
		 * @param string $sRecipient
		 * @return bool
		 */
		protected function sendChecksumErrorNotification( $aFiles, $sRecipient ) {
			if ( empty( $aFiles ) && empty( $aFiles['missing'] ) && empty( $aFiles['checksum_mismatch'] ) ) {
				return true;
			}

			$oWp = $this->loadWpFunctionsProcessor();
			$aContent = array(
				sprintf( _wpsf__( '%s has detected files on your site with potential problems.' ), $this->getController()->getHumanName() )
				. ' <a href="http://icwp.io/moreinfochecksum">'._wpsf__('More Info').'</a>',
				sprintf( 'Site - %s', sprintf( '<a href="%s" target="_blank">%s</a>', $oWp->getHomeUrl(), $oWp->getSiteName() ) ),
				_wpsf__( 'Details for the files are below:' ),
			);

			if ( !empty( $aFiles['checksum_mismatch'] ) ) {
				$aContent[] = '';
				$aContent[] = _wpsf__('The MD5 Checksum Hashes for following core files do not match the official WordPress.org Checksum Hashes:');
				foreach( $aFiles['checksum_mismatch'] as $sFile ) {
					$aContent[] = ' - ' . $sFile;
				}
			}
			if ( !empty( $aFiles['missing'] ) ) {
				$aContent[] = '';
				$aContent[] = _wpsf__('The following official WordPress core files are missing from your site:');
				foreach( $aFiles['missing'] as $sFile ) {
					$aContent[] = ' - ' . $sFile;
				}
			}

			$aContent[] = '';
			if ( $this->getIsOption( 'attempt_auto_file_repair', 'Y' ) ) {
				$aContent[] = _wpsf__( 'We have already attempted to repair these files based on your current settings.' );
			}
			else {
				$aContent[] = _wpsf__( 'You should review these files and replace them with official versions if required.' );
			}

			$sEmailSubject = sprintf( _wpsf__( 'Warning - %s' ), _wpsf__( 'Core WordPress Files(s) Discovered That May Have Been Modified.' ) );
			$bSendSuccess = $this->getEmailProcessor()->sendEmailTo( $sRecipient, $sEmailSubject, $aContent );

			if ( $bSendSuccess ) {
				$this->addToAuditEntry( sprintf( _wpsf__( 'Successfully sent Checksum Scan Notification email alert to: %s' ), $sRecipient ) );
			}
			else {
				$this->addToAuditEntry( sprintf( _wpsf__( 'Failed to send Checksum Scan Notification email alert to: %s' ), $sRecipient ) );
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