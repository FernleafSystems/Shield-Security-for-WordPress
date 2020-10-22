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
					$this->fromFileUpload();
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
	 * @param string $sPath
	 * @throws \Exception
	 */
	public function fromFile( $sPath ) {
		if ( !$this->getCon()->isPluginAdmin() ) {
			throw new \Exception( __( 'Not currently logged-in as security admin', 'wp-simple-firewall' ) );
		}

		$sContent = Services::WpFs()->getFileContent( $sPath );
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
				throw new \Exception( __( "Options data in the file wasn't of the correct format.", 'wp-simple-firewall' ) );
			}
		}

		$this->processDataImport( $aData, __( 'uploaded file', 'wp-simple-firewall' ) );
	}

	/**
	 * @throws \Exception
	 */
	public function fromFileUpload() {
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

		$this->fromFile( $_FILES[ 'import_file' ][ 'tmp_name' ] );

		$oFs->deleteFile( $_FILES[ 'import_file' ][ 'tmp_name' ] );
	}

	/**
	 * @param string    $sMasterSiteUrl
	 * @param string    $sSecretKey
	 * @param bool|null $bEnableNetwork
	 * @return int
	 * @throws \Exception
	 */
	public function fromSite( $sMasterSiteUrl = '', $sSecretKey = '', $bEnableNetwork = null ) {
		/** @var Plugin\Options $oOpts */
		$oOpts = $this->getOptions();
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $mod */
		$mod = $this->getMod();
		$oDP = Services::Data();

		if ( empty( $sMasterSiteUrl ) ) {
			$sMasterSiteUrl = $oOpts->getImportExportMasterImportUrl();
		}

		$sOriginalMasterSiteUrl = $oOpts->getImportExportMasterImportUrl();
		$bHadMasterSiteUrl = $oOpts->hasImportExportMasterImportUrl();
		$bCheckKeyFormat = !$bHadMasterSiteUrl;
		$sSecretKey = sanitize_key( $sSecretKey );

		if ( $bCheckKeyFormat ) {
			if ( empty( $sSecretKey ) ) {
				throw new \Exception( 'Empty secret key', 1 );
			}
			if ( strlen( $sSecretKey ) != 40 ) {
				throw new \Exception( "Secret key isn't of the correct format", 2 );
			}
		}

		// Ensure we have entries for 'scheme' and 'host'
		$aUrlParts = wp_parse_url( $sMasterSiteUrl );
		$bHasParts = !empty( $aUrlParts )
					 && count(
							array_filter( array_intersect_key(
								$aUrlParts,
								array_flip( [ 'scheme', 'host' ] )
							) )
						) === 2;
		if ( !$bHasParts ) {
			throw new \Exception( "Couldn't parse the URL into its parts", 4 );
		}
		$sMasterSiteUrl = $oDP->validateSimpleHttpUrl( $sMasterSiteUrl ); // final clean
		if ( empty( $sMasterSiteUrl ) ) {
			throw new \Exception( "Couldn't validate the URL.", 4 );
		}

		// Begin the handshake process.
		$oOpts->setOpt(
			'importexport_handshake_expires_at',
			Services::Request()->ts() + 30
		);
		$this->getMod()->saveModOptions();

		$aData = [
			'shield_action' => 'importexport_export',
			'secret'        => $sSecretKey,
			'url'           => Services::WpGeneral()->getHomeUrl()
		];
		// Don't send the network setup request if it's the cron.
		if ( !is_null( $bEnableNetwork ) && !Services::WpGeneral()->isCron() ) {
			$aData[ 'network' ] = $bEnableNetwork ? 'Y' : 'N';
		}

		{ // Make the request
			$sFinalUrl = add_query_arg( $aData, $sMasterSiteUrl );
			$sResponse = Services::HttpRequest()->getContent( $sFinalUrl );
			$aResponse = @json_decode( $sResponse, true );

			if ( empty( $aResponse ) ) {
				throw new \Exception( "Request failed as we couldn't parse the response.", 5 );
			}
		}

		if ( empty( $aResponse[ 'success' ] ) ) {

			if ( empty ( $aResponse[ 'message' ] ) ) {
				throw new \Exception( "Request failed with no error message from the source site.", 6 );
			}
			else {
				throw new \Exception( "Request failed with error message from the source site: ".$aResponse[ 'message' ], 7 );
			}
		}

		if ( empty( $aResponse[ 'data' ] ) || !is_array( $aResponse[ 'data' ] ) ) {
			throw new \Exception( "Response data was empty", 8 );
		}

		$this->processDataImport( $aResponse[ 'data' ], $sMasterSiteUrl );

		// Fix for the overwriting of the Master Site URL with an empty string.
		// Only do so if we're not turning it off. i.e on or no-change
		if ( is_null( $bEnableNetwork ) ) {
			if ( $bHadMasterSiteUrl && !$oOpts->hasImportExportMasterImportUrl() ) {
				$mod->setImportExportMasterImportUrl( $sOriginalMasterSiteUrl );
			}
		}
		elseif ( $bEnableNetwork === true ) {
			$mod->setImportExportMasterImportUrl( $sMasterSiteUrl );
			$this->getCon()->fireEvent(
				'master_url_set',
				[ 'audit' => [ 'site' => $sMasterSiteUrl ] ]
			);
		}
		elseif ( $bEnableNetwork === false ) {
			$mod->setImportExportMasterImportUrl( '' );
		}

		return 0;
	}

	/**
	 * @param array  $aImportData
	 * @param string $sImportSource
	 * @return bool
	 */
	private function processDataImport( $aImportData, $sImportSource = 'unspecified' ) {
		$bImported = false;

		$bAnythingChanged = false;
		foreach ( $this->getCon()->modules as $mod ) {
			if ( !empty( $aImportData[ $mod->getOptionsStorageKey() ] ) ) {
				$oTheseOpts = $mod->getOptions();
				$oTheseOpts->setMultipleOptions(
					array_diff_key(
						$aImportData[ $mod->getOptionsStorageKey() ],
						array_flip( $oTheseOpts->getXferExcluded() )
					)
				);

				$bAnythingChanged = $bAnythingChanged || $oTheseOpts->getNeedSave();
				$mod->saveModOptions( true );
			}
		}

		if ( $bAnythingChanged ) {
			$this->getCon()->fireEvent(
				'options_imported',
				[ 'audit' => [ 'site' => $sImportSource ] ]
			);
		}

		return $bImported;
	}
}