<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Services\Services;

class Import {

	use ModConsumer;

	/**
	 * @param string $sMethod
	 */
	public function run( $sMethod = 'site' ) {
		try {
			switch ( $sMethod ) {
				case 'file':
					$this->fromFile();
					break;
				case 'site':
				default:
					$this->fromSite();
					break;
			}
		}
		catch ( \Exception $oE ) {
		}
		die();
	}

	/**
	 * @throws \Exception
	 */
	public function fromFile() {
		if ( !$this->getCon()->isPluginAdmin() ) {
			throw new \Exception( __( 'Not currently logged-in as security admin', 'wp-simple-firewall' ) );
		}

		if ( Services::Request()->post( 'confirm' ) != 'Y' ) {
			throw new \Exception( __( 'Please check the box to confirm your intent to overwrite settings', 'wp-simple-firewall' ) );
		}

		$oFs = Services::WpFs();
		if ( empty( $_FILES ) || !isset( $_FILES[ 'import_file' ] )
			 || empty( $_FILES[ 'import_file' ][ 'tmp_name' ] ) ) {
			throw new \Exception( __( 'Please select a file to upload', 'wp-simple-firewall' ) );
		}
		if ( $_FILES[ 'import_file' ][ 'size' ] == 0
			 || isset( $_FILES[ 'error' ] ) && $_FILES[ 'error' ] != UPLOAD_ERR_OK
			 || !$oFs->isFile( $_FILES[ 'import_file' ][ 'tmp_name' ] )
			 || filesize( $_FILES[ 'import_file' ][ 'tmp_name' ] ) === 0
		) {
			throw new \Exception( __( 'Uploading of file failed', 'wp-simple-firewall' ) );
		}

		$sContent = Services::WpFs()->getFileContent( $_FILES[ 'import_file' ][ 'tmp_name' ] );
		if ( empty( $sContent ) ) {
			throw new \Exception( __( 'Uploaded file was empty', 'wp-simple-firewall' ) );
		}

		{//filter any comment lines
			$aParts = array_filter(
				array_map( 'trim', explode( "\n", $sContent ) ),
				function ( $sLine ) {
					return ( strpos( $sLine, '{' ) === 0 );
				}
			);
			if ( empty( $aParts ) ) {
				throw new \Exception( __( 'Options data could not be found in uploaded file', 'wp-simple-firewall' ) );
			}
		}
		{//parse the options json
			$aData = @json_decode( array_shift( $aParts ), true );
			if ( empty( $aData ) || !is_array( $aData ) ) {
				throw new \Exception( __( 'Uploaded options data was not of the correct format', 'wp-simple-firewall' ) );
			}
		}

		$this->processDataImport( $aData, __( 'uploaded file', 'wp-simple-firewall' ) );
		$oFs->deleteFile( $_FILES[ 'import_file' ][ 'tmp_name' ] );
	}

	/**
	 * @param string    $sMasterSiteUrl
	 * @param string    $sSecretKey
	 * @param bool|null $bEnableNetwork
	 * @param string    $sSiteResponse
	 * @return int
	 */
	public function fromSite( $sMasterSiteUrl = '', $sSecretKey = '', $bEnableNetwork = null, &$sSiteResponse = '' ) {
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