<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Controller\Database;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\{
	LoadIpRules,
	MergeAutoBlockRules,
	Ops as IpRulesDB
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRulesCache;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CleanIpRules {

	use PluginControllerConsumer;

	public function all() {
		$this->expired();
		$this->duplicates();
	}

	public function duplicates() {
		$this->duplicates_AutoBlock();
		$this->duplicates_Crowdsec();
	}

	public function expired() {
		$this->expired_AutoBlock();
		$this->expired_Crowdsec();
	}

	public function cleanAutoBlocks() {
		$this->expired_AutoBlock();
		$this->duplicates_AutoBlock();
	}

	private function expired_AutoBlock() {
		// Expired AutoBlock
		/** @var IpRulesDB\Delete $deleter */
		$deleter = self::con()->db_con->ip_rules->getQueryDeleter();
		$deleter
			->filterByType( IpRulesDB\Handler::T_AUTO_BLOCK )
			->addWhereOlderThan(
				Services::Request()
						->carbon()
						->subSeconds( self::con()->comps->opts_lookup->getIpAutoBlockTTL() )->timestamp,
				'last_access_at'
			)
			->query();
	}

	public function expired_Crowdsec() {
		/** @var IpRulesDB\Delete $deleter */
		$deleter = self::con()->db_con->ip_rules->getQueryDeleter();
		$deleter->filterByType( IpRulesDB\Handler::T_CROWDSEC )
				->addWhereOlderThan( Services::Request()->ts(), 'expires_at' )
				->query();

		/**
		 * @since 18.4 - delete crowdsec IPs that have never been accessed, and that expire within 2 days.
		 */
		$deleter->reset()
				->filterByType( IpRulesDB\Handler::T_CROWDSEC )
				->addWhere( 'last_access_at', 0 )
				->addWhereOlderThan( Services::Request()->ts() + DAY_IN_SECONDS*2, 'expires_at' )
				->query();

		IpRulesCache::Delete( IpRulesCache::COLLECTION_RANGES, IpRulesCache::GROUP_COLLECTIONS );
	}

	public function duplicates_AutoBlock() {
		\array_map(
			function ( $ip ) {
				try {
					( new MergeAutoBlockRules() )->byIP( $ip );
				}
				catch ( \Exception $e ) {
					error_log( 'clean duplicate IPs for: '.$ip );
				}
			},
			\array_keys( \array_filter( $this->getIpCountsForType( IpRulesDB\Handler::T_AUTO_BLOCK ), function ( $IDs ) {
				return \count( $IDs ) > 1;
			} ) )
		);
	}

	/**
	 * Find all records that reference duplicate IP addresses and delete surplus.
	 */
	public function duplicates_Crowdsec() {

		$allCounts = \array_filter( $this->getIpCountsForType( IpRulesDB\Handler::T_CROWDSEC ), function ( $IDs ) {
			return \count( $IDs ) > 1;
		} );

		$deleteIDs = [];
		foreach ( $allCounts as $ipIDs ) {
			\array_pop( $ipIDs );
			$deleteIDs = \array_merge( $deleteIDs, $ipIDs );
		}

		if ( !empty( $deleteIDs ) ) {
			/** @var IpRulesDB\Delete $deleter */
			$deleter = self::con()->db_con->ip_rules->getQueryDeleter();
			$deleter
				->filterByType( IpRulesDB\Handler::T_CROWDSEC )
				->addWhereIn( 'id', $deleteIDs )
				->query();
		}
	}

	private function getIpCountsForType( string $type ) :array {
		$ipCounts = [];

		$page = 0;
		$pageSize = 250;
		do {
			$loader = new LoadIpRules();
			$loader->wheres = [
				sprintf( "`ir`.`type`='%s'", $type )
			];
			$loader->limit = $pageSize;
			$loader->offset = $page*$pageSize;

			$hasRecords = false;
			foreach ( $loader->select() as $record ) {
				$hasRecords = true;
				$ip = $record->ipAsSubnetRange();
				if ( !isset( $ipCounts[ $ip ] ) ) {
					$ipCounts[ $ip ] = [];
				}
				$ipCounts[ $ip ][] = $record->id;
			}

			$page++;
		} while ( $hasRecords );

		return $ipCounts;
	}
}