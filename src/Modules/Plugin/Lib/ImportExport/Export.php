<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportExport_HandshakeConfirm;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\Handler as ImportExportSitesDB;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\Record as ImportExportSiteRecord;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\LoadIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Profiles\ProfileRepository;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportProfiles\Ops\Record as ImportExportProfileRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SiteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\ScopedTargetHostRequest;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites\SyncSiteUrlValidator;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class Export {

	use PluginControllerConsumer;

	private const VERIFY_OK = 'ok';
	private const VERIFY_FAILED = 'failed';
	private const VERIFY_COOLDOWN = 'cooldown';
	private const EXPORT_COOLDOWN = 300;
	private const HANDSHAKE_COOLDOWN = 300;

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
		$networkOpt = empty( $url ) ? false : $req->query( 'network', '' );
		$verification = $this->verifyUrl( $repo, $url, $id, (string)$req->query( 'secret', '' ) );

		if ( $verification[ 'status' ] === self::VERIFY_COOLDOWN ) {
			$code = 3;
			$msg = __( 'Verification of import-origin failed.', 'wp-simple-firewall' );
		}
		elseif ( $verification[ 'status' ] !== self::VERIFY_OK ) {
			$code = 3;
			$msg = __( 'Verification of import-origin failed.', 'wp-simple-firewall' );
			$repo->recordExportRequested( $url );
			$repo->recordExportFailure( $url, ImportExportSitesDB::EXPORT_RESULT_VERIFY_FAILED, $msg );
		}
		else {
			$row = $verification[ 'row' ];
			if ( $row instanceof ImportExportSiteRecord
				 && !(bool)$verification[ 'secret' ]
				 && $repo->exportCooldownActive( $row, self::EXPORT_COOLDOWN ) ) {
				$code = 3;
				$msg = __( 'Verification of import-origin failed.', 'wp-simple-firewall' );
			}
			else {
				try {
					$code = 0;
					$repo->recordExportRequested( $url );
					$data = $this->shouldUseProfileExport( $row, $networkOpt )
						? $this->getExportDataForProfile( ( new ProfileRepository() )->profileForSite( $row ) )
						: $this->getExportData();
					$success = true;
					$msg = 'Options Exported Successfully';

					$evt->fireEvent(
						'options_exported',
						[ 'audit_params' => [ 'site' => $url ] ]
					);

					if ( $networkOpt === 'Y' ) {
						$ieCon->addSyncSiteExportUrl( $url, $id );
					}

					$repo->recordExportSuccess( $url, ImportExportSitesDB::EXPORT_RESULT_SUCCESS, $id );
					$servedRow = $repo->findByUrl( $url, true );
					if ( $servedRow instanceof ImportExportSiteRecord ) {
						$repo->recordExportServed( $servedRow );
					}

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
		}

		/**
		 * Use 403 to help break caches.
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
		return $this->buildExportData( $this->getRawOptionsExport() );
	}

	public function getExportDataForProfile( ?ImportExportProfileRecord $profile ) :array {
		return $this->buildExportData(
			$profile instanceof ImportExportProfileRecord
				? ( new ProfileRepository() )->exportOptionsForProfile( $profile )
				: $this->getRawOptionsExport()
		);
	}

	public function buildExportData( array $options ) :array {
		$all = [
			'site_url'      => Services::WpGeneral()->getHomeUrl(),
			'exported_at'   => Services::Request()->ts(),
			'exported_date' => Services::Request()->carbon( true )->toIso8601String(),
			'slug'          => 'wp-simple-firewall',
			'version'       => self::con()->cfg->version(),
			'options'       => $options,
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

	private function shouldUseProfileExport( ?ImportExportSiteRecord $row, $networkOpt ) :bool {
		return $row instanceof ImportExportSiteRecord || $networkOpt === 'Y';
	}

	/**
	 * Secret-key export remains valid. Otherwise export trust comes from an active sync-site row.
	 * Rows that already have an import ID must use it. No-ID rows keep legacy handshake fallback.
	 *
	 * @return array{status:string,row:?ImportExportSiteRecord,secret:bool}
	 */
	private function verifyUrl( SiteRepository $repo, string $url, string $id, string $secret ) :array {
		if ( empty( $url ) ) {
			return $this->verifyResult( self::VERIFY_FAILED );
		}

		if ( self::con()->comps->import_export->verifySecretKey( $secret ) ) {
			return $this->verifyResult( self::VERIFY_OK, null, true );
		}

		$row = $repo->findByUrl( $url );
		if ( !$row instanceof ImportExportSiteRecord || !$this->syncSiteRowAllowsExportTrust( $row, $url ) ) {
			return $this->verifyResult( self::VERIFY_FAILED, $row );
		}

		if ( (string)$row->import_id !== '' ) {
			return $id !== '' && \hash_equals( (string)$row->import_id, $id )
				? $this->verifyResult( self::VERIFY_OK, $row )
				: $this->verifyResult( self::VERIFY_FAILED, $row );
		}

		if ( $repo->handshakeCooldownActive( $row, self::HANDSHAKE_COOLDOWN ) ) {
			return $this->verifyResult( self::VERIFY_COOLDOWN, $row );
		}
		$repo->recordHandshakeAttempt( $row );

		return $this->handshake( $url, (string)$row->source === ImportExportSitesDB::SOURCE_MANUAL )
			? $this->verifyResult( self::VERIFY_OK, $row )
			: $this->verifyResult( self::VERIFY_FAILED, $row );
	}

	/**
	 * @return array{status:string,row:?ImportExportSiteRecord,secret:bool}
	 */
	private function verifyResult( string $status, ?ImportExportSiteRecord $row = null, bool $secret = false ) :array {
		return [
			'status' => $status,
			'row'    => $row,
			'secret' => $secret,
		];
	}

	private function syncSiteRowAllowsExportTrust( ImportExportSiteRecord $row, string $url ) :bool {
		if ( (string)$row->source !== ImportExportSitesDB::SOURCE_MANUAL ) {
			return true;
		}

		try {
			( new SyncSiteUrlValidator() )->validateTrustedSyncUrl( $url );
			return true;
		}
		catch ( \Throwable $e ) {
			return false;
		}
	}

	private function handshake( string $url, bool $rejectUnsafeUrls = false ) :bool {
		$targetUrl = URL::Build( $url, ActionData::Build( PluginImportExport_HandshakeConfirm::class, false, [], true ) );
		$request = static fn() :string => Services::HttpRequest()->getContent(
			$targetUrl,
			$rejectUnsafeUrls ? [ 'reject_unsafe_urls' => true ] : []
		);
		$raw = $rejectUnsafeUrls ? ( new ScopedTargetHostRequest() )->run( $targetUrl, $request ) : $request();
		$dec = @\json_decode( $raw, true );
		return \is_array( $dec ) && isset( $dec[ 'success' ] ) && ( $dec[ 'success' ] === true );
	}
}
