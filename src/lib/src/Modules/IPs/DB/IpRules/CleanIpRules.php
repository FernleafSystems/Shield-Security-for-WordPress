<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class CleanIpRules {

	use ExecOnce;
	use ModConsumer;

	protected function run() {
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

	public function expired_AutoBlock() {
		// Expired AutoBlock
		/** @var Ops\Delete $deleter */
		$deleter = $this->mod()->getDbH_IPRules()->getQueryDeleter();
		$deleter
			->filterByType( Handler::T_AUTO_BLOCK )
			->addWhereOlderThan(
				Services::Request()
						->carbon()
						->subSeconds( $this->opts()->getAutoExpireTime() )->timestamp,
				'last_access_at'
			)
			->query();
	}

	public function expired_Crowdsec() {
		/** @var Ops\Delete $deleter */
		$deleter = $this->mod()->getDbH_IPRules()->getQueryDeleter();
		$deleter->filterByType( Handler::T_CROWDSEC )
				->addWhereOlderThan( Services::Request()->ts(), 'expires_at' )
				->query();
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
			\array_keys( \array_filter( $this->getIpCountsForType( Handler::T_AUTO_BLOCK ), function ( $IDs ) {
				return \count( $IDs ) > 1;
			} ) )
		);
	}

	/**
	 * Find all records that reference duplicate IP addresses and delete surplus.
	 */
	public function duplicates_Crowdsec() {

		$allCounts = \array_filter( $this->getIpCountsForType( Handler::T_CROWDSEC ), function ( $IDs ) {
			return \count( $IDs ) > 1;
		} );

		$deleteIDs = [];
		foreach ( $allCounts as $ipIDs ) {
			\array_pop( $ipIDs );
			$deleteIDs = \array_merge( $deleteIDs, $ipIDs );
		}

		if ( !empty( $deleteIDs ) ) {
			/** @var Ops\Delete $deleter */
			$deleter = $this->mod()->getDbH_IPRules()->getQueryDeleter();
			$deleter
				->filterByType( Handler::T_AUTO_BLOCK )
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