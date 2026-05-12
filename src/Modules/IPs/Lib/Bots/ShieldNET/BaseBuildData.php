<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\ShieldNET;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\BotSignal\BotSignalRecord;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\Common\IpAddressSql;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

abstract class BaseBuildData {

	use PluginControllerConsumer;

	public function build( bool $quiet = false ) :array {
		$records = $this->getRecords();
		if ( !$quiet ) {
			$this->markRecordsAsSent( $records );
		}

		return $this->buildFromRecords( $records );
	}

	abstract protected function buildFromRecords( array $records ) :array;

	protected function determineSignals( BotSignalRecord $record ) :array {
		$signals = [];
		foreach ( $record->getRawData() as $col => $value ) {
			if ( \strpos( $col, '_at' ) && $value > 0
				 && !\in_array( $col, [ 'snsent_at', 'updated_at', 'created_at', 'deleted_at' ], true ) ) {
				$signals[] = \str_replace( '_at', '', $col );
			}
		}

		return $signals;
	}

	protected function isUnknownCrawlerIp( string $ip ) :bool {
		try {
			[ $id, ] = ( new IpID( $ip ) )->run();
			$isUnknown = $id === IpID::UNKNOWN;
		}
		catch ( \Exception $e ) {
			$isUnknown = false;
		}

		return $isUnknown;
	}

	/**
	 * @param BotSignalRecord[] $records
	 */
	private function markRecordsAsSent( array $records ) :void {
		if ( !empty( $records ) ) {
			Services::WpDb()
					->doSql(
						sprintf( 'UPDATE `%s` SET `snsent_at`=%s WHERE `id` in (%s);',
							self::con()->db_con->bot_signals->getTable(),
							Services::Request()->ts(),
							\implode( ',', \array_map( fn( BotSignalRecord $record ) => $record->id, $records ) )
						)
					);
		}
	}

	/**
	 * @return BotSignalRecord[]
	 */
	private function getRecords() :array {
		$serverIPs = IpAddressSql::literalsFromIps( Services::IP()->getServerPublicIPs() );

		$records = Services::WpDb()->selectCustom(
			sprintf( "SELECT `ips`.`ip`, `bs`.*
						FROM `%s` as `bs`
						INNER JOIN `%s` as `ips`
							ON `ips`.`id` = `bs`.`ip_ref`
							%s
						WHERE `bs`.`snsent_at` = 0 OR `bs`.`updated_at` > `bs`.`snsent_at`
						ORDER BY `bs`.`updated_at` DESC
						LIMIT 200;",
				self::con()->db_con->bot_signals->getTable(),
				self::con()->db_con->ips->getTable(),
				empty( $serverIPs ) ? '' : sprintf( "AND `ips`.`ip` NOT IN (%s)", \implode( ',', $serverIPs ) )
			)
		);

		return \array_map(
			fn( $record ) => ( new BotSignalRecord() )->applyFromArray( $record ),
			\is_array( $records ) ? $records : []
		);
	}
}
