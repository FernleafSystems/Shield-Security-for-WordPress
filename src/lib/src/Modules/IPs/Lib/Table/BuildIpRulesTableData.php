<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPGeoVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\Lib\GeoIP\Lookup;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\CleanIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\IpRuleRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\LoadIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildTableData;
use FernleafSystems\Wordpress\Services\Services;

class BuildIpRulesTableData extends BaseBuildTableData {

	use ModConsumer;

	/**
	 * @var Lookup
	 */
	private $geoLookup;

	protected function loadRecordsWithSearch() :array {
		return $this->loadRecordsWithDirectQuery();
	}

	protected function getSearchPanesData() :array {
		return ( new BuildSearchPanesData() )->build();
	}

	/**
	 * @param IpRuleRecord[] $records
	 */
	protected function buildTableRowsFromRawRecords( array $records ) :array {
		return \array_values( \array_filter( \array_map(
			function ( $record ) {
				$data = $record->getRawData();
				$geo = $this->getCountryIP( $record->ip );
				$data[ 'ip_linked' ] = $this->getColumnContent_LinkedIP( $record->ipAsSubnetRange(), $record->id );
				$data[ 'is_blocked' ] = $record->ip > 0;
				$data[ 'status' ] = $this->getColumnContent_Status( $record );
				$data[ 'type' ] = Handler::GetTypeName( $data[ 'type' ] );
				$data[ 'country' ] = empty( $geo->countryCode ) ?
					__( 'Unknown', 'wp-simple-firewall' ) : $geo->countryName;
				$data[ 'last_seen' ] = $this->getColumnContent_LastSeen( $record->last_access_at );
				$data[ 'unblocked_at' ] = $this->getColumnContent_UnblockedAt( $record->unblocked_at );
				$data[ 'created_since' ] = $this->getColumnContent_Date( $record->created_at );
				$data[ 'day' ] = Services::Request()
										 ->carbon( true )->setTimestamp( $record->last_access_at )->toDateString();

				return $data;
			},
			$records
		) ) );
	}

	protected function countTotalRecords() :int {
		return $this->getRecordsLoader()->countAll();
	}

	protected function countTotalRecordsFiltered() :int {
		$loader = $this->getRecordsLoader();
		$loader->wheres = $this->buildWheresFromSearchParams();
		return $loader->countAll();
	}

	/**
	 * The Wheres need to align with the structure of the Query called from getRecords()
	 */
	protected function buildWheresFromSearchParams() :array {
		$wheres = [];
		if ( !empty( $this->table_data[ 'searchPanes' ] ) ) {
			foreach ( \array_filter( $this->table_data[ 'searchPanes' ] ) as $column => $selected ) {
				switch ( $column ) {
					case 'day':
						$wheres[] = $this->buildSqlWhereForDaysSearch( $selected, 'ir', 'last_access_at' );
						break;
					case 'ip':
						$wheres[] = sprintf( "`ir`.`id` IN (%s)", \implode( ',', \array_map( '\intval', $selected ) ) );
						break;
					case 'type':
						$selected = \array_filter( $selected, function ( $type ) {
							return Handler::IsValidType( (string)$type );
						} );
						$wheres[] = sprintf( "`ir`.`type` IN ('%s')", \implode( "','", $selected ) );
						break;
					case 'is_blocked':
						if ( \count( $selected ) === 1 ) {
							$wheres[] = sprintf( "`ir`.`blocked_at`%s'0'", \current( $selected ) ? '>' : '=' );
						}
						break;
					default:
						break;
				}
			}
		}

		if ( !empty( $this->table_data[ 'search' ][ 'value' ] ) ) {
			$ip = \preg_replace( '#[^0-9a-f:.]#i', '', $this->table_data[ 'search' ][ 'value' ] );
			if ( !empty( $ip ) ) {
				$wheres[] = sprintf( "INET6_NTOA(`ips`.`ip`) LIKE '%%%s%%'", $ip );
			}
		}
		return $wheres;
	}

	protected function getRecordsLoader() :LoadIpRules {
		return new LoadIpRules();
	}

	protected function getSearchableColumns() :array {
		// Use the DataTables definition builder to locate searchable columns
		return \array_filter( \array_map(
			function ( $column ) {
				return ( $column[ 'searchable' ] ?? false ) ? $column[ 'data' ] : '';
			},
			( new ForIpRules() )->buildRaw()[ 'columns' ]
		) );
	}

