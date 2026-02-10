<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Traffic;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\ReqLogs\{
	LoadRequestLogs,
	LogRecord,
	Ops as RegLogsDB,
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\Lib\GeoIP\LookupMeta;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForTraffic;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class BuildTrafficTableData extends \FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildTableData {

	/**
	 * @var LogRecord
	 */
	private $log;

	private array $ipInfo = [];

	protected function loadRecordsWithSearch() :array {
		return $this->loadRecordsWithDirectQuery();
	}

	protected function getSearchPanesData() :array {
		return $this->getSearchPanesDataBuilder()->build();
	}

	protected function getSearchPanesDataBuilder() :BuildSearchPanesData {
		return new BuildSearchPanesData();
	}

	/**
	 * @param LogRecord[] $records
	 */
	protected function buildTableRowsFromRawRecords( array $records ) :array {
		$this->primeUserCache( \array_map( fn( $log ) => $log->uid, $records ) );

		return \array_values( \array_filter( \array_map(
			function ( $log ) {

				$log->meta = \array_merge(
					[
						'ua'      => 'Unknown',
						'offense' => false,
						'uid'     => 0
					],
					$log->meta
				);

				$this->log = $log;

				$data = $log->getRawData();

				$data[ 'ip' ] = $this->log->ip;
				$data[ 'offense' ] = $this->log->offense ? 'Offense' : 'Not Offense';
				$data[ 'rid' ] = $this->log->rid ?? __( 'Unknown', 'wp-simple-firewall' );
				$data[ 'path' ] = empty( $this->log->path ) ? '-' : $this->log->path;

				$data[ 'country' ] = ( new LookupMeta() )
					->setIP( $this->log->ip )
					->countryCode();

				$data[ 'user' ] = $this->getTrafficUserDisplay( $this->log->uid );
				$data[ 'page' ] = $this->getColumnContent_Page();
				$data[ 'details' ] = $this->getColumnContent_Details();
				$data[ 'response' ] = $this->getColumnContent_Response();
				$data[ 'created_since' ] = $this->getColumnContent_Date( $this->log->created_at );
				$data[ 'day' ] = Services::Request()
										 ->carbon( true )->setTimestamp( $this->log->created_at )->toDateString();
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

	protected function validateSearchPanes( array $searchPanes ) :array {
		foreach ( $searchPanes as $column => &$values ) {
			switch ( $column ) {
				case 'offense':
					$values = \array_filter(
						\array_map( '\intval', $values ),
						fn( $offense ) => \in_array( $offense, [ 0, 1 ], true )
					);
					break;
				case 'type':
					$values = \array_intersect( $values, RegLogsDB\Handler::AllTypes() );
					break;
				case 'code':
					$values = \array_filter( \array_map( '\intval', $values ), fn( $c ) => $c === 0 || ( $c >= 100 && $c <= 999 ) );
					break;
				default:
					$values = $this->validateCommonColumn( $column, $values );
					break;
			}
		}
		return \array_filter( $searchPanes );
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
						$wheres[] = $this->buildSqlWhereForDaysSearch( $selected, 'req' );
						break;
					case 'ip':
						$wheres[] = sprintf( "`ips`.ip=INET6_ATON('%s')", \array_pop( $selected ) );
						break;
					case 'type':
						$wheres[] = sprintf( "`req`.`%s` IN ('%s')", $column, \implode( "','", $selected ) );
						break;
					case 'code':
					case 'offense':
						$wheres[] = sprintf( "`req`.`%s` IN (%s)", $column, \implode( ",", $selected ) );
						break;
					case 'user':
						$wheres[] = sprintf( "`req`.`uid` IN (%s)", \implode( ",", $selected ) );
						break;
					default:
						break;
				}
			}
		}

		$wheres = \array_merge( $wheres, $this->buildWheresFromCommonSearchParams() );

		$remaining = $this->parseSearchText()[ 'remaining' ];
		if ( !empty( $remaining ) ) {
			$wheres[] = \sprintf( "`req`.`path` LIKE '%%%s%%'", esc_sql( $remaining ) );
		}
		return $wheres;
	}

	protected function getRecordsLoader() :LoadRequestLogs {
		return new LoadRequestLogs();
	}

	protected function getSearchableColumns() :array {
		// Use the DataTables definition builder to locate searchable columns
		return \array_filter( \array_map(
			fn( $column ) => ( $column[ 'searchable' ] ?? false ) ? $column[ 'data' ] : '',
			( new ForTraffic() )->buildRaw()[ 'columns' ]
		) );
	}

	/**
	 * @return LogRecord[]
	 */
	protected function getRecords( array $wheres = [], int $offset = 0, int $limit = 0 ) :array {
		$loader = $this->getRecordsLoader();
		$loader->wheres = $wheres;
		$loader->limit = $limit;
		$loader->offset = $offset;
		$loader->order_by = $this->getOrderBy();
		$loader->order_dir = $this->getOrderDirection();
		return $loader->select();
	}

	private function getColumnContent_Details() :string {
		$code = ( new LookupMeta() )
			->setIP( $this->log->ip )
			->countryCode();
		if ( empty( $code ) ) {
			$country = '';//__( 'Unknown', 'wp-simple-firewall' );
		}
		else {
			$country = sprintf(
				'<img class="icon-flag" src="%s" alt="%s" width="24px"/> %s',
				sprintf( 'https://api.aptoweb.com/api/v1/country/flag/%s.svg', \strtolower( $code ) ),
				$code,
				$code
			);
		}

		if ( $this->isWpCli() ) {
			$content = 'WP-CLI';
		}
		else {
			$identity = $this->resolveIpIdentity( $this->log->ip ) ?? [ IpID::UNKNOWN, 'Unknown' ];

			$components = [
				sprintf( '<div class="text-nowrap">%s: %s%s</div>',
					__( 'IP', 'wp-simple-firewall' ),
					$this->getIpAnalysisLink( $this->log->ip ),
					$identity[ 0 ] === IpID::UNKNOWN ? '' : sprintf( ' (%s)', $identity[ 1 ] )
				),
			];

			$info = $this->getIpInfo( $this->log->ip );
			if ( !empty( $info ) ) {
				$components[] = sprintf( '%s: %s', __( 'IP Status', 'wp-simple-firewall' ), $info );
			}
			$components[] = sprintf( '%s: %s', __( 'Logged-In', 'wp-simple-firewall' ), $this->getTrafficUserDisplay( $this->log->uid ) );
			if ( !empty( $country ) ) {
				$components[] = sprintf( '%s: %s', __( 'Location', 'wp-simple-firewall' ), $country );
			}
			if ( !empty( $this->log->meta[ 'ua' ] ) ) {
				$components[] = esc_html( esc_js( sprintf( '%s - %s', __( 'User Agent', 'wp-simple-firewall' ), $this->log->meta[ 'ua' ] ) ) );
			}

			$content = sprintf( '<div>%s</div>', \implode( '</div><div>', $components ) );
		}

		return $content;
	}

	private function getColumnContent_Response() :string {
		if ( $this->log->code >= 400 ) {
			$codeType = 'danger';
		}
		elseif ( $this->log->code >= 300 ) {
			$codeType = 'warning';
		}
		else {
			$codeType = 'success';
		}

		return sprintf( '<div>%s</div>', \implode( '</div><div>', [
			sprintf( '%s: %s', __( 'Response', 'wp-simple-firewall' ),
				sprintf( '<span class="badge bg-%s">%s</span>', $codeType, $this->log->code ) ),
			sprintf( '%s: %s', __( 'Offense', 'wp-simple-firewall' ),
				sprintf(
					'<span class="badge bg-%s">%s</span>',
					@$this->log->offense ? 'danger' : 'info',
					@$this->log->offense ? __( 'Yes', 'wp-simple-firewall' ) : __( 'No', 'wp-simple-firewall' )
				)
			),
		] ) );
	}

	private function getColumnContent_Page() :string {
		$query = $this->log->meta[ 'query' ] ?? '';

		$content = sprintf( '<span class="badge bg-secondary me-1">%s</span>', RegLogsDB\Handler::GetTypeName( $this->log->type ) );
		$path = esc_html( $this->log->path );
		$query = esc_html( $query );
		return $content.(
			$this->isWpCli() ?
				sprintf( '<code>:> %s %s</code>', $path, $query )
				: sprintf( '%s: <code>%s%s</code>', \strtoupper( $this->log->verb ), $path, empty( $query ) ? '' : '?<br/>'.\ltrim( $query, '?' ) )
			);
	}

	private function getTrafficUserDisplay( int $uid ) :string {
		if ( $uid <= 0 ) {
			$display = __( 'No', 'wp-simple-firewall' );
		}
		else {
			$user = $this->resolveUser( $uid );
			$display = empty( $user ) ? __( 'Unknown', 'wp-simple-firewall' ) :
				sprintf( '<a href="%s" target="_blank" title="Go To Profile">%s</a>',
					Services::WpUsers()->getAdminUrl_ProfileEdit( $user ), $user->user_login );
		}
		return $display;
	}

	private function getIpInfo( string $ip ) {

		if ( !isset( $this->ipInfo[ $ip ] ) ) {

			if ( empty( $ip ) ) {
				$this->ipInfo[ '' ] = '';
			}
			else {
				$badgeTemplate = '<span class="badge bg-%s">%s</span>';
				$ipRuleStatus = new IpRuleStatus( $ip );
				if ( $ipRuleStatus->isBlocked() ) {
					$status = sprintf( $badgeTemplate, 'danger', __( 'Blocked', 'wp-simple-firewall' ) );
				}
				elseif ( $ipRuleStatus->isBypass() ) {
					$status = sprintf( $badgeTemplate, 'success', __( 'Bypass', 'wp-simple-firewall' ) );
				}
				elseif ( $ipRuleStatus->isAutoBlacklisted() ) {
					$offenses = $ipRuleStatus->getOffenses();
					$offensesString = sprintf( _n( '%s offense', '%s offenses', $offenses, 'wp-simple-firewall' ), $offenses );
					if ( $ipRuleStatus->isUnBlocked() ) {
						$status = __( 'Unblocked', 'wp-simple-firewall' );
						if ( $offenses > 0 ) {
							$status .= ' ('.$offensesString.')';
						}
					}
					else {
						$status = $offensesString;
					}
					$status = sprintf( $badgeTemplate, 'warning', $status );
				}
				else {
					$status = '';
				}

				$this->ipInfo[ $ip ] = $status;
			}
		}

		return $this->ipInfo[ $ip ];
	}

	private function isWpCli() :bool {
		return $this->log->type === RegLogsDB\Handler::TYPE_WPCLI;
	}
}
