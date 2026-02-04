<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\Decisions\Scopes\V2;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Database\CleanIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\{
	IpRulesIterator,
	LoadIpRules,
	Ops as IpRulesDB
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\CrowdSec\CrowdSecConstants;
use FernleafSystems\Wordpress\Services\Services;
use IPLib\{
	Factory,
	Range\RangeInterface,
	Range\Type
};

class ProcessIPs extends ProcessBase {

	public const SCOPE = CrowdSecConstants::SCOPE_IP;

	protected function preRun() {
		( new CleanIpRules() )->expired_Crowdsec();
	}

	protected function postRun() {
		( new CleanIpRules() )->duplicates_Crowdsec();
	}

	/**
	 * NOTE: little or no handling for IP ranges
	 *
	 * 1. First removes all potential duplicates from the decision stream.
	 * 2. Creates any missing records in the IP table to support all the new CS IP Rules
	 * 3. Selects all the IP table records required to support all the new CS IP Rules
	 * 4. Create new IP Rules records.
	 */
	protected function processNew() :int {
		$DB = Services::WpDb();
		$now = Services::Request()->ts();

		$this->removeDuplicatesFromNewStream();

		$total = 0;

		$ipTableName = self::con()->db_con->ips->getTableSchema()->table;
		$pageSize = 100;
		do {
			$slice = \array_splice( $this->newDecisions, 0, $pageSize );
			$hasRecords = !empty( $slice );
			if ( $hasRecords ) {

				// 2. Insert all new IP addresses into the IP table that don't already exist.
				$DB->doSql( sprintf( 'INSERT IGNORE INTO `%s` (`ip`, `created_at`) VALUES %s;',
					$ipTableName,
					\implode( ',', \array_map(
						fn( $ip ) => sprintf( "(INET6_ATON('%s'), %s)", $ip, $now ),
						\array_keys( $slice )
					) )
				) );

				// 3. Select the IP records required to insert the new CS records.
				$ipRecords = $DB->selectCustom(
					sprintf( "SELECT `id`, INET6_NTOA(`ip`) as `ip` FROM `%s` WHERE `ip` IN (%s);",
						$ipTableName,
						\implode( ',', \array_map( fn( $ip ) => sprintf( "INET6_ATON('%s')", $ip ), \array_keys( $slice ) ) )
					)
				);

				$insertValues = [];
				foreach ( $ipRecords as $ipRecord ) {
					$recordIP = $ipRecord[ 'ip' ];

					/**
					 * PIA!
					 * IPv6 IPs can't be compared with simple string comparison, and we've no way to know what format
					 * the IPs will be in from CrowdSec vs from the MySQL DB.  E.g.
					 * 2400:8100:ffff::117:120:13:52  ===  2400:8100:ffff:0:117:120:13:52
					 *
					 * So we have to search for it using the "long"/verbose IP address format.
					 */
					if ( !isset( $slice[ $recordIP ] ) ) {
						$found = false;
						foreach ( \array_keys( $slice ) as $sliceIP ) {
							if ( Factory::parseAddressString( $sliceIP )->toString( true )
								 === Factory::parseAddressString( $recordIP )->toString( true ) ) {
								$slice[ $recordIP ] = $slice[ $sliceIP ];
								unset( $slice[ $sliceIP ] );
								$found = true;
								break;
							}
						}
						if ( !$found ) {
							//weird! We couldn't find an IP address that we had earlier inserted?
							continue;
						}
					}

					$insertValues[] = sprintf( "( %s, %s, '%s', %s, %s, %s, %s )",
						$ipRecord[ 'id' ], 32, IpRulesDB\Handler::T_CROWDSEC, $now, $slice[ $recordIP ][ 'expires_at' ], $now, $now );
					unset( $slice[ $recordIP ] );
				}

				// 4. Insert the new IP Rules records.
				$DB->doSql( sprintf( 'INSERT INTO `%s` (`ip_ref`, `cidr`, `type`, `blocked_at`, `expires_at`, `updated_at`, `created_at`) VALUES %s;',
					self::con()->db_con->ip_rules->getTable(),
					\implode( ', ', $insertValues )
				) );

				$total += \count( $insertValues );
			}
		} while ( $hasRecords );

		return $total;
	}

	/**
	 * Loop through all existing CS rules and if a new rule/decision already exists, remove it from the new stream.
	 */
	protected function removeDuplicatesFromNewStream() {
		$duplicates = [];

		$page = 0;
		$pageSize = 250;
		do {
			$slice = \array_slice( $this->newDecisions, $page*$pageSize, $pageSize );
			if ( !empty( $slice ) ) {

				// We don't need to deal with ranges just yet:
//				$singles = \array_keys( \array_filter( $slice, function ( array $dec ) {
//					/** @var RangeInterface $range */
//					$range = $dec[ 'parsed' ];
//					return $range->getSize() === 1;
//				} ) );

				$singles = \array_keys( $slice );
				if ( !empty( $singles ) ) {
					$loader = new LoadIpRules();
					$loader->wheres = [
						sprintf( "`ips`.`ip` IN (%s)",
							\implode( ',', \array_map( fn( $ip ) => sprintf( "INET6_ATON('%s')", $ip ), $singles ) )
						),
						sprintf( "`ir`.`type`='%s'", IpRulesDB\Handler::T_CROWDSEC )
					];

					foreach ( $loader->select() as $preExistingRule ) {
						if ( \in_array( $preExistingRule->ip, $singles ) ) {
							$duplicates[] = $preExistingRule->ip;
						}
						elseif ( \str_contains( $preExistingRule->ip, ':' ) ) {
							$preExistingIPv6 = Factory::parseAddressString( $preExistingRule->ip )->toString( true );
							// handle variance of IPv6 notation.
							foreach ( $singles as $sliceIP ) {
								if ( \str_contains( $sliceIP, ':' ) &&
									 Factory::parseAddressString( $sliceIP )->toString( true ) === $preExistingIPv6 ) {
									$duplicates[] = $sliceIP;
									break;
								}
							}
						}
					}
				}

//				$ranges = \array_filter( $slice, function ( array $dec ) {
//					/** @var RangeInterface $range */
//					$range = $dec[ 'parsed' ];
//					return $range->getSize() > 1;
//				} );
//				if ( !empty( $ranges ) ) {
//					// TODO.
//				}
			}

			$page++;
		} while ( !empty( $slice ) );

		$this->newDecisions = \array_diff_key( $this->newDecisions, \array_flip( $duplicates ) );
	}

	protected function processDeleted() :int {

		/** @var RangeInterface[] $ipsToDelete */
		$ipsToDelete = \array_map( function ( $ip ) {
			return Factory::parseRangeString( $ip );
		}, $this->deletedDecisions );

		$rulesIterator = new IpRulesIterator();
		$loader = $rulesIterator->getLoader();
		$loader->wheres = [
			sprintf( "`ir`.`type`='%s'", IpRulesDB\Handler::T_CROWDSEC )
		];
		$loader->joined_table_select_fields = [
			'ip_ref',
		];

		$idsToDelete = [];
		foreach ( $rulesIterator as $record ) {
			$recordAsRange = Factory::parseRangeString( $record->ipAsSubnetRange() );
			foreach ( $ipsToDelete as $toDeleteRange ) {
				if ( $toDeleteRange->containsRange( $recordAsRange ) ) {
					$idsToDelete[] = $record->id;
					break;
				}
			}
		}

		if ( !empty( $idsToDelete ) ) {
			self::con()
				->db_con
				->ip_rules
				->getQueryDeleter()
				->addWhereIn( 'id', $idsToDelete )
				->query();
		}

		return \count( $idsToDelete );
	}

	protected function extractScopeDecisionData_New( array $rawDecisionsGroups ) :array {
		$group = [];
		\array_map(
			function ( array $decisionsGroup ) use ( &$group ) {
				try {
					foreach ( $this->getDecisionValuesFromGroup( $decisionsGroup ) as $decision ) {
						// Since we are strictly only permitting non-ranged IPs, we optimise here and don't
						// store the Range object for use later on.  When this position changes, this code can update.
						$group[ $decision[ 'value' ] ] = [
							'expires_at' => $this->getDecisionExpiresAt( $decision )
							// 'parsed'     => Factory::parseRangeString( $ip ),
						];
					}
				}
				catch ( \Exception $e ) {
				}
				return null;
			},
			\array_filter( $rawDecisionsGroups, '\is_array' )
		);
		return $group;
	}

	/**
	 * We only require the IP addresses for the deleted stream in order to remove our decisions.
	 */
	protected function extractScopeDecisionData_Deleted( array $decisions ) :array {
		return \array_filter( \array_map(
			function ( $decision ) {
				$ip = null;
				if ( \is_array( $decision ) ) {
					try {
						$ip = $this->getDecisionValue( $decision );
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
	 * @return string
	 */
	protected function normaliseDecisionValue( $value ) {
		return \trim( (string)$value );
	}

	/**
	 * TODO: support ranges. We prevent ranges at this point from making their way through to full processing.
	 * We don't know how CS ranges will be formatted. Ideally CIDR.
	 * @inheritDoc
	 */
	protected function validateDecisionValue( $value ) :bool {
		$ip = Factory::parseRangeString( $value );
		return !empty( $ip ) && $ip->getRangeType() === Type::T_PUBLIC && $ip->getSize() === 1;
	}
}