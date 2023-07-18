<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class MergeAutoBlockRules {

	use ModConsumer;

	/**
	 * @throws \Exception
	 */
	public function byIP( string $ip ) :void {
		$loader = ( new LoadIpRules() )->setIP( $ip );
		$loader->wheres = [
			"`ir`.`is_range`='0'"
		];
		$this->byRecords( $loader->select() );
	}

	/**
	 * @throws \Exception
	 */
	public function byRecords( array $records ) :void {
		$dbh = $this->mod()->getDbH_IPRules();

		if ( \count( $records ) < 2 ) {
			throw new \Exception( 'At least 2 records are required to merge.' );
		}

		$workingIP = null;
		$toKeep = null;

		$extraOffenses = 0;
		$idsToDelete = [];

		foreach ( $records as $record ) {

			if ( empty( $workingIP ) ) {
				$workingIP = $record->ip;
			}
			elseif ( $workingIP !== $record->ip ) {
				throw new \Exception( 'The records dont pertain to the same IP address.' );
			}

			if ( !isset( $toKeep ) ) {
				$toKeep = $record;
			}
			else {
				$extraOffenses += $record->offenses;
				$idsToDelete[] = $record->id;
			}
		}

		if ( !empty( $toKeep ) && !empty( $idsToDelete ) ) {
			$dbh->getQueryDeleter()
				->addWhereIn( 'id', $idsToDelete )
				->query();

			$updateData = [
				'offenses' => $toKeep->offenses + $extraOffenses,
			];
			if ( $updateData[ 'offenses' ] >= $this->opts()->getOffenseLimit()
				 && $toKeep->blocked_at <= $toKeep->unblocked_at ) {
				$updateData[ 'blocked_at' ] = Services::Request()->ts();
			}

			$dbh->getQueryUpdater()->updateRecord( $toKeep, $updateData );
			$dbh->getQuerySelector()->byId( $toKeep->id );
		}
	}
}