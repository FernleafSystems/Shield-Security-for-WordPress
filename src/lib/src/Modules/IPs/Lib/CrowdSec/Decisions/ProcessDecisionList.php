<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Decisions;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Exceptions\ColumnDoesNotExistException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPRecords;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\CrowdSecDecisions\Ops as CrowdSecDB;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\CrowdSecConstants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class ProcessDecisionList {

	use ModConsumer;

	/**
	 * The stream is provided by Api\DecisionsDownload and ensures keys 'new' and 'deleted' are present.
	 */
	public function run( array $stream ) {
		$this->preRun();

		$deleted = is_array( $stream[ 'deleted' ] ) ? $this->delete( $stream[ 'deleted' ] ) : 0;
		$new = is_array( $stream[ 'new' ] ) ? $this->add( $stream[ 'new' ] ) : 0;

		if ( !empty( $new ) || !empty( $deleted ) ) {
			$this->getCon()->fireEvent( 'crowdsec_decisions_acquired', [
				'audit_params' => [
					'count_new'     => $new,
					'count_deleted' => $deleted,
				]
			] );
		}
	}

	/**
	 * Delete "expired" IPs - i.e. those older than 1 week.
	 */
	public function preRun() {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		try {
			$mod->getDbH_CrowdSecDecisions()
				->getQueryDeleter()
				->addWhere(
					'updated_at',
					Services::Request()
							->carbon()
							->subDays( 7 )->timestamp,
					'<'
				)
				->query();
		}
		catch ( ColumnDoesNotExistException $e ) {
		}
	}

	private function add( array $decisions ) :int {
		// We only handle "ip" scope right now, but we can add more as we need to.
		return $this->addForScope_IP( $this->extractDataFromDecisionsForScope_IP( $decisions ) );
	}

	public function addForScope_IP( array $ipList ) :int {

		$count = 0;
		if ( !empty( $ipList ) ) {
			/** @var ModCon $mod */
			$mod = $this->getMod();
			$dbhCS = $mod->getDbH_CrowdSecDecisions();

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
				$existingIPs = array_map(
					function ( $record ) {
						return $record[ 'ip' ];
					},
					$existingRecords
				);
				$toAdd = array_filter(
					$ipList,
					function ( $maybeAddIP ) use ( $existingIPs ) {
						return !empty( $maybeAddIP ) && !Services::IP()->checkIp( $maybeAddIP, $existingIPs );
					}
				);
			}

			foreach ( $toAdd as $ipToAdd ) {
				try {
					/** @var CrowdSecDB\Record $record */
					$record = $dbhCS->getRecord();
					$record->ip_ref = ( new IPRecords() )
						->setMod( $this->getCon()->getModule_Data() )
						->loadIP( $ipToAdd, true, false )
						->id;
					$dbhCS->getQueryInserter()->insert( $record );

					$count++;
				}
				catch ( \Exception $e ) {
				}
			}
		}

		return $count;
	}

	private function delete( array $decisions ) :int {
		// We only handle "ip" scopes right now, but we can add more as we need to.
		return $this->deleteForScope_IP( $this->extractDataFromDecisionsForScope_IP( $decisions ) );
	}

	private function deleteForScope_IP( array $ipList ) :int {
		$count = 0;
		if ( !empty( $ipList ) ) {
			/** @var ModCon $mod */
			$mod = $this->getMod();
			$dbhCS = $mod->getDbH_CrowdSecDecisions();
			$count = (int)Services::WpDb()->doSql( sprintf(
				"DELETE FROM `%s`
					WHERE `ip_ref` IN ( SELECT `id` FROM `%s` WHERE INET6_NTOA(`ip`) IN ('%s') )
				;",
				$dbhCS->getTableSchema()->table,
				$this->getCon()->getModule_Data()->getDbH_IPs()->getTableSchema()->table,
				implode( "','", $ipList )
			) );
		}

		return $count;
	}

	private function extractDataFromDecisionsForScope_IP( array $decisions ) :array {
		return array_filter( array_map(
			function ( $decision ) {
				$ip = null;
				if ( is_array( $decision ) ) {
					try {
						$ip = $this->getValueFromDecision( $decision, CrowdSecConstants::SCOPE_IP );
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
	private function getValueFromDecision( array $decision, string $scope ) :string {
		$srvIP = Services::IP();
		if ( empty( $decision[ 'scope' ] ) ) {
			throw new \Exception( 'Empty decision scope' );
		}
		if ( $decision[ 'scope' ] !== $scope ) {
			throw new \Exception( "Unsupported decision scope (i.e. not 'ip'): ".$decision[ 'scope' ] );
		}
		if ( empty( $decision[ 'value' ] ) ) {
			throw new \Exception( 'Empty decision value' );
		}

		$value = trim( (string)$decision[ 'value' ] );

		// simple verification of data we're going to import
		if ( $scope === CrowdSecConstants::SCOPE_IP && !$srvIP->isValidIp_PublicRemote( $value ) ) {
			throw new \Exception( 'Invalid decision value for scope (IP) provided: '.$value );
		}
		// elseif $scope === another support scope

		return $value;
	}
}