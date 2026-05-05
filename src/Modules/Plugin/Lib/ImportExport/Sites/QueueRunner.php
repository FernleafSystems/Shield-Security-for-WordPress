<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites;

class QueueRunner {

	public const BATCH_SIZE = 10;
	public const PING_TIMEOUT = 2;
	public const LOCK_TIMEOUT = 600;
	public const EXPORT_GRACE = 600;

	public function run() :void {
		$repo = $this->repository();
		$repo->ensureLegacyImported();

		foreach ( $repo->selectExpiredWaitingExportRows( self::BATCH_SIZE ) as $row ) {
			$repo->recordExportTimeout( $row );
		}

		$now = \FernleafSystems\Wordpress\Services\Services::Request()->ts();
		foreach ( $repo->claimDueRows( self::BATCH_SIZE, $now + self::LOCK_TIMEOUT ) as $row ) {
			$repo->recordPingAttempt( $row );
			$result = $this->pingSender()->send( $row->url, self::PING_TIMEOUT );
			if ( (bool)( $result[ 'success' ] ?? false ) ) {
				$repo->recordPingSuccess( $row, (int)( $result[ 'http_code' ] ?? 0 ), $now + self::EXPORT_GRACE );
			}
			else {
				$repo->recordPingFailure( $row, (int)( $result[ 'http_code' ] ?? 0 ), (string)( $result[ 'error' ] ?? '' ) );
			}
		}
	}

	protected function repository() :SiteRepository {
		return new SiteRepository();
	}

	protected function pingSender() :PingSender {
		return new PingSender();
	}
}