	/**
	 * @return IpRuleRecord[]
	 */
	protected function getRecords( array $wheres = [], int $offset = 0, int $limit = 0 ) :array {
		$cleaner = new CleanIpRules();
		$cleaner->expired();
		$cleaner->duplicates_AutoBlock();

		$loader = $this->getRecordsLoader();
		$loader->wheres = $wheres;
		$loader->limit = empty( $limit ) ? 10 : $limit;
		$loader->offset = $offset;
		$loader->order_by = $this->getOrderBy();
		$loader->order_dir = $this->getOrderDirection();
		return $loader->select();
	}

	private function getColumnContent_Status( IpRuleRecord $record ) :string {
		$opts = $this->opts();

		$content = [
			sprintf( '%s: <code>%s</code>', __( 'Rule Type', 'wp-simple-firewall' ), Handler::GetTypeName( $record->type ) )
		];

		if ( $record->type === Handler::T_AUTO_BLOCK ) {
			$content[] = sprintf( '%s: <span class="badge text-bg-warning">%s</span>', __( 'Offenses', 'wp-simple-firewall' ), $record->offenses );
		}

		if ( $record->type === Handler::T_MANUAL_BYPASS ) {
			$content[] = sprintf( '%s: %s', __( 'Label', 'wp-simple-firewall' ), $record->label );
		}

		if ( \in_array( $record->type, [ Handler::T_AUTO_BLOCK, Handler::T_MANUAL_BLOCK, Handler::T_CROWDSEC ] ) ) {

			if ( $record->blocked_at > 0 ) {
				if ( $record->blocked_at > $record->unblocked_at ) {
					$color = 'danger';
					$blockedStatus = __( 'Blocked', 'wp-simple-firewall' );

					switch ( $record->type ) {
						case Handler::T_AUTO_BLOCK:
							if ( ( new IpRuleStatus( $record->ip ) )->hasHighReputation() ) {
								$color = 'warning';
								$blockedStatus = sprintf( '%s (%s: %s)',
									__( 'Blocked/High Reputation', 'wp-simple-firewall' ), __( 'expires', 'wp-simple-firewall' ),
									Services::Request()
											->carbon()
											->timestamp( $record->last_access_at )
											->addSeconds( $opts->getAutoExpireTime() )
											->diffForHumans() );
							}
							else {
								$blockedStatus = sprintf( '%s (%s: %s)', $blockedStatus, __( 'expires', 'wp-simple-firewall' ),
									Services::Request()
											->carbon()
											->timestamp( $record->last_access_at )
											->addSeconds( $opts->getAutoExpireTime() )
											->diffForHumans() );
							}
							break;
						case Handler::T_CROWDSEC:
							$blockedStatus = sprintf( '%s (%s: %s)', $blockedStatus, __( 'expires', 'wp-simple-firewall' ),
								Services::Request()
										->carbon()
										->timestamp( $record->updated_at )
										->addDays( 7 )
										->diffForHumans() );
							break;
						case Handler::T_MANUAL_BLOCK:
							$blockedStatus = sprintf( '%s (%s)', $blockedStatus, __( 'permanently', 'wp-simple-firewall' ) );
							break;
					}
				}
				else {
					$color = 'warning';
					$blockedStatus = __( 'Unblocked', 'wp-simple-firewall' );
				}
			}
			else {
				$color = 'warning';
				$remaining = $opts->getOffenseLimit() - $record->offenses;
				$blockedStatus = sprintf(
					_n( '%s offense until block',
						'%s offenses until block',
						$remaining,
						'wp-simple-firewall'
					), $remaining );
			}

			$content[] = sprintf( '%s: <span class="badge text-bg-%s">%s</span>', __( 'IP Block Status', 'wp-simple-firewall' ), $color, $blockedStatus );
		}

		return \implode( '<br/>', $content );
	}

	private function getColumnContent_LastSeen( int $ts ) :string {
		if ( empty( $ts ) ) {
			$content = __( 'Never Seen', 'wp-simple-firewall' );
		}
		else {
			$content = $this->getColumnContent_Date( $ts );
		}
		return $content;
	}

	private function getColumnContent_UnblockedAt( int $ts ) :string {
		if ( empty( $ts ) ) {
			$content = __( 'Never', 'wp-simple-firewall' );
		}
		else {
			$content = $this->getColumnContent_Date( $ts );
		}
		return $content;
	}

	private function getCountryIP( string $ip ) :IPGeoVO {
		if ( empty( $this->geoLookup ) ) {
			$this->geoLookup = new Lookup();
		}
		return $this->geoLookup
			->setIP( $ip )
			->lookup();
	}
}