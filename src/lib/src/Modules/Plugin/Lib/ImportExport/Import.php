<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportExport_Export;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\AddRule;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class Import {

	use PluginControllerConsumer;

	/**
	 * @throws \Exception
	 */
	public function fromFile( string $path, bool $delete = true ) {
		$FS = Services::WpFs();

		if ( !$FS->isAccessibleFile( $path ) ) {
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
			$parts = \array_filter(
				\array_map( '\trim', \explode( "\n", $content ) ),
				function ( $line ) {
					return \str_starts_with( $line, '{' );
				}
			);
			if ( empty( $parts ) ) {
				throw new \Exception( __( 'Options data could not be found in uploaded file', 'wp-simple-firewall' ) );
			}
		}
		{//parse the options json
			$data = @\json_decode( \array_shift( $parts ), true );
			if ( empty( $data ) || !\is_array( $data ) ) {
				throw new \Exception( __( "Options data in the file wasn't of the correct format.", 'wp-simple-firewall' ) );
			}
		}

		$this->processDataImport( $data, __( 'import file', 'wp-simple-firewall' ) );
	}

	/**
	 * @throws \Exception
	 */
	public function fromFileUpload() {
		if ( !self::con()->isPluginAdmin() ) {
			throw new \Exception( __( 'Not currently logged-in as security admin.', 'wp-simple-firewall' ) );
		}
		if ( Services::Request()->post( 'confirm' ) != 'Y' ) {
			throw new \Exception( __( 'Please check the box to confirm.', 'wp-simple-firewall' ) );
		}

		$FS = Services::WpFs();
		if ( empty( $_FILES ) || !isset( $_FILES[ 'import_file' ] ) || empty( $_FILES[ 'import_file' ][ 'tmp_name' ] ) ) {
			throw new \Exception( __( 'Please select a file to upload', 'wp-simple-firewall' ) );
		}

		if ( isset( $_FILES[ 'error' ] ) && $_FILES[ 'error' ] != UPLOAD_ERR_OK
			 || !$FS->isAccessibleFile( $_FILES[ 'import_file' ][ 'tmp_name' ] ) ) {
			throw new \Exception( __( 'Uploading of file failed', 'wp-simple-firewall' ) );
		}

		if ( $_FILES[ 'import_file' ][ 'size' ] == 0 || filesize( $_FILES[ 'import_file' ][ 'tmp_name' ] ) === 0 ) {
			throw new \Exception( __( "The file appears to be empty or couldn't be uploaded properly", 'wp-simple-firewall' ) );
		}

		$this->fromFile( $_FILES[ 'import_file' ][ 'tmp_name' ] );
	}

	public function autoImportFromMaster() {
		try {
			$this->fromSite();
		}
		catch ( \Exception $e ) {
		}
	}

	/**
	 * @throws \Exception
	 */
	public function fromSite( string $masterURL = '', string $secretKey = '', ?bool $enableNetwork = null ) :void {
		$optsCon = self::con()->opts;

		if ( empty( $masterURL ) ) {
			$masterURL = self::con()->comps->import_export->getImportExportMasterImportUrl();
			if ( empty( $masterURL ) ) {
				throw new \Exception( "No Master Site URL provided.", 4 );
			}
		}

		$originalMasterSiteURL = $masterURL;
		$secretKey = sanitize_key( $secretKey );

		if ( !empty( $secretKey ) && \strlen( $secretKey ) !== 40 ) {
			throw new \Exception( "Secret key isn't of the correct format", 2 );
		}

		// Ensure we have entries for 'scheme' and 'host'
		$urlParts = wp_parse_url( \strtolower( $masterURL ) );
		$hasParts = !empty( $urlParts )
					&& \count(
						   \array_filter( \array_intersect_key(
							   $urlParts,
							   \array_flip( [ 'scheme', 'host' ] )
						   ) )
					   ) === 2;

		if ( !$hasParts ) {
			throw new \Exception( "Master Site doesn't appear to be a valid URL.", 4 );
		}
		if ( !\preg_match( '#^https?$#', $urlParts[ 'scheme' ] ) ) {
			throw new \Exception( "Master Site URL doesn't contain 'http' or 'https'.", 4 );
		}
		$masterURL = Services::Data()->validateSimpleHttpUrl( $masterURL ); // final clean
		if ( empty( $masterURL ) ) {
			throw new \Exception( "Couldn't validate the URL.", 4 );
		}

		// Begin the handshake process.
		$optsCon->optSet(
			'importexport_handshake_expires_at',
			Services::Request()->carbon()->addMinutes( 20 )->timestamp
		);

		$optsCon->store();

		// Don't send the network setup request if it's the cron.
		$data = [
			'secret' => $secretKey,
			'url'    => Services::WpGeneral()->getHomeUrl(),
			'id'     => $this->getImportID(),
			'method' => 'json',
		];
		if ( !\is_null( $enableNetwork ) && !Services::WpGeneral()->isCron() ) {
			$data[ 'network' ] = $enableNetwork ? 'Y' : 'N';
		}

		// Bust caches on the target export site
		$data[ 'uniq' ] = wp_generate_password( 4, false );

		{ // Send the export request
			$targetExportURL = self::con()->plugin_urls->noncedPluginAction(
				PluginImportExport_Export::class,
				$masterURL,
				$data
			);

			add_filter( 'http_request_host_is_external', '\__return_true', 11 );
			$response = @\json_decode( Services::HttpRequest()->getContent( $targetExportURL ), true );
			remove_filter( 'http_request_host_is_external', '\__return_true', 11 );
			if ( empty( $response ) ) {
				throw new \Exception( "Request failed as we couldn't parse the response.", 5 );
			}
		}

		if ( empty( $response[ 'success' ] ) ) {

			if ( empty ( $response[ 'message' ] ) ) {
				throw new \Exception( "Request failed with no error message from the source site.", 6 );
			}
			else {
				throw new \Exception( $response[ 'message' ], 7 );
			}
		}

		if ( empty( $response[ 'data' ] ) || !\is_array( $response[ 'data' ] ) ) {
			throw new \Exception( "Response data was empty", 8 );
		}

		$this->processDataImport( $response[ 'data' ], $masterURL );

		// Fix for the overwriting of the Master Site URL with an empty string.
		// Only do so if we're not turning it off. i.e on or no-change
		if ( $enableNetwork === true ) {
			$optsCon->optSet( 'importexport_masterurl', $masterURL );
			self::con()->fireEvent(
				'master_url_set',
				[ 'audit_params' => [ 'site' => $masterURL ] ]
			);
		}
		elseif ( $enableNetwork === false ) {
			$optsCon->optSet( 'importexport_masterurl', '' );
		}
		else {
			// restore the original setting
			$optsCon->optSet( 'importexport_masterurl', $originalMasterSiteURL );
		}

		// store & clean the master URL
		$optsCon->store();
	}

	private function processDataImport( array $data, string $source = 'unspecified' ) {
		$con = self::con();
		$opts = $con->opts;

		foreach ( \array_diff_key( $data[ 'options' ] ?? [], \array_flip( $con->comps->opts_lookup->getXferExcluded() ) ) as $optKey => $value ) {
			$opts->optSet( $optKey, $value );
		}

		if ( $opts->hasChanges() ) {
			self::con()->fireEvent(
				'options_imported',
				[ 'audit_params' => [ 'site' => $source ] ]
			);
		}

		$opts->store();

		if ( !empty( $data[ 'ip_rules' ] ) ) {
			$dbh = self::con()->db_con->ip_rules;
			$now = Services::Request()->ts();
			foreach ( $data[ 'ip_rules' ] as $rule ) {
				try {
					if ( ( $rule[ 'type' ] ?? '' ) === $dbh::T_MANUAL_BYPASS ) {
						( new AddRule() )->toManualWhitelist(
							sprintf( '%s- %s', __( 'Imported', 'wp-simple-firewall' ), $rule[ 'label' ] ),
							[
								'imported_at' => $now,
							]
						);
					}
				}
				catch ( \Exception $e ) {
				}
			}
		}
	}

	private function getImportID() :string {
		$id = self::con()->opts->optGet( 'import_id' );
		if ( empty( $id ) ) {
			$id = \bin2hex( \random_bytes( 8 ) );
			self::con()
				->opts
				->optSet( 'import_id', $id )
				->store();
		}
		return $id;
	}
}