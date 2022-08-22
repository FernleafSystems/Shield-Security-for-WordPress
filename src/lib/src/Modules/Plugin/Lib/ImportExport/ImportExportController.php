<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Options;
use FernleafSystems\Wordpress\Services\Services;

class ImportExportController extends Shield\Modules\Base\Common\ExecOnceModConsumer {

	use Shield\Crons\PluginCronsConsumer;

	protected function canRun() :bool {
		return $this->getOptions()->isOpt( 'importexport_enable', 'Y' ) && $this->getCon()->isPremiumActive();
	}

	protected function run() {
		$this->setupHooks();
		$this->setupCronHooks();
	}

	private function setupHooks() {
		$con = $this->getCon();
		/** @var Plugin\Options $opts */
		$opts = $this->getOptions();

		$this->getImportExportSecretKey();

		// Cron
		add_action( $con->prefix( 'importexport_notify' ), function () {
			( new NotifyWhitelist() )
				->setMod( $this->getMod() )
				->execute();
		} );

		add_action( 'shield/plugin_activated', function () {
			$this->importFromFlag();
		} );

		if ( $opts->hasImportExportMasterImportUrl() ) {
			// For auto update whitelist notifications:
			add_action( $con->prefix( 'importexport_updatenotified' ), function () {
				( new Import() )
					->setMod( $this->getMod() )
					->run( 'site' );
			} );
		}

		add_action( $con->prefix( 'shield_action' ), function ( $action ) {
			switch ( $action ) {
				case 'importexport_export':
					( new Export() )
						->setMod( $this->getMod() )
						->run( (string)Services::Request()->query( 'method' ) );
					break;

				case 'importexport_import':
					( new Import() )
						->setMod( $this->getMod() )
						->run( (string)Services::Request()->query( 'method' ) );
					break;

				case 'importexport_handshake':
					$this->confirmExportHandshake();
					break;

				case 'importexport_updatenotified':
					$this->runOptionsUpdateNotified();
					break;
			}
		} );
	}

	public function addUrlToImportExportWhitelistUrls( string $url ) {
		/** @var Plugin\Options $opts */
		$opts = $this->getOptions();
		$url = Services::Data()->validateSimpleHttpUrl( $url );
		if ( $url !== false ) {
			$urls = $opts->getImportExportWhitelist();
			$urls[] = $url;
			$opts->setOpt( 'importexport_whitelist', $urls );
			$this->getMod()->saveModOptions();
		}
	}

	public function removeUrlFromImportExportWhitelistUrls( string $url ) {
		/** @var Plugin\Options $opts */
		$opts = $this->getOptions();
		$url = Services::Data()->validateSimpleHttpUrl( $url );
		if ( $url !== false ) {
			$urls = $opts->getImportExportWhitelist();
			$key = array_search( $url, $urls );
			if ( $key !== false ) {
				unset( $urls[ $key ] );
			}
			$opts->setOpt( 'importexport_whitelist', $urls );
			$this->getMod()->saveModOptions();
		}
	}

	protected function getImportExportSecretKey() :string {
		/** @var Plugin\Options $opts */
		$opts = $this->getOptions();
		$ID = $opts->getOpt( 'importexport_secretkey', '' );
		if ( empty( $ID ) || $this->isImportExportSecretKeyExpired() ) {
			$ID = sha1( $this->getCon()->getInstallationID()[ 'id' ].wp_rand( 0, PHP_INT_MAX ) );
			$opts->setOpt( 'importexport_secretkey', $ID )
				 ->setOpt( 'importexport_secretkey_expires_at', Services::Request()->ts() + HOUR_IN_SECONDS );
		}
		return $ID;
	}

	public function verifySecretKey( string $secret ) :bool {
		return !empty( $secret ) && $this->getImportExportSecretKey() == $secret;
	}

	protected function isImportExportSecretKeyExpired() :bool {
		return Services::Request()->ts() > $this->getOptions()->getOpt( 'importexport_secretkey_expires_at' );
	}

	private function importFromFlag() {
		try {
			( new Import() )
				->setMod( $this->getMod() )
				->fromFile( $this->getCon()->paths->forFlag( 'import.json' ) );
		}
		catch ( \Exception $e ) {
		}
	}

	/**
	 * We've been notified that there's an update to pull in from the master site so we set a cron to do this.
	 */
	private function runOptionsUpdateNotified() {
		$con = $this->getCon();
		/** @var Plugin\Options $opts */
		$opts = $this->getOptions();

		$cronHook = $con->prefix( 'importexport_updatenotified' );
		if ( wp_next_scheduled( $cronHook ) ) {
			wp_clear_scheduled_hook( $cronHook );
		}

		if ( !wp_next_scheduled( $cronHook ) ) {

			wp_schedule_single_event( Services::Request()->ts() + 12, $cronHook );

			preg_match( '#.*WordPress/.*\s+(.*)\s?#', Services::Request()->getUserAgent(), $aMatches );
			if ( !empty( $aMatches[ 1 ] ) && filter_var( $aMatches[ 1 ], FILTER_VALIDATE_URL ) ) {
				$url = parse_url( $aMatches[ 1 ], PHP_URL_HOST );
				if ( !empty( $url ) ) {
					$url = 'Site: '.$url;
				}
			}
			else {
				$url = '';
			}

			$con->fireEvent(
				'import_notify_received',
				[ 'audit_params' => [ 'master_site' => $opts->getImportExportMasterImportUrl() ] ]
			);
		}
	}

