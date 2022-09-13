<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\{
	DB\IpRules\Ops\Update,
	ModCon,
	Options
};
use FernleafSystems\Wordpress\Services\Services;

class MergeAutoBlockRules extends ExecOnceModConsumer {

	/**
	 * @return IpRuleRecord|null
	 * @throws \Exception
	 */
	public function byIP( string $ip ) {
		$loader = ( new LoadIpRules() )
			->setMod( $this->getMod() )
			->setIP( $ip );
		$loader->wheres = [
			"`ir`.`is_range`='0'"
		];
		return $this->byRecords( $loader->select() );
	}

	/**
	 * @param IpRuleRecord[] $records
	 * @return IpRuleRecord|null
	 * @throws \Exception
	 */
	public function byRecords( array $records ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		if ( count( $records ) < 2 ) {
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
			$mod->getDbH_IPRules()
				->getQueryDeleter()
				->addWhereIn( 'id', $idsToDelete )
				->query();

			$updateData = [
				'offenses' => $toKeep->offenses + $extraOffenses,
			];
			if ( $updateData[ 'offenses' ] >= $opts->getOffenseLimit() && $toKeep->blocked_at <= $toKeep->unblocked_at ) {
				$updateData[ 'blocked_at' ] = Services::Request()->ts();
			}

			/** @var Update $updater */
			$updater = $mod->getDbH_IPRules()->getQueryUpdater();
			$updater->updateRecord( $toKeep, $updateData );

			$toKeep = $mod->getDbH_IPRules()->getQuerySelector()->byId( $toKeep->id );
		}

		return $toKeep;
	}
}