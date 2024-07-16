<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\ShieldNET;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\BotSignal\BotSignalRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class BuildData {

	use PluginControllerConsumer;

	public function build( bool $quiet = false ) :array {

		$records = $this->getRecords();
		if ( !$quiet ) {
			$this->markRecordsAsSent( $records );
		}

		$records = \array_filter( \array_map(
			function ( $entryVO ) {
				$data = [
					'ip'      => $entryVO->ip,
					'signals' => [],
				];
				foreach ( $entryVO->getRawData() as $col => $value ) {
					if ( \strpos( $col, '_at' ) && $value > 0
						 && !\in_array( $col, [ 'snsent_at', 'updated_at', 'created_at', 'deleted_at' ] ) ) {
						$data[ 'signals' ][] = \str_replace( '_at', '', $col );
					}
				}

				// Now we remove any "known" bots/crawlers
				$record = [];
				if ( !empty( $data[ 'signals' ] ) ) {
					try {
						[ $id, ] = ( new IpID( $data[ 'ip' ] ) )->run();
						if ( $id === IpID::UNKNOWN ) {
							$record = $data;
						}
					}
					catch ( \Exception $e ) {
					}
				}

				return $record;
			},
			$records
		) );

		// We order with preference towards IPs with more signals.
		// And, if the only signal is "frontpage" we prefer anything else before it.
		\usort( $records, function ( $a, $b ) {
			$countA = \count( $a[ 'signals' ] );
			$countB = \count( $b[ 'signals' ] );

			if ( $countA == $countB ) {

				if ( $countA === 1 && \in_array( 'frontpage', $a[ 'signals' ] ) ) {
					$order = 1;
				}
				elseif ( $countB === 1 && \in_array( 'frontpage', $b[ 'signals' ] ) ) {
					$order = -1;
				}
				else {
					$order = 0;
				}
			}
			else {
				$order = ( \count( $a[ 'signals' ] ) > \count( $b[ 'signals' ] ) ) ? -1 : 1;
			}

			return $order;
		} );

		return \array_slice( $records, 0, 100 );
	}

	/**
	 * @param BotSignalRecord[] $records
	 */
	private function markRecordsAsSent( array $records ) {
		if ( !empty( $records ) ) {
			Services::WpDb()
					->doSql(
						sprintf( 'UPDATE `%s` SET `snsent_at`=%s WHERE `id` in (%s);',
							self::con()->db_con->bot_signals->getTable(),
							Services::Request()->ts(),
							\implode( ',', \array_map( function ( $record ) {
								return $record->id;
							}, $records ) )
						)
					);
		}
	}

	/**
	 * Optimised to ensure that only signals are sent if they've been updated since the last SNAPI-Send
	 * @return BotSignalRecord[]
	 */
	private function getRecords() :array {
		$serverIPs = \array_map(
			function ( $ip ) {
				return sprintf( "INET6_ATON('%s')", $ip );
			},
			\is_array( Services::IP()->getServerPublicIPs() ) ? Services::IP()->getServerPublicIPs() : []
		);

		$records = Services::WpDb()->selectCustom(
			sprintf( "SELECT `ips`.`ip`, `bs`.*
						FROM `%s` as `bs`
						INNER JOIN `%s` as `ips`
							ON `ips`.`id` = `bs`.`ip_ref` 
							%s
						ORDER BY `bs`.`updated_at` DESC
						LIMIT 200;",
				self::con()->db_con->bot_signals->getTable(),
				self::con()->db_con->ips->getTable(),
				empty( $serverIPs ) ? '' : sprintf( "AND `ips`.`ip` NOT IN (%s)", \implode( ",", $serverIPs ) )
			)
		);

		return \array_map(
			function ( $record ) {
				return ( new BotSignalRecord() )->applyFromArray( $record );
			},
			\is_array( $records ) ? $records : []
		);
	}
}