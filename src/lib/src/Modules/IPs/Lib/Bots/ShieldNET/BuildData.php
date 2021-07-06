<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\ShieldNET;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals\EntryVO;
use FernleafSystems\Wordpress\Plugin\Shield\Databases\BotSignals\Select;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BuildData {

	use ModConsumer;

	public function build( bool $quiet = false ) :array {

		$recordsToSend = $this->getRecords();
		if ( !$quiet ) {
			$this->markRecordsAsSent( $recordsToSend );
		}

		$results = array_filter( array_map(
			function ( $entryVO ) {
				$data = [
					'ip'      => $entryVO->ip,
					'signals' => [],
				];
				foreach ( $entryVO->getRawData() as $col => $value ) {
					if ( strpos( $col, '_at' ) && $value > 0
						 && !in_array( $col, [ 'snsent_at', 'updated_at', 'created_at', 'deleted_at' ] ) ) {
						$data[ 'signals' ][] = str_replace( '_at', '', $col );
					}
				}
				return empty( $data[ 'signals' ] ) ? [] : $data;
			},
			$recordsToSend
		) );

		// We order with preference towards IPs with more signals.
		// And, if the only signal is "frontpage" we prefer anything else before it.
		usort( $results, function ( $a, $b ) {
			$countA = count( $a[ 'signals' ] );
			$countB = count( $b[ 'signals' ] );

			if ( $countA == $countB ) {

				if ( $countA === 1 && in_array( 'frontpage', $a[ 'signals' ] ) ) {
					$order = 1;
				}
				elseif ( $countB === 1 && in_array( 'frontpage', $b[ 'signals' ] ) ) {
					$order = -1;
				}
				else {
					$order = 0;
				}
			}
			else {
				$order = ( count( $a[ 'signals' ] ) > count( $b[ 'signals' ] ) ) ? -1 : 1;
			}

			return $order;
		} );

		return array_slice( $results, 0, 50 );
	}

	/**
	 * @param EntryVO[] $records
	 */
	private function markRecordsAsSent( array $records ) {
		if ( !empty( $records ) ) {
			/** @var ModCon $mod */
			$mod = $this->getMod();
			Services::WpDb()
					->doSql(
						sprintf( 'UPDATE `%s` SET `snsent_at`=%s WHERE `id` in (%s);',
							$mod->getDbHandler_BotSignals()->getTableSchema()->table,
							Services::Request()->ts(),
							implode( ',', array_map( function ( $record ) {
								return $record->id;
							}, $records ) )
						)
					);
		}
	}

	/**
	 * Optimised to ensure that only signals are sent if they've been updated since the last SNAPI-Send
	 * @return EntryVO[]
	 */
	private function getRecords() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Select $select */
		$select = $mod->getDbHandler_BotSignals()->getQuerySelector();
		$records = $select->setLimit( 150 )
						  ->setOrderBy( 'updated_at', 'DESC' )
						  ->addWhereNotIn( 'ip', array_map( 'inet_pton', Services::IP()->getServerPublicIPs() ) )
						  ->addWhereCompareColumns( 'updated_at', 'snsent_at', '>' )
						  ->query();
		return is_array( $records ) ? $records : [];
	}
}