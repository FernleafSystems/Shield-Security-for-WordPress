<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Options;
use FernleafSystems\Wordpress\Services\Services;

class CleanIpRules extends ExecOnceModConsumer {

	protected function run() {
		$this->expired();
//		$this->duplicates();
	}

	public function expired() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		/** @var Options $opts */
		$opts = $this->getOptions();

		// Expired CrowdSec
		/** @var Ops\Delete $deleter */
		$deleter = $mod->getDbH_IPRules()->getQueryDeleter();
		$deleter
			->filterByType( Ops\Handler::T_CROWDSEC )
			->addWhereNewerThan( 0, 'expires_at' )
			->addWhereOlderThan( Services::Request()->ts(), 'expires_at' )
			->query();

		// Expired AutoBlock
		/** @var Ops\Delete $deleter */
		$deleter = $mod->getDbH_IPRules()->getQueryDeleter();
		$deleter
			->filterByType( Ops\Handler::T_AUTO_BLOCK )
			->addWhereOlderThan(
				Services::Request()
						->carbon()
						->subSeconds( $opts->getAutoExpireTime() )->timestamp,
				'last_access_at'
			)
			->query();
	}

	/**
	 * TODO: update for newer IPRules
	 * Find all records that reference duplicate IP addresses and delete surplus.
	 */
	public function duplicates() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbh = $mod->getDbH_IPRules();
		$WPDB = Services::WpDb();
		$raw = $WPDB->selectCustom( sprintf(
			'SELECT `ir`.`ip_ref` as `ip_ref`, COUNT(*) as `count`
			FROM `%s` as `ir`
			INNER JOIN `%s` as `ips` ON `ips`.`id` = `ir`.`ip_ref`
			GROUP BY `ir`.`ip_ref`;',
			$dbh->getTableSchema()->table,
			$this->getCon()->getModule_Data()->getDbH_IPs()->getTableSchema()->table
		) );

		if ( is_array( $raw ) ) {
			foreach ( $raw as $record ) {
				if ( is_array( $record ) && !empty( $record[ 'ip_ref' ] ) && $record[ 'count' ] > 1 ) {
					$WPDB->doSql( sprintf( "DELETE FROM `%s` WHERE `ip_ref`='%s' LIMIT %s;",
						$dbh->getTableSchema()->table,
						(int)$record[ 'ip_ref' ],
						$record[ 'count' ] - 1
					) );
				}
			}
		}

		return $raw;
	}
}