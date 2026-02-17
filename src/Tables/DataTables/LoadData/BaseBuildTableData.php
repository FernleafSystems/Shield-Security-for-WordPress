<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\SearchPanes\BuildDataForDays;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

/**
 * @property array $table_data
 */
abstract class BaseBuildTableData extends DynPropertiesClass {

	use PluginControllerConsumer;

	private const TOTAL_COUNT_CACHE_TTL = 10;

	private array $ipIdCache = [];

	private array $userCache = [];

	private array $parsedSearch;

	abstract protected function countTotalRecords() :int;

	abstract protected function countTotalRecordsFiltered() :int;

	abstract protected function buildTableRowsFromRawRecords( array $records ) :array;

	abstract protected function getSearchPanesDataBuilder() :BaseBuildSearchPanesData;

	public function build() :array {
		try {
			// loadForRecords() MUST run first — validateSearchPanes() sanitises
			// table_data['searchPanes'] in-place before any WHERE building.
			$data = $this->loadForRecords();
			$totalCount = $this->getOrCacheTotalCount();
			return [
				'data'            => $data,
				'recordsTotal'    => $totalCount,
				'recordsFiltered' => empty( $this->buildWheresFromSearchParams() )
					? $totalCount
					: $this->countTotalRecordsFiltered(),
				'searchPanes'     => $this->getSearchPanesData(),
			];
		}
		catch ( ImpossibleQueryException $e ) {
			return [
				'data'            => [],
				'recordsTotal'    => $this->getOrCacheTotalCount(),
				'recordsFiltered' => 0,
				'searchPanes'     => $this->getSearchPanesData(),
			];
		}
	}

	/**
	 * Transient key for caching the unfiltered total count.
	 * Return empty string to disable caching (e.g. when the "total"
	 * varies by per-request constructor parameters).
	 */
	protected function getTotalCountCacheKey() :string {
		return 'shield_dt_total_'.\md5( static::class );
	}

	private function getOrCacheTotalCount() :int {
		$key = $this->getTotalCountCacheKey();
		if ( empty( $key ) ) {
			$count = $this->countTotalRecords();
		}
		else {
			$count = get_transient( $key );
			if ( $count === false ) {
				$count = $this->countTotalRecords();
				set_transient( $key, $count, self::TOTAL_COUNT_CACHE_TTL );
			}
		}
		return (int)$count;
	}

	protected function getSearchPanesData() :array {
		return [];
	}

	public function loadForRecords() :array {
		if ( empty( $this->parseSearchText()[ 'remaining' ] ) ) {
			return $this->loadRecordsWithDirectQuery();
		}
		else {
			return $this->loadRecordsWithSearch();
		}
	}

	protected function loadRecordsWithDirectQuery() :array {
		if ( !empty( $this->table_data[ 'searchPanes' ] ) ) {
			$this->table_data[ 'searchPanes' ] = $this->validateSearchPanes( $this->table_data[ 'searchPanes' ] );
		}

		return $this->buildTableRowsFromRawRecords(
			$this->getRecords(
				$this->buildWheresFromSearchParams(),
				(int)$this->table_data[ 'start' ],
				(int)$this->table_data[ 'length' ]
			)
		);
	}

