<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\{
	IpRuleRecord,
	LoadIpRules
};
use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\LoadRequestLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\Utility\ConvertLogsToFlatText;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\Download\IssueFileDownloadResponse;

class FileDownloadAsStream extends BaseAction {

	public const SLUG = 'file_download_stream';

	protected function exec() {
		try {
			( new IssueFileDownloadResponse(
				sprintf( '%s-%s.log', $this->action_data[ 'download_category' ], Services::Request()->ts() )
			) )->fromGenerator( $this->getGenerator() );
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
			case 'ip_rules':
				$gen = $this->downloadIpRules();
				break;
			case 'traffic':
				$gen = $this->downloadTrafficLogs();
				break;
			default:
				throw new \Exception( 'Invalid download request.' );
		}
		return $gen;
	}

	private function downloadIpRules() :\Generator {
		$page = 0;
		$length = 200;

		$fields = [
			'ip',
			'type',
			'offenses',
			'last_access_at',
			'blocked_at',
		];

		do {
			$logLoader = new LoadIpRules();
			$logLoader->limit = $length;
			$logLoader->offset = $length*( $page++ );
			$logLoader->order_by = 'id';
			$logLoader->order_dir = 'DESC';
			$results = $logLoader->select();
			if ( empty( $results ) ) {
				break;
			}

			$lines = \array_map(
				function ( IpRuleRecord $r ) use ( $fields ) {
					return \implode( ',', \array_map( function ( string $field ) use ( $r ) {
						return $r->{$field};
					}, $fields ) );
				},
				$results
			);

			if ( $page === 1 ) {
				\array_unshift( $lines, \implode( ',', $fields ) );
			}

			yield \implode( "\n", $lines );
		} while ( true );
	}

	private function downloadTrafficLogs() :\Generator {
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
			yield \implode( "\n", ( new ConvertLogsToFlatText() )->convert( $results ) );
		} while ( true );
	}
}