	/**
	 * This is called from a remote site when this site sends out an export request to another
	 * site but without a secret key i.e. it assumes it's on the white list. We give a 30 second
	 * window for the handshake to complete.  We do not explicitly fail.
	 */
	private function confirmExportHandshake() {
		/** @var Plugin\Options $opts */
		$opts = $this->getOptions();
		if ( Services::Request()->ts() < (int)$opts->getOpt( 'importexport_handshake_expires_at' ) ) {
			echo json_encode( [ 'success' => true ] );
			die();
		}
	}

	public function runDailyCron() {
		/** @var Options $opts */
		$opts = $this->getOptions();
		try {
			( new Import() )
				->setMod( $this->getMod() )
				->fromSite( $opts->getImportExportMasterImportUrl() );
		}
		catch ( \Exception $e ) {
		}
	}

	public function buildInsightsVars() :array {
		/** @var Plugin\ModCon $mod */
		$mod = $this->getMod();
		return [
			'vars'    => [
				'file_upload_nonce' => $mod->getNonceActionData( 'import_file_upload' ),
				'form_action'       => $mod->getUrl_AdminPage(),
			],
			'ajax'    => [
				'import_from_site' => $mod->getAjaxActionData( 'import_from_site', true ),
			],
			'flags'   => [
				'can_importexport' => $this->getCon()->isPremiumActive(),
			],
			'hrefs'   => [
				'export_file_download' => $mod->createFileDownloadLink( 'plugin_export' )
			],
			'strings' => [
				'tab_by_file'          => __( 'Import From File', 'wp-simple-firewall' ),
				'tab_by_site'          => __( 'Import From Another Site', 'wp-simple-firewall' ),
				'title_import_file'    => __( 'Import From File', 'wp-simple-firewall' ),
				'subtitle_import_file' => __( 'Upload an exported options file you downloaded from another site', 'wp-simple-firewall' ),
				'select_import_file'   => __( 'Select file to import options from', 'wp-simple-firewall' ),
				'i_understand'         => __( 'I Understand Existing Options Will Be Overwritten', 'wp-simple-firewall' ),
				'be_sure'              => __( 'Please be sure that this is what you want.', 'wp-simple-firewall' ),
				'not_undone'           => __( "This action can't be undone.", 'wp-simple-firewall' ),
				'title_import_site'    => __( "Import From Site", 'wp-simple-firewall' ),

				'title_download_file'    => __( 'Download Options Export File', 'wp-simple-firewall' ),
				'subtitle_download_file' => __( 'Use this file to copy options from this site into another site', 'wp-simple-firewall' ),

				'subtitle_import_site' => __( 'Import options directly from another site', 'wp-simple-firewall' ),
				'master_site_url'      => __( 'Master Site URL', 'wp-simple-firewall' ),
				'remember_include'     => sprintf(
					__( 'Remember to include %s or %s', 'wp-simple-firewall' ),
					'<code>https://</code>',
					'<code>http://</code>'
				),
				'secret_key'           => __( 'Secret Key', 'wp-simple-firewall' ),
				'master_site_key'      => __( 'Master Site Secret Key', 'wp-simple-firewall' ),
				'create_network'       => __( 'Create Shield Network', 'wp-simple-firewall' ),
				'key_found_under'      => sprintf( __( 'The secret key is found in: %s', 'wp-simple-firewall' ),
					ucwords( sprintf( '%s > %s > %s ', __( 'General Settings', 'wp-simple-firewall' ), __( 'Import/Export', 'wp-simple-firewall' ), __( 'Secret Key', 'wp-simple-firewall' ) ) )
				),
				'turn_on'              => __( 'Turn On', 'wp-simple-firewall' ),
				'turn_off'             => __( 'Turn Off', 'wp-simple-firewall' ),
				'no_change'            => __( 'No Change', 'wp-simple-firewall' ),
				'network_explain'      => [
					__( 'Checking this option on will link this site to Master site.', 'wp-simple-firewall' ),
					__( 'Options will be automatically imported from the Master site each night', 'wp-simple-firewall' ),
					__( 'When you adjust options on the Master site, they will be reflected in this site after the automatic import', 'wp-simple-firewall' ),
				],
				'import_options'       => __( 'Import Options', 'wp-simple-firewall' ),
			]
		];
	}
}