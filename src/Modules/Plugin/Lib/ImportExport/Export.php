<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportExport_HandshakeConfirm;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\Handler as ImportExportSitesDB;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\Record as ImportExportSiteRecord;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\LoadIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SiteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SyncSiteUrlValidator;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class Export {

	use PluginControllerConsumer;

	public function run( string $method ) {
		try {
			switch ( $method ) {
				case 'json':
					$this->toJson();
				default:
					throw new \Exception();
			}
		}
		catch ( \Exception $e ) {
		}
		die();
	}

	public function toJson() :void {
		$ieCon = self::con()->comps->import_export;
		$evt = self::con()->comps->events;
		$req = Services::Request();

		$success = false;
		$data = [];

		$repo = new SiteRepository();
		try {
			$repo->ensureLegacyImported( false );
		}
		catch ( \Throwable $e ) {
		}

		$url = (string)Services::Data()->validateSimpleHttpUrl( (string)$req->query( 'url', '' ) );
		$id = (string)$req->query( 'id', '' );
		$repo->recordExportRequested( $url );

		if ( !$this->verifyUrl( $url, $id, (string)$req->query( 'secret', '' ) ) ) {
			$code = 3;
			$msg = __( 'Verification of import-origin failed.', 'wp-simple-firewall' );
			$repo->recordExportFailure( $url, ImportExportSitesDB::EXPORT_RESULT_VERIFY_FAILED, $msg );
		}
		else {
			try {
				$code = 0;
				$data = $this->getExportData();
				$success = true;
				$msg = 'Options Exported Successfully';

				$evt->fireEvent(
					'options_exported',
					[ 'audit_params' => [ 'site' => $url ] ]
				);

				// Only setup the network if we have a valid URL
				$networkOpt = empty( $url ) ? false : $req->query( 'network', '' );

				if ( $networkOpt === 'Y' ) {
					$ieCon->addSyncSiteExportUrl( $url, $id );
				}

				$repo->recordExportSuccess( $url, ImportExportSitesDB::EXPORT_RESULT_SUCCESS, $id );

				if ( $networkOpt === 'Y' ) {
					$evt->fireEvent(
						'whitelist_site_added',
						[ 'audit_params' => [ 'site' => $url ] ]
					);
				}
				elseif ( !empty( $networkOpt ) ) {
					$ieCon->removeSyncSiteExportUrl( $url );
					$evt->fireEvent(
						'whitelist_site_removed',
						[ 'audit_params' => [ 'site' => $url ] ]
					);
				}
			}
			catch ( \Throwable $e ) {
				$code = 4;
				$success = false;
				$data = [];
				$msg = $e->getMessage();
				$repo->recordExportFailure( $url, ImportExportSitesDB::EXPORT_RESULT_EXCEPTION, $msg );
			}
		}

		/**
		 * Send a JSON error response with 403 to also help break caches.
		 */
		wp_send_json( [
			'success' => $success,
			'code'    => $code,
			'message' => $msg,
			'data'    => $data,
		], 403 );
	}

	/**
	 * @return string[]
	 */
	public function toStandardArray() :array {
		$export = \wp_json_encode( $this->getExportData() );
		return [
			'# Site URL: '.Services::WpGeneral()->getHomeUrl(),
			'# Export Date: '.Services::WpGeneral()->getTimeStringForDisplay(),
			'# Hash: '.\hash( 'sha1', $export ),
			$export
		];
	}

	public function toFile() :array {
		return [
			'name'    => sprintf( 'shieldexport-%s-%s.json',
				Services::Data()->urlStripSchema( Services::WpGeneral()->getHomeUrl() ),
				date( 'Ymd_His' )
			),
			'content' => \implode( "\n", $this->toStandardArray() )
		];
	}

	public function getExportData() :array {
		$all = [
			'site_url'      => Services::WpGeneral()->getHomeUrl(),
			'exported_at'   => Services::Request()->ts(),
			'exported_date' => Services::Request()->carbon( true )->toIso8601String(),
			'slug'          => 'wp-simple-firewall',
			'version'       => self::con()->cfg->version(),
			'options'       => $this->getRawOptionsExport(),
		];

		if ( apply_filters( 'shield/export_include_ip_rules', true ) ) {
			$loader = new LoadIpRules();
			$loader->wheres = [
				sprintf( "`ir`.`type`='%s'", self::con()->db_con->ip_rules::T_MANUAL_BYPASS ),
				"`ir`.`can_export`='1'"
			];
			$loader->limit = 100;

			$all[ 'ip_rules' ] = \array_map(
				function ( $rule ) {
					return [
						'ip'    => $rule->ipAsSubnetRange(),
						'label' => $rule->label,
						'type'  => $rule->type,
					];
				},
				$loader->select()
			);
		}

		return $all;
	}

	public function getFullTransferableOptionsExport() :array {
		$all = [];
		foreach ( self::con()->cfg->configuration->transferableOptions() as $optKey => $optDef ) {
			$all[ $optKey ] = self::con()->opts->optGet( $optKey );
		}
		return $all;
	}

	/**
	 * Removes any options marked as to be excluded from import/export
	 */
	public function getRawOptionsExport() :array {
		return \array_diff_key( $this->getFullTransferableOptionsExport(), \array_flip( self::con()->comps->opts_lookup->getXferExcluded() ) );
	}

	/**
	 * Secret-key export remains valid. Otherwise export trust comes from an active sync-site row,
	 * with either a matching import ID or a fresh handshake from that site.
	 */
	private function verifyUrl( string $url, string $id, string $secret ) :bool {
		if ( empty( $url ) ) {
			return false;
		}

		if ( self::con()->comps->import_export->verifySecretKey( $secret ) ) {
			return true;
		}

		$row = ( new SiteRepository() )->findByUrl( $url );
		if ( !$row instanceof ImportExportSiteRecord || !$this->syncSiteRowAllowsExportTrust( $row, $url ) ) {
			return false;
		}

		return ( !empty( $id ) && (string)$row->import_id === $id )
			   || $this->handshake( $url, (string)$row->source === ImportExportSitesDB::SOURCE_MANUAL );
	}

	private function syncSiteRowAllowsExportTrust( ImportExportSiteRecord $row, string $url ) :bool {
		if ( (string)$row->source !== ImportExportSitesDB::SOURCE_MANUAL ) {
			return true;
		}

		try {
			( new SyncSiteUrlValidator() )->validatePublicOutbound( $url );
			return true;
		}
		catch ( \Throwable $e ) {
			return false;
		}
	}

	private function handshake( string $url, bool $rejectUnsafeUrls = false ) :bool {
		$raw = Services::HttpRequest()->getContent(
			URL::Build( $url, ActionData::Build( PluginImportExport_HandshakeConfirm::class, false, [], true ) ),
			$rejectUnsafeUrls ? [ 'reject_unsafe_urls' => true ] : []
		);
		$dec = @\json_decode( $raw, true );
		return \is_array( $dec ) && isset( $dec[ 'success' ] ) && ( $dec[ 'success' ] === true );
	}
}
