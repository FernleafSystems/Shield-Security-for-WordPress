<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs\Ops\Record;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs\Ops\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\ModCon;

class UpgradeReqLogsTable extends ExecOnceModConsumer {

	protected function run() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Select $select */
		$select = $mod->getDbH_ReqLogs()->getQuerySelector();

		$page = 1;
		$pageSize = 100;
		do {
			/** @var Record[] $records */
			$records = $select->setLimit( $pageSize )
							  ->setPage( $page )
							  ->addWhere( 'type', '' )
							  ->queryWithResult();
			foreach ( $records as $record ) {
				try {
					$this->upgradeLogEntry( $record );
				}
				catch ( \Exception $e ) {
					break( 2 );
				}
			}

			$page++;
			if ( $page > 5 ) {
				break;
			}
		} while ( !empty( $records ) );
	}

	/**
	 * @throws \Exception
	 */
	private function upgradeLogEntry( Record $record ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$upgradeData = [
			'type' => Handler::TYPE_HTTP,
			'path' => $record->path,
		];

		$meta = $record->meta;

		if ( $meta[ 'ua' ] === 'wpcli' ) {
			$isWpCli = true;
			$upgradeData[ 'type' ] = Handler::TYPE_WPCLI;
			unset( $meta[ 'ua' ] );
		}
		else {
			$isWpCli = false;
		}

		if ( isset( $meta[ 'code' ] ) && is_numeric( $meta[ 'code' ] ) ) {
			$upgradeData[ 'code' ] = (int)$meta[ 'code' ];
			unset( $meta[ 'code' ] );
		}

		if ( isset( $meta[ 'offense' ] ) ) {
			$upgradeData[ 'offense' ] = true;
			unset( $meta[ 'offense' ] );
		}

		if ( !empty( $meta[ 'path' ] ) ) {
			$parts = explode( $isWpCli ? ' ' : '?', (string)$meta[ 'path' ], 2 );
			$upgradeData[ 'path' ] = $parts[ 0 ];
			if ( !empty( $parts[ 1 ] ) ) {
				$meta[ 'query' ] = $parts[ 1 ];
			}
			unset( $meta[ 'path' ] );
		}

		if ( !empty( $meta[ 'verb' ] ) ) {
			$upgradeData[ 'verb' ] = strtoupper( (string)$meta[ 'verb' ] );
			unset( $meta[ 'verb' ] );
		}

		if ( !empty( $meta[ 'uid' ] ) ) {
			$upgradeData[ 'uid' ] = (int)$meta[ 'uid' ];
			unset( $meta[ 'uid' ] );
		}

		if ( ( $meta[ 'ua' ] ?? '' ) === 'wpcli' ) {
			$upgradeData[ 'type' ] = 'WPCLI';
			unset( $meta[ 'ua' ] );
		}
		elseif ( wp_parse_url( admin_url( 'admin-ajax.php' ), PHP_URL_PATH ) === $upgradeData[ 'path' ] ) {
			$upgradeData[ 'type' ] = Handler::TYPE_AJAX;
		}
		elseif ( wp_parse_url( home_url( 'wp-cron.php' ), PHP_URL_PATH ) === $upgradeData[ 'path' ] ) {
			$upgradeData[ 'type' ] = Handler::TYPE_CRON;
		}
		elseif ( wp_parse_url( home_url( 'xmlrpc.php' ), PHP_URL_PATH ) === $upgradeData[ 'path' ] ) {
			$upgradeData[ 'type' ] = Handler::TYPE_XMLRPC;
		}

		$record->meta = $meta;
		$upgradeData[ 'meta' ] = $record->getRawData()[ 'meta' ];

		$success = $mod->getDbH_ReqLogs()
					   ->getQueryUpdater()
					   ->updateById( $record->id, $upgradeData );
		if ( !$success ) {
			throw new \Exception( 'failed to update' );
		}
	}
}