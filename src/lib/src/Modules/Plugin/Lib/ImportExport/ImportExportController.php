<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Services\Services;

class ImportExportController {

	use ModConsumer;

	public function run() {
		$oCon = $this->getCon();
		/** @var Plugin\Options $oOpts */
		$oOpts = $this->getOptions();

		// Cron
		add_action( $oCon->prefix( 'importexport_notify' ), function () {
			( new NotifyWhitelist() )
				->setMod( $this->getMod() )
				->run();
		} );

		if ( $oOpts->hasImportExportMasterImportUrl() ) {
			// For auto update whitelist notifications:
			add_action( $oCon->prefix( 'importexport_updatenotified' ), function () {
				( new Import() )
					->setMod( $this->getMod() )
					->run( 'site' );
			} );
		}

		add_action( $oCon->prefix( 'shield_action' ), function ( $sAction ) {
			switch ( $sAction ) {
				case 'importexport_export':
					( new Export() )
						->setMod( $this->getMod() )
						->run( Services::Request()->query( 'method' ) );
					break;

				case 'importexport_import':
					( new Import() )
						->setMod( $this->getMod() )
						->run( Services::Request()->query( 'method' ) );
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

	/**
	 * We've been notified that there's an update to pull in from the master site so we set a cron to do this.
	 */
	private function runOptionsUpdateNotified() {
		$oCon = $this->getCon();
		/** @var Plugin\Options $oOpts */
		$oOpts = $this->getOptions();

		$sCronHook = $oCon->prefix( 'importexport_updatenotified' );
		if ( wp_next_scheduled( $sCronHook ) ) {
			wp_clear_scheduled_hook( $sCronHook );
		}

		if ( !wp_next_scheduled( $sCronHook ) ) {

			wp_schedule_single_event( Services::Request()->ts() + 12, $sCronHook );

			preg_match( '#.*WordPress/.*\s+(.*)\s?#', Services::Request()->getUserAgent(), $aMatches );
			if ( !empty( $aMatches[ 1 ] ) && filter_var( $aMatches[ 1 ], FILTER_VALIDATE_URL ) ) {
				$sUrl = parse_url( $aMatches[ 1 ], PHP_URL_HOST );
				if ( !empty( $sUrl ) ) {
					$sUrl = 'Site: '.$sUrl;
				}
			}
			else {
				$sUrl = '';
			}

			$this->getCon()->fireEvent(
				'import_notify_received',
				[ 'audit' => [ 'master_site' => $oOpts->getImportExportMasterImportUrl() ] ]
			);
		}
	}

	/**
	 * This is called from a remote site when this site sends out an export request to another
	 * site but without a secret key i.e. it assumes it's on the white list. We give a 30 second
	 * window for the handshake to complete.  We do not explicitly fail.
	 */
	private function confirmExportHandshake() {
		/** @var Plugin\Options $oOpts */
		$oOpts = $this->getOptions();
		if ( Services::Request()->ts() < (int)$oOpts->getOpt( 'importexport_handshake_expires_at' ) ) {
			echo json_encode( [ 'success' => true ] );
			die();
		}
		else {
			return;
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
				'tab_by_file'           => __( 'Import From File', 'wp-simple-firewall' ),
				'tab_by_site'           => __( 'Import From Another Site', 'wp-simple-firewall' ),
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
}