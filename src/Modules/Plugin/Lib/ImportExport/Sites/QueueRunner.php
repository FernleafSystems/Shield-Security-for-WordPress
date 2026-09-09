<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\ImportExport\Sites;

class QueueRunner {

	public const BATCH_SIZE = 5;
	public const INVITE_TIMEOUT = 2;
	public const NOTIFY_TIMEOUT = 5;
	public const LOCK_TIMEOUT = 600;
	public const EXPORT_GRACE = 600;

	public function run() :void {
		$repo = $this->repository();
		$repo->ensureLegacyImported();
		$repo->recoverExpiredProcessingRows( self::BATCH_SIZE );

		foreach ( $repo->selectExpiredWaitingExportRows( self::BATCH_SIZE ) as $row ) {
			$repo->recordExportTimeout( $row );
		}

		$now = \FernleafSystems\Wordpress\Services\Services::Request()->ts();
		$remaining = self::BATCH_SIZE;
		foreach ( $repo->claimDueInviteRows( $remaining, $now + self::LOCK_TIMEOUT ) as $row ) {
			$this->inviteSender()->send( $row->url, self::INVITE_TIMEOUT );
			$repo->recordInviteProcessed( $row );
			$remaining--;
		}

		if ( $remaining <= 0 ) {
			return;
		}

		foreach ( $repo->claimDueRows( $remaining, $now + self::LOCK_TIMEOUT ) as $row ) {
			$repo->recordPingAttempt( $row );
			$result = $this->pingSender()->send( $row->url, self::NOTIFY_TIMEOUT, (string)$row->import_id );
			if ( $result[ 'success' ] ) {
				$repo->recordNotifyDispatched( $row, $result[ 'http_code' ], $now + self::EXPORT_GRACE );
			}
			else {
				$repo->recordPingFailure( $row, $result[ 'http_code' ], $result[ 'error' ] );
			}
		}
	}

	protected function repository() :SiteRepository {
		return new SiteRepository();
	}

	protected function pingSender() :PingSender {
		return new PingSender();
	}

	protected function inviteSender() :SyncSiteInviteSender {
		return new SyncSiteInviteSender();
	}
}
