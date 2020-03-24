<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Services\Services;

class ImportExportController {

	use ModConsumer;

	public function run() {
		/** @var Plugin\Options $oOpts */
		$oOpts = $this->getOptions();

		// Cron
		add_action( $this->getCon()->prefix( 'importexport_notify' ), function () {
			( new NotifyWhitelist() )
				->setMod( $this->getMod() )
				->run();
		} );

		if ( $oOpts->hasImportExportMasterImportUrl() ) {
			// For auto update whitelist notifications:
			add_action( $this->getCon()->prefix( 'importexport_updatenotified' ), [ $this, 'runImport' ] );
		}
	}

	/**
	 * @return array
	 */
	public function buildInsightsVars() {
		/** @var \ICWP_WPSF_FeatureHandler_HackProtect $oMod */
		$oMod = $this->getMod();
		return [
			'vars'    => [
				'file_upload_nonce' => $oMod->getNonceActionData( 'import_file_upload' ),
				'form_action'       => $oMod->getUrl_AdminPage()
			],
			'ajax'    => [
				'import_from_site' => $oMod->getAjaxActionData( 'import_from_site', true ),
			],
			'flags'   => [
				'can_importexport' => $this->getCon()->isPremiumActive(),
			],
			'hrefs'   => [
				'export_file_download' => $this->createExportFileDownloadLink()
			],
			'strings' => [
				'title_import_file'    => __( 'Import From File', 'wp-simple-firewall' ),
				'subtitle_import_file' => __( 'Upload an exported options file you downloaded from another site', 'wp-simple-firewall' ),
				'select_import_file'   => __( 'Select file to import options from', 'wp-simple-firewall' ),
				'i_understand'         => __( 'I Understand Existing Options Will Be Overwritten', 'wp-simple-firewall' ),
				'be_sure'              => __( 'Please be sure that this is what you want.', 'wp-simple-firewall' ),
				'not_undone'           => __( "This action can't be undone.", 'wp-simple-firewall' ),
				'title_import_site'    => __( "Import From Site", 'wp-simple-firewall' ),

				'title_download_file'    => __( 'Download Options Export File', 'wp-simple-firewall' ),
				'subtitle_download_file' => __( 'Use this file to copy options from this site into another site', 'wp-simple-firewall' ),

				'subtitle_import_site'     => __( 'Import options directly from another site', 'wp-simple-firewall' ),
				'master_site_url'          => __( 'Master Site URL', 'wp-simple-firewall' ),
				'remember_include'         => sprintf(
					__( 'Remember to include %s or %s', 'wp-simple-firewall' ),
					'<code>https://</code>',
					'<code>http://</code>'
				),
				'secret_key'               => __( 'Secret Key', 'wp-simple-firewall' ),
				'master_site_key'          => __( 'Master Site Secret Key', 'wp-simple-firewall' ),
				'create_network'           => __( 'Create Shield Network', 'wp-simple-firewall' ),
				'key_found_under'          => sprintf( __( 'The secret key is found in: %s', 'wp-simple-firewall' ),
					ucwords( sprintf( '%s > %s > %s ', __( 'General Settings', 'wp-simple-firewall' ), __( 'Import/Export', 'wp-simple-firewall' ), __( 'Secret Key', 'wp-simple-firewall' ) ) )
				),
				'turn_on'                  => __( 'Turn On', 'wp-simple-firewall' ),
				'turn_off'                 => __( 'Turn Off', 'wp-simple-firewall' ),
				'no_change'                => __( 'No Change', 'wp-simple-firewall' ),
				'network_explain'          => [
					__( 'Checking this option on will link this site to Master site.', 'wp-simple-firewall' ),
					__( 'Options will be automatically imported from the Master site each night', 'wp-simple-firewall' ),
					__( 'When you adjust options on the Master site, they will be reflected in this site after the automatic import', 'wp-simple-firewall' ),
				],
				'import_options'           => __( 'Import Options', 'wp-simple-firewall' ),
				'downloading_please_wait'  => __( 'Downloading file, please wait...', 'wp-simple-firewall' ),
				'problem_downloading_file' => __( 'There was a problem downloading the file.', 'wp-simple-firewall' ),
			]
		];
	}

	/**
	 * @return string
	 */
	private function createExportFileDownloadLink() {
		return add_query_arg(
			$this->getMod()->getNonceActionData( 'export_file_download' ),
			$this->getMod()->getUrl_AdminPage()
		);
	}

