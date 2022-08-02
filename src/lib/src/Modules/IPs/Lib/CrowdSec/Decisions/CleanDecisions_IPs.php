<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Decisions;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Exceptions\ColumnDoesNotExistException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Services\Services;

class CleanDecisions_IPs extends ExecOnceModConsumer {

	protected function run() {
		$this->old();
		$this->duplicates();
	}

	/**
	 * The stream is provided by Api\DecisionsDownload and ensures keys 'new' and 'deleted' are present.
	 */
	public function old( int $days = 7 ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		try {
			$mod->getDbH_CrowdSecDecisions()
				->getQueryDeleter()
				->addWhere(
					'updated_at',
					Services::Request()
							->carbon()
							->subDays( $days )->timestamp,
					'<'
				)
				->query();
		}
		catch ( ColumnDoesNotExistException $e ) {
		}
	}

	public function ipList( array $ipList ) :int {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbhCS = $mod->getDbH_CrowdSecDecisions();
		$ipList = array_filter( array_map( 'trim', array_filter( $ipList ) ), function ( $ip ) {
			return Services::IP()->isValidIp( $ip );
		} );
		return empty( $ipList ) ? 0 :
			(int)Services::WpDb()->doSql( sprintf(
				"DELETE FROM `%s` WHERE `ip_ref` IN ( SELECT `id` FROM `%s` WHERE INET6_NTOA(`ip`) IN ('%s') );",
				$dbhCS->getTableSchema()->table,
				$this->getCon()->getModule_Data()->getDbH_IPs()->getTableSchema()->table,
				implode( "','", $ipList )
			) );
	}

	/**
	 * Find all records that reference duplicate IP addresses and delete surplus.
	 */
	public function duplicates() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$WPDB = Services::WpDb();
		$raw = $WPDB->selectCustom( sprintf(
			'SELECT `csd`.`ip_ref` as `ip_ref`,COUNT(*) as `count`
			FROM `%s` as `csd`
			INNER JOIN `%s` as `ips` ON `ips`.`id` = `csd`.`ip_ref`
			GROUP BY `csd`.`ip_ref`;',
			$mod->getDbH_CrowdSecDecisions()->getTableSchema()->table,
			$this->getCon()->getModule_Data()->getDbH_IPs()->getTableSchema()->table
		) );

		if ( is_array( $raw ) ) {
			foreach ( $raw as $record ) {
				if ( is_array( $record ) && !empty( $record[ 'ip_ref' ] ) && $record[ 'count' ] > 1 ) {
					$WPDB->doSql( sprintf( "DELETE FROM `%s` WHERE `ip_ref`='%s' LIMIT %s;",
						$mod->getDbH_CrowdSecDecisions()->getTableSchema()->table,
						(int)$record[ 'ip_ref' ],
						$record[ 'count' ] - 1
					) );
				}
			}
		}

		return $raw;
	}
}