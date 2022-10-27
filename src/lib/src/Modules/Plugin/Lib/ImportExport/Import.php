<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class Import {

	use ModConsumer;

	public function run( string $method = 'site' ) {
		try {
			switch ( $method ) {
				case 'file':
					$this->fromFileUpload();
					break;
				case 'site':
				default:
					$this->fromSite();
					break;
			}
		}
		catch ( \Exception $e ) {
		}
		die();
	}

	/**
	 * @param string $path
	 * @param bool   $delete
	 * @throws \Exception
	 */
	public function fromFile( string $path, bool $delete = true ) {
		$FS = Services::WpFs();

		if ( !$FS->isFile( $path ) ) {
			throw new \Exception( "The import file specified isn't a valid file." );
		}

		$content = $FS->getFileContent( $path );
		if ( $delete ) {
			$FS->deleteFile( $path );
			if ( $FS->exists( $path ) ) {
				throw new \Exception( __( 'Not importing a file that cannot be deleted', 'wp-simple-firewall' ) );
			}
		}

		if ( empty( $content ) ) {
			throw new \Exception( __( 'Import file was empty', 'wp-simple-firewall' ) );
		}

		{//filter any comment lines
			$parts = array_filter(
				array_map( 'trim', explode( "\n", $content ) ),
				function ( $line ) {
					return ( strpos( $line, '{' ) === 0 );
				}
			);
			if ( empty( $parts ) ) {
				throw new \Exception( __( 'Options data could not be found in uploaded file', 'wp-simple-firewall' ) );
			}
		}
		{//parse the options json
			$data = @json_decode( array_shift( $parts ), true );
			if ( empty( $data ) || !is_array( $data ) ) {
				throw new \Exception( __( "Options data in the file wasn't of the correct format.", 'wp-simple-firewall' ) );
			}
		}

		$this->processDataImport( $data, __( 'import file', 'wp-simple-firewall' ) );
	}

	/**
	 * @throws \Exception
	 */
	public function fromFileUpload() {
		if ( !$this->getCon()->isPluginAdmin() ) {
			throw new \Exception( __( 'Not currently logged-in as security admin', 'wp-simple-firewall' ) );
		}
		if ( Services::Request()->post( 'confirm' ) != 'Y' ) {
			throw new \Exception( __( 'Please check the box to confirm your intent to overwrite settings', 'wp-simple-firewall' ) );
		}

		$FS = Services::WpFs();
		if ( empty( $_FILES ) || !isset( $_FILES[ 'import_file' ] )
			 || empty( $_FILES[ 'import_file' ][ 'tmp_name' ] ) ) {
			throw new \Exception( __( 'Please select a file to upload', 'wp-simple-firewall' ) );
		}
		if ( $_FILES[ 'import_file' ][ 'size' ] == 0
			 || isset( $_FILES[ 'error' ] ) && $_FILES[ 'error' ] != UPLOAD_ERR_OK
			 || !$FS->isFile( $_FILES[ 'import_file' ][ 'tmp_name' ] )
			 || filesize( $_FILES[ 'import_file' ][ 'tmp_name' ] ) === 0
		) {
			throw new \Exception( __( 'Uploading of file failed', 'wp-simple-firewall' ) );
		}

		$this->fromFile( $_FILES[ 'import_file' ][ 'tmp_name' ] );
	}

	/**
	 * @param bool|null $isEnableNetwork
	 * @return int
	 * @throws \Exception
	 */
	public function fromSite( string $masterURL = '', string $secretKey = '', $isEnableNetwork = null ) {
		/** @var Plugin\Options $opts */
		$opts = $this->getOptions();
		/** @var Plugin\ModCon $mod */
		$mod = $this->getMod();
		$DP = Services::Data();

		if ( empty( $masterURL ) ) {
			$masterURL = $opts->getImportExportMasterImportUrl();
		}

		$originalMasterSiteURL = $opts->getImportExportMasterImportUrl();
		$hadMasterSiteUrl = $opts->hasImportExportMasterImportUrl();
		$secretKey = sanitize_key( $secretKey );

		if ( !$hadMasterSiteUrl ) {
			if ( empty( $secretKey ) ) {
				throw new \Exception( 'Empty secret key', 1 );
			}
			if ( strlen( $secretKey ) !== 40 ) {
				throw new \Exception( "Secret key isn't of the correct format", 2 );
			}
		}

		// Ensure we have entries for 'scheme' and 'host'
		$urlParts = wp_parse_url( $masterURL );
		$hasParts = !empty( $urlParts )
					&& count(
						   array_filter( array_intersect_key(
							   $urlParts,
							   array_flip( [ 'scheme', 'host' ] )
						   ) )
					   ) === 2;
		if ( !$hasParts ) {
			throw new \Exception( "Couldn't parse the URL.", 4 );
		}
		$masterURL = $DP->validateSimpleHttpUrl( $masterURL ); // final clean
		if ( empty( $masterURL ) ) {
			throw new \Exception( "Couldn't validate the URL.", 4 );
		}

		// Begin the handshake process.
		$opts->setOpt( 'importexport_handshake_expires_at', Services::Request()->ts() + 30 );
		$mod->saveModOptions();

		$data = [
			'shield_action' => 'importexport_export',
			'secret'        => $secretKey,
			'url'           => Services::WpGeneral()->getHomeUrl(),
			'id'            => $this->getImportID(),
		];
		// Don't send the network setup request if it's the cron.
		if ( !is_null( $isEnableNetwork ) && !Services::WpGeneral()->isCron() ) {
			$data[ 'network' ] = $isEnableNetwork ? 'Y' : 'N';
		}

		{ // Make the request
			add_filter( 'http_request_host_is_external', '__return_true', 11 );
			$response = @json_decode( Services::HttpRequest()->getContent( URL::Build( $masterURL, $data ) ), true );
			remove_filter( 'http_request_host_is_external', '__return_true', 11 );
			if ( empty( $response ) ) {
				throw new \Exception( "Request failed as we couldn't parse the response.", 5 );
			}
		}

		if ( empty( $response[ 'success' ] ) ) {

			if ( empty ( $response[ 'message' ] ) ) {
				throw new \Exception( "Request failed with no error message from the source site.", 6 );
			}
			else {
				throw new \Exception( "Request failed with error message from the source site: ".$response[ 'message' ], 7 );
			}
		}

		if ( empty( $response[ 'data' ] ) || !is_array( $response[ 'data' ] ) ) {
			throw new \Exception( "Response data was empty", 8 );
		}

		$this->processDataImport( $response[ 'data' ], $masterURL );

		// Fix for the overwriting of the Master Site URL with an empty string.
		// Only do so if we're not turning it off. i.e on or no-change
		if ( is_null( $isEnableNetwork ) ) {
			if ( $hadMasterSiteUrl && !$opts->hasImportExportMasterImportUrl() ) {
				$opts->setOpt( 'importexport_masterurl', $originalMasterSiteURL );
			}
		}
		elseif ( $isEnableNetwork === true ) {
			$opts->setOpt( 'importexport_masterurl', $masterURL );
			$this->getCon()->fireEvent(
				'master_url_set',
				[ 'audit_params' => [ 'site' => $masterURL ] ]
			);
		}
		elseif ( $isEnableNetwork === false ) {
			$opts->setOpt( 'importexport_masterurl', '' );
		}
		// store & clean the master URL
		$mod->saveModOptions();

		return 0;
	}

	private function processDataImport( array $data, string $source = 'unspecified' ) {

		$anythingChanged = false;
		foreach ( $this->getCon()->modules as $mod ) {
			if ( !empty( $data[ $mod->getOptionsStorageKey() ] ) ) {
				$oTheseOpts = $mod->getOptions();
				$oTheseOpts->setMultipleOptions(
					array_diff_key(
						$data[ $mod->getOptionsStorageKey() ] ?? [],
						array_flip( $oTheseOpts->getXferExcluded() )
					)
				);

				$anythingChanged = $anythingChanged || $oTheseOpts->getNeedSave();
				$mod->saveModOptions( true );
			}
		}

		if ( $anythingChanged ) {
			$this->getCon()->fireEvent(
				'options_imported',
				[ 'audit_params' => [ 'site' => $source ] ]
			);
		}
	}

	private function getImportID() :string {
		$opts = $this->getOptions();
		$id = $opts->getOpt( 'import_id' );
		if ( empty( $id ) ) {
			$id = bin2hex( random_bytes( 8 ) );
			$opts->setOpt( 'import_id', $id );
			$this->getMod()->saveModOptions();
		}
		return $id;
	}
}