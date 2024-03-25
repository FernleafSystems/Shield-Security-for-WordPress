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

	abstract protected function countTotalRecords() :int;

	abstract protected function countTotalRecordsFiltered() :int;

	abstract protected function buildTableRowsFromRawRecords( array $records ) :array;

	public function build() :array {
		return [
			'data'            => $this->loadForRecords(),
			'recordsTotal'    => $this->countTotalRecords(),
			'recordsFiltered' => $this->countTotalRecordsFiltered(),
			'searchPanes'     => $this->getSearchPanesData(),
		];
	}

	protected function getSearchPanesData() :array {
		return [];
	}

	public function loadForRecords() :array {
		if ( empty( $this->table_data[ 'search' ][ 'value' ] ) ) {
			return $this->loadRecordsWithDirectQuery();
		}
		else {
			return $this->loadRecordsWithSearch();
		}
	}

	protected function loadRecordsWithDirectQuery() :array {
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
		$search = (string)$this->table_data[ 'search' ][ 'value' ] ?? '';
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

	protected function getColumnContent_LinkedIP( string $ip, int $recordDeleteID = -1 ) :string {
		if ( !empty( $ip ) ) {
			try {
				$ipID = ( new IpID( $ip ) )->run();
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
			catch ( \Exception $e ) {
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
		$WPP = Services::WpUsers();
		$user = $WPP->getUserById( $uid );
		return empty( $user ) ? sprintf( 'Unavailable (ID:%s)', $uid ) :
			sprintf( '<a href="%s" target="_blank">%s</a>', $WPP->getAdminUrl_ProfileEdit( $user ), $user->user_login );
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