<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginImportExport_HandshakeConfirm;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\Handler as ImportExportSitesDB;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportSites\Ops\Record as ImportExportSiteRecord;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\LoadIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Diagnostics\{
	HttpOutcome,
	ObservationStore,
	SyncObservation
};
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Profiles\ProfileRepository;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ImportExportProfiles\Ops\Record as ImportExportProfileRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Sites\SiteRepository;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Sites\ScopedTargetHostRequest;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\ImportExport\Sites\SyncSiteUrlValidator;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\URL;

class Export {

	use PluginControllerConsumer;

	private const VERIFY_OK = 'ok';
	private const VERIFY_FAILED = 'failed';
	private const VERIFY_COOLDOWN = 'cooldown';
	private const EXPORT_COOLDOWN = 300;
	private const IMPORT_ID_EXPORT_COOLDOWN = 60;
	private const HANDSHAKE_COOLDOWN = 300;

	public function run( string $method ) {
		try {
			if ( $method === 'json' ) {
				$this->toJson();
			}
		}
		catch ( \Exception $e ) {
		}
	}

	public function toJson() :void {
		$ieCon = self::con()->comps->import_export;
		if ( !$ieCon->isSyncEnabled() ) {
			return;
		}

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

		$url = ( new SyncSiteUrlValidator() )->canonicalize( (string)$req->query( 'url', '' ) );
		$id = (string)$req->query( 'id', '' );
		$networkOpt = empty( $url ) ? false : $req->query( 'network', '' );
		$verification = $this->verifyUrl( $repo, $url, $id, (string)$req->query( 'secret', '' ) );

		if ( \in_array( $verification[ 'status' ], [ self::VERIFY_FAILED, self::VERIFY_COOLDOWN ], true ) ) {
			return;
		}

		$row = $verification[ 'row' ];
		$operationRow = $row;
		if ( $verification[ 'secret' ] && !$operationRow instanceof ImportExportSiteRecord ) {
			$operationRow = $repo->findByUrl( $url );
		}
		$diagnosticRow = $row;
		if ( $row instanceof ImportExportSiteRecord && !$verification[ 'secret' ] ) {
			$cooldown = $verification[ 'import_id_verified' ] ? self::IMPORT_ID_EXPORT_COOLDOWN : self::EXPORT_COOLDOWN;
			if ( $repo->exportCooldownActive( $row, $cooldown ) ) {
				$servedAt = (int)( \is_array( $row->meta ) ? ( $row->meta[ 'export_served_at' ] ?? 0 ) : 0 );
				$this->saveRowObservation( $repo, $row, SyncObservation::PHASE_EXPORT,
					SyncObservation::RESULT_EXPORT_COOLDOWN,
					SyncObservation::VERIFICATION_ESTABLISHED,
					\array_filter( [
						'error_category' => SyncObservation::ERROR_REMOTE_COOLDOWN,
						'eligible_at'    => $servedAt > 0 ? $servedAt + $cooldown : null,
					], static fn( $value ) :bool => $value !== null )
				);
				wp_send_json( [
					'success' => false,
					'code'    => 3,
					'message' => __( 'Please wait a few minutes before trying that again.', 'wp-simple-firewall' ),
					'data'    => $data,
				], 403 );
			}
		}

		try {
			$code = 0;
			if ( $operationRow instanceof ImportExportSiteRecord ) {
				$repo->recordExportRequested( $operationRow );
			}
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
				$enrolledRow = $ieCon->addSyncSiteExportUrl( $url, $id );
				if ( $enrolledRow instanceof ImportExportSiteRecord ) {
					$operationRow = $enrolledRow;
					$diagnosticRow = $enrolledRow;
				}
			}

			if ( $operationRow instanceof ImportExportSiteRecord ) {
				$repo->recordExportSuccess( $operationRow, ImportExportSitesDB::EXPORT_RESULT_SUCCESS, $id );
			}
			$servedRow = $repo->findByUrl( $url, true );
			if ( $servedRow instanceof ImportExportSiteRecord ) {
				$repo->recordExportServed( $servedRow );
			}
			if ( $diagnosticRow instanceof ImportExportSiteRecord ) {
				$this->saveRowObservation( $repo, $diagnosticRow, SyncObservation::PHASE_EXPORT,
					SyncObservation::RESULT_EXPORT_SERVED, SyncObservation::VERIFICATION_ESTABLISHED );
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
			if ( $diagnosticRow instanceof ImportExportSiteRecord ) {
				$this->saveRowObservation( $repo, $diagnosticRow, SyncObservation::PHASE_EXPORT,
					SyncObservation::RESULT_EXPORT_EXCEPTION,
					SyncObservation::VERIFICATION_ESTABLISHED,
					[ 'error_category' => SyncObservation::ERROR_REMOTE_EXPORT_EXCEPTION ]
				);
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
	 * @return array{status:string,row:?ImportExportSiteRecord,secret:bool,import_id_verified:bool}
	 */
	private function verifyUrl( SiteRepository $repo, string $url, string $id, string $secret ) :array {
		if ( empty( $url ) ) {
			$this->saveUnassociatedObservation( SyncObservation::RESULT_INVALID_CLAIMED_URL );
			return $this->verifyResult( self::VERIFY_FAILED );
		}

		if ( self::con()->comps->import_export->verifySecretKey( $secret ) ) {
			return $this->verifyResult( self::VERIFY_OK, null, true );
		}

		$row = $repo->findByUrl( $url );
		if ( !$row instanceof ImportExportSiteRecord ) {
			$this->saveUnassociatedObservation( SyncObservation::RESULT_NO_AUTHORIZED_ROW );
			return $this->verifyResult( self::VERIFY_FAILED, $row );
		}
		if ( !$this->syncSiteRowAllowsExportTrust( $row, $url ) ) {
			$this->saveRowObservation( $repo, $row, SyncObservation::PHASE_VERIFICATION,
				SyncObservation::RESULT_TRUSTED_TARGET_VALIDATION_FAILED,
				SyncObservation::VERIFICATION_FAILED
			);
			return $this->verifyResult( self::VERIFY_FAILED, $row );
		}

		if ( $row->import_id !== '' ) {
			if ( $id === '' ) {
				$this->saveRowObservation( $repo, $row, SyncObservation::PHASE_VERIFICATION,
					SyncObservation::RESULT_MISSING_ID,
					SyncObservation::VERIFICATION_FAILED
				);
				return $this->verifyResult( self::VERIFY_FAILED, $row );
			}
			if ( !\hash_equals( $row->import_id, $id ) ) {
				$this->saveRowObservation( $repo, $row, SyncObservation::PHASE_VERIFICATION,
					SyncObservation::RESULT_MISMATCHED_ID,
					SyncObservation::VERIFICATION_FAILED
				);
				return $this->verifyResult( self::VERIFY_FAILED, $row );
			}
			$this->saveRowObservation( $repo, $row, SyncObservation::PHASE_VERIFICATION,
				SyncObservation::RESULT_VERIFICATION_PASSED,
				SyncObservation::VERIFICATION_ESTABLISHED
			);
			return $this->verifyResult( self::VERIFY_OK, $row, false, true );
		}

		if ( $repo->handshakeCooldownActive( $row, self::HANDSHAKE_COOLDOWN ) ) {
			$attemptedAt = (int)( \is_array( $row->meta ) ? ( $row->meta[ 'handshake_attempt_at' ] ?? 0 ) : 0 );
			$this->saveRowObservation( $repo, $row, SyncObservation::PHASE_VERIFICATION,
				SyncObservation::RESULT_CALLBACK_COOLDOWN,
				SyncObservation::VERIFICATION_FAILED,
				$attemptedAt > 0 ? [ 'eligible_at' => $attemptedAt + self::HANDSHAKE_COOLDOWN ] : []
			);
			return $this->verifyResult( self::VERIFY_COOLDOWN, $row );
		}
		try {
			$callbackUrl = ( new SyncSiteUrlValidator() )->validateTrustedSyncUrl( $row->url );
		}
		catch ( \InvalidArgumentException $e ) {
			$this->saveRowObservation( $repo, $row, SyncObservation::PHASE_VERIFICATION,
				SyncObservation::RESULT_TRUSTED_TARGET_VALIDATION_FAILED,
				SyncObservation::VERIFICATION_FAILED
			);
			return $this->verifyResult( self::VERIFY_FAILED, $row );
		}
		$repo->recordHandshakeAttempt( $row );

		$handshake = $this->handshake( $callbackUrl );
		$this->saveRowObservation( $repo, $row, SyncObservation::PHASE_VERIFICATION,
			$handshake[ 'result' ],
			$handshake[ 'verified' ]
				? SyncObservation::VERIFICATION_ESTABLISHED
				: SyncObservation::VERIFICATION_FAILED,
			$handshake[ 'fields' ]
		);
		return $handshake[ 'verified' ]
			? $this->verifyResult( self::VERIFY_OK, $row )
			: $this->verifyResult( self::VERIFY_FAILED, $row );
	}

	/**
	 * @return array{status:string,row:?ImportExportSiteRecord,secret:bool,import_id_verified:bool}
	 */
	private function verifyResult(
		string $status,
		?ImportExportSiteRecord $row = null,
		bool $secret = false,
		bool $importIDVerified = false
	) :array {
		return [
			'status'             => $status,
			'row'                => $row,
			'secret'             => $secret,
			'import_id_verified' => $importIDVerified,
		];
	}

	private function syncSiteRowAllowsExportTrust( ImportExportSiteRecord $row, string $url ) :bool {
		if ( $row->source !== ImportExportSitesDB::SOURCE_MANUAL ) {
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

	/**
	 * @return array{verified:bool,result:string,fields:array}
	 */
	private function handshake( string $url ) :array {
		$targetUrl = URL::Build( $url, ActionData::Build( PluginImportExport_HandshakeConfirm::class, false, [], true ) );
		$request = static function () use ( $targetUrl ) :HttpOutcome {
			$http = Services::HttpRequest();
			$body = $http->getContent( $targetUrl, [ 'reject_unsafe_urls' => true ] );
			return HttpOutcome::fromRequest( $body, $http );
		};
		$outcome = ( new ScopedTargetHostRequest() )->run( $targetUrl, $request );
		if ( !$outcome->hasResponse() ) {
			return [
				'verified' => false,
				'result'   => SyncObservation::RESULT_CALLBACK_TRANSPORT_FAILURE,
				'fields'   => $outcome->observationFields(),
			];
		}
		$dec = @\json_decode( $outcome->body(), true );
		if ( !\is_array( $dec ) ) {
			return [
				'verified' => false,
				'result'   => SyncObservation::RESULT_CALLBACK_INVALID_RESPONSE,
				'fields'   => $outcome->observationFields(),
			];
		}
		$verified = isset( $dec[ 'success' ] ) && $dec[ 'success' ] === true;
		return [
			'verified' => $verified,
			'result'   => $verified
				? SyncObservation::RESULT_VERIFICATION_PASSED
				: SyncObservation::RESULT_CALLBACK_DID_NOT_CONFIRM,
			'fields'   => $outcome->observationFields(),
		];
	}

	private function saveRowObservation(
		SiteRepository $repo,
		ImportExportSiteRecord $row,
		string $phase,
		string $result,
		string $verification,
		array $optional = []
	) :void {
		$observation = SyncObservation::create( Services::Request()->ts(), $phase, $result, $verification, $optional );
		if ( $observation !== null ) {
			$repo->saveObservation( $row, $phase, $observation );
		}
	}

	private function saveUnassociatedObservation( string $result ) :void {
		$observation = SyncObservation::create(
			Services::Request()->ts(),
			SyncObservation::PHASE_VERIFICATION,
			$result,
			SyncObservation::VERIFICATION_FAILED
		);
		if ( $observation !== null ) {
			( new ObservationStore() )->saveUnassociatedRejection( $observation );
		}
	}
}
