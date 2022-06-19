<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

/**
 * @property array $table_data
 */
abstract class BaseBuildTableData extends DynPropertiesClass {

	use ModConsumer;

	public function build() :array {
		return [
			'data'            => $this->loadForLogs(),
			'recordsTotal'    => $this->countTotalRecords(),
			'recordsFiltered' => $this->countTotalRecordsFiltered(),
			'searchPanes'     => $this->getSearchPanesData(),
		];
	}

	protected function getSearchPanesData() :array {
		return [];
	}

	public function loadForLogs() :array {
		if ( empty( $this->table_data[ 'search' ][ 'value' ] ) ) {
			return $this->loadLogsWithDirectQuery();
		}
		else {
			return $this->loadLogsWithSearch();
		}
	}

	protected function loadLogsWithDirectQuery() :array {
		return $this->buildTableRowsFromRawLogs(
			$this->getRecords( $this->buildWheresFromSearchParams(), (int)$this->table_data[ 'start' ], (int)$this->table_data[ 'length' ] )
		);
	}

	protected function loadLogsWithSearch() :array {
		$start = (int)$this->table_data[ 'start' ];
		$length = (int)$this->table_data[ 'length' ];
		$search = (string)$this->table_data[ 'search' ][ 'value' ] ?? '';
		$wheres = $this->buildWheresFromSearchParams();

		$searchableColumns = array_flip( $this->getSearchableColumns() );

		// We keep building logs and filtering by the search string until we have
		// enough records built to return in order to satisfy the start + length.
		$results = [];
		$page = 0;
		$pageLength = 100;
		do {
			$interimResults = $this->buildTableRowsFromRawLogs(
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
					$searchable = array_intersect_key( $result, $searchableColumns );
					foreach ( $searchable as $value ) {
						$value = wp_strip_all_tags( $value );
						if ( !is_string( $search ) ) {
							error_log( var_export( $search, true ) );
							continue;
						}
						if ( stripos( $value, $search ) !== false ) {
							$results[] = $result;
							break;
						}
					}
				}
			}

			$page++;
		} while ( count( $results ) < $start + $length );

		$results = array_values( $results );
		if ( count( $results ) < $start ) {
			$results = [];
		}
		else {
			$results = array_splice( $results, $start, $length );
		}

		return array_values( $results );
	}

	protected function buildWheresFromSearchParams() :array {
		return [];
	}

	protected function getOrderBy() :string {
		$orderBy = '';
		if ( !empty( $this->table_data[ 'order' ] ) ) {
			$col = $this->table_data[ 'order' ][ 0 ][ 'column' ];
			$sortCol = $this->table_data[ 'columns' ][ $col ][ 'data' ];
			$orderBy = is_array( $sortCol ) ? $sortCol[ 'sort' ] : $sortCol;
		}
		return $orderBy;
	}

	protected function getOrderDirection() :string {
		$dir = 'DESC';
		if ( !empty( $this->table_data[ 'order' ] ) ) {
			$dir = strtoupper( $this->table_data[ 'order' ][ 0 ][ 'dir' ] );
			if ( !in_array( $dir, [ 'ASC', 'DESC' ] ) ) {
				$dir = 'DESC';
			}
		}
		return $dir;
	}

	protected function getRecords( array $wheres = [], int $offset = 0, int $limit = 0 ) :array {
		return [];
	}

	abstract protected function countTotalRecords() :int;

	abstract protected function countTotalRecordsFiltered() :int;

	abstract protected function buildTableRowsFromRawLogs( array $records ) :array;

	protected function getColumnContent_Date( int $ts ) :string {
		return sprintf( '%s<br /><small>%s</small>',
			Services::Request()
					->carbon( true )
					->setTimestamp( $ts )
					->diffForHumans(),
			Services::WpGeneral()->getTimeStringForDisplay( $ts )
		);
	}

	protected function getColumnContent_LinkedIP( string $ip ) :string {
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
			$content = sprintf( '<h6>%s%s</h6>', $this->getIpAnalysisLink( $ip ),
				empty( $id ) ? '' : sprintf( '<br/><small>%s</small>', esc_html( $id ) ) );
		}
		else {
			$content = 'No IP';
		}
		return $content;
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
		elseif ( Services::IP()->isValidIp( $ip ) ) {
			$content = sprintf(
				'<a href="%s" target="_blank" title="%s" class="%s" data-ip="%s">%s</a>',
				$this->getCon()->getModule_Insights()->getUrl_IpAnalysis( $ip ),
				__( 'IP Analysis', 'wp-simple-firewall' ),
				'modal_ip_analysis',
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
}