	protected function loadRecordsWithSearch() :array {
		$start = (int)$this->table_data[ 'start' ];
		$length = (int)$this->table_data[ 'length' ];
		$search = $this->parseSearchText()[ 'remaining' ];

		if ( !empty( $this->table_data[ 'searchPanes' ] ) ) {
			$this->table_data[ 'searchPanes' ] = $this->validateSearchPanes( $this->table_data[ 'searchPanes' ] );
		}

		$wheres = $this->buildWheresFromSearchParams();

		$searchableColumns = \array_flip( $this->getSearchableColumns() );

		// We keep building logs and filtering by the search string until we have
		// enough records built to return in order to satisfy the start + length.
		$results = [];
		$page = 0;
		$pageLength = 100;
		do {
			$interimResults = $this->buildTableRowsFromRawRecords(
				$this->getRecords( $wheres, $page*$pageLength, $pageLength )
			);
			// no more table results to process, so go with what we have.
			if ( empty( $interimResults ) ) {
				break;
			}

			foreach ( $interimResults as $result ) {

				if ( empty( $search ) ) {
					$results[] = $result;
				}
				else {
					$searchable = \array_intersect_key( $result, $searchableColumns );
					foreach ( $searchable as $value ) {
						$value = wp_strip_all_tags( $value );
						if ( !\is_string( $search ) ) {
//							error_log( var_export( $search, true ) );
							continue;
						}
						if ( \stripos( $value, $search ) !== false ) {
							$results[] = $result;
							break;
						}
					}
				}
			}

			$page++;
		} while ( \count( $results ) < $start + $length );

		$results = \array_values( $results );
		if ( \count( $results ) < $start ) {
			$results = [];
		}
		else {
			$results = \array_splice( $results, $start, $length );
		}

		return \array_values( $results );
	}

	protected function parseSearchText() :array {
		return $this->parsedSearch ??= SearchTextParser::Parse( (string)( $this->table_data[ 'search' ][ 'value' ] ?? '' ) );
	}

	protected function buildSqlWhereForIpColumn( string $ipTerm ) :string {
		return \sprintf( "INET6_NTOA(`ips`.`ip`) LIKE '%%%s%%'", $ipTerm );
	}

	protected function buildWheresFromCommonSearchParams() :array {
		$wheres = [];
		$ipWhere = $this->buildSqlWhereForIpSearch();
		if ( !empty( $ipWhere ) ) {
			$wheres[] = $ipWhere;
		}
		foreach ( [ SearchTextParser::USER_ID, SearchTextParser::USER_NAME, SearchTextParser::USER_EMAIL ] as $param ) {
			$userWhere = $this->buildSqlWhereForUserSearch( $param );
			if ( !empty( $userWhere ) ) {
				$wheres[] = $userWhere;
			}
		}
		$requestIDWhere = $this->buildSqlWhereForRequestIdSearch();
		if ( !empty( $requestIDWhere ) ) {
			$wheres[] = $requestIDWhere;
		}
		return $wheres;
	}

	protected function buildSqlWhereForIpSearch() :string {
		$parsed = $this->parseSearchText();
		return empty( $parsed[ 'ip' ] ) ? '' : $this->buildSqlWhereForIpColumn( $parsed[ 'ip' ] );
	}

	protected function buildSqlWhereForUserSearch( string $searchParam ) :string {
		$where = '';
		$parsed = $this->parseSearchText();
		$searchValue = $parsed[ $searchParam ] ?? '';
		if ( \in_array( $searchParam, [ SearchTextParser::USER_NAME, SearchTextParser::USER_EMAIL, SearchTextParser::USER_ID ], true )
			 && !empty( $searchValue ) ) {
			if ( $searchParam === SearchTextParser::USER_NAME ) {
				$user = Services::WpUsers()->getUserByUsername( $searchValue );
			}
			elseif ( $searchParam === SearchTextParser::USER_EMAIL ) {
				$user = Services::WpUsers()->getUserByEmail( $searchValue );
			}
			else {
				$user = Services::WpUsers()->getUserById( $searchValue );
			}
			if ( empty( $user ) ) {
				throw new ImpossibleQueryException( $searchParam );
			}
			$where = \sprintf( "`req`.`uid`=%d", $user->ID );
		}
		return $where;
	}

	protected function buildSqlWhereForRequestIdSearch() :string {
		$requestID = $this->parseSearchText()[ SearchTextParser::REQUEST_ID ] ?? '';
		return empty( $requestID ) ? '' : \sprintf( "`req`.`req_id`='%s'", esc_sql( $requestID ) );
	}