	/**
	 * @param string    $sMasterSiteUrl
	 * @param string    $sSecretKey
	 * @param bool|null $bEnableNetwork
	 * @param string    $sSiteResponse
	 * @return int
	 */
	public function runImport( $sMasterSiteUrl = '', $sSecretKey = '', $bEnableNetwork = null, &$sSiteResponse = '' ) {
		/** @var Plugin\Options $oOpts */
		$oOpts = $this->getOptions();
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getMod();
		$oDP = Services::Data();

		if ( empty( $sMasterSiteUrl ) ) {
			$sMasterSiteUrl = $oOpts->getImportExportMasterImportUrl();
		}

		$aParts = parse_url( $sMasterSiteUrl );

		$sOriginalMasterSiteUrl = $oOpts->getImportExportMasterImportUrl();
		$bHadMasterSiteUrl = $oOpts->hasImportExportMasterImportUrl();
		$bCheckKeyFormat = !$bHadMasterSiteUrl;
		$sSecretKey = preg_replace( '#[^0-9a-z]#i', '', $sSecretKey );

		if ( $bCheckKeyFormat && empty( $sSecretKey ) ) {
			$nErrorCode = 1;
		}
		elseif ( $bCheckKeyFormat && strlen( $sSecretKey ) != 40 ) {
			$nErrorCode = 2;
		}
		elseif ( $bCheckKeyFormat && preg_match( '#[^0-9a-z]#i', $sSecretKey ) ) {
			$nErrorCode = 3; //unused
		}
		elseif ( empty( $aParts ) ) {
			$nErrorCode = 4;
		}
		elseif ( $oDP->validateSimpleHttpUrl( $sMasterSiteUrl ) === false ) {
			$nErrorCode = 4; // a final check
		}
		else {
			$bReady = true;
			$aEssential = [ 'scheme', 'host' ];
			foreach ( $aEssential as $sKey ) {
				$bReady = $bReady && !empty( $aParts[ $sKey ] );
			}

			$sMasterSiteUrl = $oDP->validateSimpleHttpUrl( $sMasterSiteUrl ); // final clean

			if ( !$bReady || !$sMasterSiteUrl ) {
				$nErrorCode = 4;
			}
			else {
				$oMod->startImportExportHandshake();

				$aData = [
					'shield_action' => 'importexport_export',
					'secret'        => $sSecretKey,
					'url'           => Services::WpGeneral()->getHomeUrl()
				];
				// Don't send the network setup request if it's the cron.
				if ( !is_null( $bEnableNetwork ) && !Services::WpGeneral()->isCron() ) {
					$aData[ 'network' ] = $bEnableNetwork ? 'Y' : 'N';
				}

				$sFinalUrl = add_query_arg( $aData, $sMasterSiteUrl );
				$sResponse = Services::HttpRequest()->getContent( $sFinalUrl );
				$aParts = @json_decode( $sResponse, true );

				if ( empty( $aParts ) ) {
					$nErrorCode = 5;
				}
				elseif ( !isset( $aParts[ 'success' ] ) || !$aParts[ 'success' ] ) {

					if ( empty ( $aParts[ 'message' ] ) ) {
						$nErrorCode = 6;
					}
					else {
						$nErrorCode = 7;
						$sSiteResponse = $aParts[ 'message' ]; // This is crap because we can't use Response objects
					}
				}
				elseif ( empty( $aParts[ 'data' ] ) || !is_array( $aParts[ 'data' ] ) ) {
					$nErrorCode = 8;
				}
				else {
					$this->processDataImport( $aParts[ 'data' ], $sMasterSiteUrl );

					// Fix for the overwriting of the Master Site URL with an empty string.
					// Only do so if we're not turning it off. i.e on or no-change
					if ( is_null( $bEnableNetwork ) ) {
						if ( $bHadMasterSiteUrl && !$oOpts->hasImportExportMasterImportUrl() ) {
							$oMod->setImportExportMasterImportUrl( $sOriginalMasterSiteUrl );
						}
					}
					elseif ( $bEnableNetwork === true ) {
						$oMod->setImportExportMasterImportUrl( $sMasterSiteUrl );
						$this->getCon()->fireEvent(
							'master_url_set',
							[ 'audit' => [ 'site' => $sMasterSiteUrl ] ]
						);
					}
					elseif ( $bEnableNetwork === false ) {
						$oMod->setImportExportMasterImportUrl( '' );
					}

					$nErrorCode = 0;
				}
			}
		}

		return $nErrorCode;
	}

	/**
	 * @param array  $aImportData
	 * @param string $sImportSource
	 * @return bool
	 */
	private function processDataImport( $aImportData, $sImportSource = 'unspecified' ) {
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getMod();
		$bImported = false;
		if ( md5( serialize( $aImportData ) ) != $oMod->getImportExportLastImportHash() ) {
			do_action( $oMod->prefix( 'import_options' ), $aImportData );
			$oMod->setImportExportLastImportHash( md5( serialize( $aImportData ) ) );
			$this->getCon()->fireEvent(
				'options_imported',
				[ 'audit' => [ 'site' => $sImportSource ] ]
			);
		}
		return $bImported;
	}
}