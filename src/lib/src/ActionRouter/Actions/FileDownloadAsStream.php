<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs\LoadRequestLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs\LogRecord;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Download\IssueFileDownloadResponse;

class FileDownloadAsStream extends BaseAction {

	public const SLUG = 'file_download_stream';

	protected function exec() {
		try {
			( new IssueFileDownloadResponse( sprintf( 'traffic-%s.log', Services::Request()->ts() ) ) )
				->fromGenerator( $this->getGenerator(), "\n", [ 'Set-cookie' => 'fileDownload=true; path=/' ] );
		}
		catch ( \Exception $e ) {
			$resp = $this->response();
			$resp->success = false;
			$resp->message = $e->getMessage();
		}
	}

	/**
	 * @throws \Exception
	 */
	private function getGenerator() :\Generator {
		switch ( $this->action_data[ 'download_category' ] ) {
			case 'traffic':
				$gen = $this->downloadTrafficLogs();
				break;
			default:
				throw new \Exception( 'Invalid download request.' );
		}
		return $gen;
	}

	public function downloadTrafficLogs() :\Generator {
		$WP = Services::WpGeneral();
		$page = 0;
		$length = 200;
		do {
			$logLoader = new LoadRequestLogs();
			$logLoader->limit = $length;
			$logLoader->offset = $length*( $page++ );
			$logLoader->order_by = 'id';
			$logLoader->order_dir = 'DESC';
			$results = $logLoader->select();
			if ( empty( $results ) ) {
				break;
			}
			yield \implode( "\n", \array_map(
				function ( LogRecord $record ) use ( $WP ) {
					return sprintf( "%s %s %s [%s] \"%s %s\" %s",
						$record->ip,
						'-',
						empty( $record->uid ) ? '-' : $record->uid,
						$WP->getTimeStampForDisplay( $record->created_at ),
						$record->verb,
						$record->path,
						$record->code
					);
				},
				$results
			) );
		} while ( true );
	}
}