	/**
	 * Security: validate searchPanes data before building any SQL queries.
	 * Handles common columns (day, ip for IP addresses, user) that are validated the same across all table builders.
	 * Child classes should override and handle their specific columns, calling validateCommonColumn() in default case.
	 */
	protected function validateSearchPanes( array $searchPanes ) :array {
		foreach ( $searchPanes as $column => &$values ) {
			$values = $this->validateCommonColumn( $column, $values );
		}
		return \array_filter( $searchPanes );
	}

	/**
	 * Helper method to validate a single common column. Used by child classes in their default case.
	 */
	protected function validateCommonColumn( string $column, array $values ) :array {
		switch ( $column ) {
			case 'day':
				$values = \array_filter( $values,
					fn( $day ) => $day === BuildDataForDays::ZERO_DATE_FORMAT || \preg_match( '#^\d+-\d+-\d+$#', $day )
				);
				break;
			case 'ip':
				// Note: This validates IP addresses. For IP rule IDs, child class should handle 'ip' column in its switch.
				$values = \array_filter( $values, fn( $ip ) => !empty( $ip ) && Services::IP()->isValidIp( $ip ) );
				break;
			case 'user':
			case 'uid':
				$values = \array_filter( \array_map( '\intval', $values ), fn( $uid ) => $uid > 0 );
				break;
			default:
				break;
		}
		return $values;
	}

	protected function buildWheresFromSearchParams() :array {
		return [];
	}

	protected function getOrderBy() :string {
		$orderBy = '';
		if ( !empty( $this->table_data[ 'order' ] ) ) {
			$col = $this->table_data[ 'order' ][ 0 ][ 'column' ];
			$sortCol = $this->table_data[ 'columns' ][ $col ][ 'data' ];
			$orderBy = \is_array( $sortCol ) ? $sortCol[ 'sort' ] : $sortCol;
		}
		return $orderBy;
	}

	protected function getOrderDirection() :string {
		$dir = 'DESC';
		if ( !empty( $this->table_data[ 'order' ] ) ) {
			$dir = \strtoupper( $this->table_data[ 'order' ][ 0 ][ 'dir' ] );
			if ( !\in_array( $dir, [ 'ASC', 'DESC' ] ) ) {
				$dir = 'DESC';
			}
		}
		return $dir;
	}

	protected function getRecords( array $wheres = [], int $offset = 0, int $limit = 0 ) :array {
		return [];
	}

	protected function getColumnContent_Date( int $ts, bool $includeTimestamp = true ) :string {
		return sprintf( '%s%s',
			Services::Request()
					->carbon( true )
					->setTimestamp( $ts )
					->diffForHumans(),
			$includeTimestamp ? sprintf( '<br /><small>%s</small>',
				Services::WpGeneral()->getTimeStringForDisplay( $ts ) ) : ''
		);
	}

	protected function resolveIpIdentity( string $ip ) :?array {
		if ( !isset( $this->ipIdCache[ $ip ] ) ) {
			try {
				$this->ipIdCache[ $ip ] = $this->createIpIdentifier( $ip )->run();
			}
			catch ( \Exception $e ) {
				$this->ipIdCache[ $ip ] = false;
			}
		}
		$result = $this->ipIdCache[ $ip ];
		return $result === false ? null : $result;
	}

	protected function createIpIdentifier( string $ip ) :IpID {
		return new IpID( $ip );
	}

	protected function resolveUser( int $uid ) {
		$this->userCache[ $uid ] ??= Services::WpUsers()->getUserById( $uid ) ?? false;
		return $this->userCache[ $uid ] === false ? null : $this->userCache[ $uid ];
	}

	protected function primeUserCache( array $uids ) :void {
		$uids = \array_unique( \array_filter( \array_map( '\intval', $uids ), fn( $uid ) => $uid > 0 ) );
		if ( !empty( $uids ) && \function_exists( 'cache_users' ) ) {
			cache_users( $uids );
		}
	}

