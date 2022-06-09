<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Decisions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\CrowdSec\Ops as CrowdSecDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ProcessDecisionList {

	use ModConsumer;

	public function run( array $decisionStream ) {
		if ( isset( $decisionStream[ 'deleted' ] ) && is_array( $decisionStream[ 'deleted' ] ) ) {
			$this->delete( $decisionStream[ 'deleted' ] );
		}
		if ( isset( $decisionStream[ 'new' ] ) && is_array( $decisionStream[ 'new' ] ) ) {
			$this->add( $decisionStream[ 'new' ] );
		}
	}

	public function add( array $decisions ) {
		$ipList = $this->getIpsFromDecisions( $decisions );

		if ( !empty( $ipList ) ) {
			/** @var ModCon $mod */
			$mod = $this->getMod();
			$dbhCS = $mod->getDbH_CrowdSec();

			$existingRecords = Services::WpDb()->selectCustom( sprintf(
				"SELECT INET6_NTOA(`ips`.`ip`) as ip
					FROM `%s` as `ips`
					INNER JOIN `%s` as `cs` ON `ips`.`id` = `cs`.`ip_ref`
					WHERE INET6_NTOA(`ips`.`ip`) IN ('%s')
				",
				$this->getCon()->getModule_Data()->getDbH_IPs()->getTableSchema()->table,
				$dbhCS->getTableSchema()->table,
				implode( "','", $ipList )
			) );

			if ( empty( $existingRecords ) || !is_array( $existingRecords ) ) {
				$toAdd = $ipList;
			}
			else {
				// Filter out the current records so that we don't "re-add" them
				$currentIPs = array_map(
					function ( $record ) {
						return $record[ 'ip' ];
					},
					$existingRecords
				);
				$toAdd = array_filter(
					$ipList,
					function ( $maybeAddIP ) use ( $currentIPs ) {
						return !empty( $maybeAddIP ) && !Services::IP()->checkIp( $maybeAddIP, $currentIPs );
					}
				);
			}

			if ( !empty( $toAdd ) ) {
				foreach ( $toAdd as $ipToAdd ) {
					try {
						$this->insertIP( $ipToAdd );
					}
					catch ( \Exception $e ) {
					}
				}
			}
		}
	}

	private function delete( array $decisions ) {
		$ipList = $this->getIpsFromDecisions( $decisions );

		if ( !empty( $ipList ) ) {
			/** @var ModCon $mod */
			$mod = $this->getMod();
			$dbhCS = $mod->getDbH_CrowdSec();
			Services::WpDb()->doSql( sprintf(
				"DELETE FROM `%s` as `cs`
					INNER JOIN `%s` as `ips` ON `ips`.`id` = `cs`.`ip_ref`
					WHERE INET6_NTOA(`ips`.`ip`) IN ('%s')
				",
				$dbhCS->getTableSchema()->table,
				$this->getCon()->getModule_Data()->getDbH_IPs()->getTableSchema()->table,
				implode( "','", $ipList )
			) );
		}
	}

	/**
	 * @throws \Exception
	 */
	private function insertIP( string $ip ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$dbhCS = $mod->getDbH_CrowdSec();
		/** @var CrowdSecDB\Record $record */
		$record = $dbhCS->getRecord();
		$record->ip_ref = ( new IPRecords() )
			->setMod( $this->getCon()->getModule_Data() )
			->loadIP( $ip, true, false )
			->id;
		$dbhCS->getQueryInserter()->insert( $record );
	}

	private function getIpsFromDecisions( array $decisions ) :array {
		return array_filter( array_map(
			function ( $decision ) {
				$ip = null;
				if ( is_array( $decision ) ) {
					try {
						$ip = $this->getIpFromDecision( $decision );
					}
					catch ( \Exception $e ) {
					}
				}
				return $ip;
			},
			$decisions
		) );
	}

	/**
	 * @throws \Exception
	 */
	private function getIpFromDecision( array $decision ) :string {
		$srvIP = Services::IP();
		$ip = trim( (string)( $decision[ 'start_ip' ] ?? '' ) );
		if ( empty( $ip ) ) {
			throw new \Exception( 'Empty start_ip' );
		}
		if ( !$srvIP->isValidIp_PublicRemote( $ip ) ) {
			throw new \Exception( 'Invalid start_ip' );
		}
		if ( !empty( $decision[ 'end_ip' ] ) && !$srvIP->checkIp( $ip, $decision[ 'end_ip' ] ) ) {
			throw new \Exception( 'Ranges are not supported yet.' );
		}
		return $ip;
	}
}