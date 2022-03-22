<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\ReqLogs;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs\Ops as ReqDB;

class QueueReqDbRecordMigrator extends Shield\Databases\Utility\QueueDbRecordsMigrator {

	public function __construct() {
		parent::__construct( 'db_upgrader_reqlogs' );
	}

	/**
	 * @return ReqDB\Select
	 */
	protected function getDbSelector() {
		/** @var Shield\Modules\Data\ModCon $mod */
		$mod = $this->getMod();
		/** @var ReqDB\Select $select */
		$select = $mod->getDbH_ReqLogs()->getQuerySelector();
		return $select->addWhere( 'type', '' );
	}

	protected function processRecord( $record ) {
		/** @var Shield\Modules\Data\DB\ReqLogs\Ops\Record */
		/** @var Shield\Modules\Data\ModCon $mod */
		$mod = $this->getMod();

		$upgradeData = [
			'type' => ReqDB\Handler::TYPE_HTTP,
			'path' => $record->path,
		];

		$meta = $record->meta;

		if ( $meta[ 'ua' ] === 'wpcli' ) {
			$isWpCli = true;
			$upgradeData[ 'type' ] = ReqDB\Handler::TYPE_WPCLI;
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
			$upgradeData[ 'type' ] = ReqDB\Handler::TYPE_AJAX;
		}
		elseif ( wp_parse_url( home_url( 'wp-cron.php' ), PHP_URL_PATH ) === $upgradeData[ 'path' ] ) {
			$upgradeData[ 'type' ] = ReqDB\Handler::TYPE_CRON;
		}
		elseif ( wp_parse_url( home_url( 'xmlrpc.php' ), PHP_URL_PATH ) === $upgradeData[ 'path' ] ) {
			$upgradeData[ 'type' ] = ReqDB\Handler::TYPE_XMLRPC;
		}

		$record->meta = $meta;
		$upgradeData[ 'meta' ] = $record->getRawData()[ 'meta' ];

		$success = $mod->getDbH_ReqLogs()
					   ->getQueryUpdater()
					   ->updateById( $record->id, $upgradeData );
		if ( !$success ) {
			$mod->getDbH_ReqLogs()->getQueryDeleter()->deleteById( $record->id );
			throw new \Exception( 'failed to update' );
		}

		return $record;
	}
}