	protected function getColumnContent_LinkedIP( string $ip, int $recordDeleteID = -1 ) :string {
		if ( !empty( $ip ) ) {
			$ipID = $this->resolveIpIdentity( $ip );
			if ( $ipID !== null ) {
				if ( $ipID[ 0 ] === IpID::THIS_SERVER ) {
					$id = __( 'This Server', 'wp-simple-firewall' );
				}
				elseif ( $ipID[ 0 ] === IpID::VISITOR ) {
					$id = __( 'This Is You', 'wp-simple-firewall' );
				}
				else {
					$id = $ipID[ 1 ];
				}
			}
			else {
				$id = '';
			}

			$deleteLink = sprintf( '<a href="javascript:{}" data-rid="%s" class="ip_delete text-danger svg-container" title="%s">%s</a>',
				$recordDeleteID,
				__( 'Delete IP', 'wp-simple-firewall' ),
				self::con()->svgs->raw( 'trash3-fill.svg' )
			);

			$content = \implode( '', \array_filter( [
				sprintf( '<h6 class="text-nowrap mb-0"><span class="me-1">%s</span>%s</h6>',
					$recordDeleteID >= 0 ? $deleteLink : '',
					$this->getIpAnalysisLink( $ip )
				),
				sprintf( '<small>%s</small>', esc_html( $id ) )
			] ) );
		}
		else {
			$content = 'No IP';
		}
		return $content;
	}

	protected function getUserHref( int $uid ) :string {
		$user = $this->resolveUser( $uid );
		return empty( $user ) ? sprintf( 'Unavailable (ID:%s)', $uid ) :
			sprintf( '<a href="%s" target="_blank">%s</a>',
				Services::WpUsers()->getAdminUrl_ProfileEdit( $user ), $user->user_login );
	}

	protected function getIpAnalysisLink( string $ip ) :string {
		$srvIP = Services::IP();

		if ( $srvIP->isValidIpRange( $ip ) ) {
			$content = sprintf( '<a href="%s" target="_blank" title="%s">%s</a>',
				$srvIP->getIpWhoisLookup( $ip ),
				__( 'IP Analysis', 'wp-simple-firewall' ),
				$ip
			);
		}
		elseif ( $srvIP->isValidIp( $ip ) ) {
			$content = sprintf(
				'<a href="%s" title="%s" class="%s" data-ip="%s">%s</a>',
				self::con()->plugin_urls->ipAnalysis( $ip ),
				__( 'IP Analysis', 'wp-simple-firewall' ),
				'offcanvas_ip_analysis ipv'.$srvIP->version( $ip ),
				$ip,
				$ip
			);
		}
		else {
			$content = __( 'IP Unavailable', 'wp-simple-firewall' );
		}
		return $content;
	}

	protected function getSearchableColumns() :array {
		return [];
	}

	protected function buildSqlWhereForDaysSearch( array $selectedDays, string $tableAbbr, string $column = 'created_at' ) :string {
		$splitDates = \array_map(
			function ( $selectedDay ) use ( $tableAbbr, $column ) {
				if ( $selectedDay === BuildDataForDays::ZERO_DATE_FORMAT ) {
					return sprintf( "(`%s`.`%s`=0)", $tableAbbr, $column );
				}
				else {
					[ $year, $month, $day ] = \explode( '-', $selectedDay );
					$carbon = Services::Request()->carbon( true )->setDate( $year, $month, $day );
					return sprintf( "(`%s`.`%s`>%s AND `%s`.`%s`<%s)",
						$tableAbbr,
						$column,
						$carbon->startOfDay()->timestamp,
						$tableAbbr,
						$column,
						$carbon->endOfDay()->timestamp
					);
				}
			},
			$selectedDays
		);
		return sprintf( '(%s)', \implode( ' OR ', \array_filter( $splitDates ) ) );
	}
}
