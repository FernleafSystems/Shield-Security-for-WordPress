<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Decisions\Scopes;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\CleanIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\LoadIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\CrowdSecConstants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Services\Services;
use IPLib\Factory;
use IPLib\Range\RangeInterface;
use IPLib\Range\Type;

class ProcessIPs extends ProcessBase {

	const SCOPE = CrowdSecConstants::SCOPE_IP;

	public function preRun() {
		( new CleanIpRules() )
			->setMod( $this->getMod() )
			->execute();
	}

	public function postRun() {
		$this->preRun();
	}

	/**
	 * NOTE: little or no handling for IP ranges
	 *
	 * 1. First removes all potential duplicates from the decision stream.
	 * 2. Creates any missing records in the IP table to support all the new CS IP Rules
	 * 3. Selects all the IP table records required to support all the new CS IP Rules
	 * 4. Create new IP Rules records.
	 */
	protected function runForNew() :int {
		$DB = Services::WpDb();
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$now = Services::Request()->ts();

		// 1: Remove potential duplicate CS IP Rules
		$loader = ( new LoadIpRules() )->setMod( $mod );
		$loader->wheres = [
			sprintf( "`ir`.`type`='%s'", Handler::T_CROWDSEC )
		];
		$loader->joined_table_select_fields = [
			'cidr',
		];
		$loader->limit = 150;

		$page = 0;
		do {
			$loader->offset = $page*$loader->limit;
			/** @var RangeInterface[] $existingRanges */
			$existingRanges = array_map( function ( $record ) {
				return Factory::parseRangeString( sprintf( '%s/%s', $record->ip, $record->cidr ) );
			}, $loader->select() );

			foreach ( $existingRanges as $range ) {
				foreach ( $this->newDecisions as $key => $decision ) {
					if ( $range->containsRange( Factory::parseRangeString( $decision ) ) ) {
						unset( $this->newDecisions[ $key ] );
						break;
					}
				}
			}

			$page++;
		} while ( !empty( $existingRanges ) );

		$ipTableName = $this->getCon()
							->getModule_Data()
							->getDbH_IPs()
							->getTableSchema()->table;
		$page = 0;
		$pageSize = 100;
		do {
			$slice = array_slice( $this->newDecisions, $page*$pageSize, $pageSize );
			if ( !empty( $slice ) ) {

				// 2. Insert all new IP addresses into the IP table that don't already exist.
				$DB->doSql( sprintf( 'INSERT IGNORE INTO `%s` (`ip`, `created_at`) VALUES %s;',
					$ipTableName,
					implode( ', ', array_map( function ( $ip ) use ( $now ) {
						return sprintf( "( INET6_ATON('%s'), %s )", $ip, $now );
					}, $slice ) )
				) );

				// 3. Select the IP records required to insert the new CS records.
//				$ipRecords = $DB->selectCustom( sprintf( "SELECT `id`, INET6_NTOA(`ip`) as `ip` FROM `%s` WHERE `ip` IN (%s);",
				$ipRecords = $DB->selectCustom( sprintf( "SELECT `id` FROM `%s` WHERE `ip` IN (%s);",
					$ipTableName,
					implode( ', ', array_map( function ( $ip ) {
						return sprintf( "INET6_ATON('%s')", $ip );
					}, $slice ) )
				) );

				// 4. Insert the new IP Rules records.
				$DB->doSql( sprintf( 'INSERT INTO `%s` (`ip_ref`, `cidr`, `type`, `blocked_at`, `updated_at`, `created_at`) VALUES %s;',
					$mod->getDbH_IPRules()->getTableSchema()->table,
					implode( ', ', array_map( function ( $result ) use ( $now ) {
						return sprintf( "( %s, %s, '%s', %s, %s, %s )", $result[ 'id' ], 32, Handler::T_CROWDSEC, $now, $now, $now );
					}, $ipRecords ) )
				) );
			}

			$page++;
		} while ( !empty( $slice ) );

		return count( $this->newDecisions );
	}

	protected function runForDeleted() :int {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		/** @var RangeInterface[] $ipsToDelete */
		$ipsToDelete = array_map( function ( $ip ) {
			return Factory::parseRangeString( $ip );
		}, $this->deletedDecisions );

		$loader = ( new LoadIpRules() )->setMod( $mod );
		$loader->wheres = [
			sprintf( "`ir`.`type`='%s'", Handler::T_CROWDSEC )
		];
		$loader->joined_table_select_fields = [
			'ip_ref',
		];
		$loader->limit = 100;

		$idsToDelete = [];
		$page = 0;
		do {
			$loader->offset = $loader->limit*$page;
			$records = $loader->select();
			foreach ( $records as $record ) {
				$recordAsRange = Factory::parseRangeString( $record->ipAsSubnetRange() );
				foreach ( $ipsToDelete as $ipRange ) {
					if ( $ipRange->containsRange( $recordAsRange ) ) {
						$idsToDelete[] = $record->id;
						break;
					}
				}
			}

			$page++;
		} while ( !empty( $records ) );

		if ( !empty( $idsToDelete ) ) {
			$mod->getDbH_IPRules()
				->getQueryDeleter()
				->addWhereIn( 'id', $idsToDelete )
				->query();
		}

		return count( $idsToDelete );
	}

	protected function extractScopeDecisionData( array $decisions ) :array {
		return array_filter( array_map(
			function ( $decision ) {
				$ip = null;
				if ( is_array( $decision ) ) {
					try {
						$ip = $this->getValueFromDecision( $decision );
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
	 * @param mixed $value
	 * @return mixed
	 */
	protected function normaliseDecisionValue( $value ) {
		return trim( (string)$value );
	}

	/**
	 * TODO: support ranges. We prevent ranges at this point from making their way through to full processing.
	 * We don't know how CS ranges will be formatted. Ideally CIDR.
	 * @inheritDoc
	 */
	protected function validateDecisionValue( $value ) :bool {
		$ip = Factory::parseRangeString( $value );
		return !empty( $ip ) && $ip->getRangeType() === Type::T_PUBLIC && $ip->getSize() === 0;
	}
}