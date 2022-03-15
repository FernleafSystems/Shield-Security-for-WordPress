<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string $order_by
 * @property string $order_dir
 * @property array  $table_data
 */
abstract class BaseBuildTableData extends DynPropertiesClass {

	use ModConsumer;

	public function build() :array {
		return [
			'data'            => $this->loadForLogs(),
			'recordsTotal'    => $this->countTotalRecords(),
			'recordsFiltered' => $this->countTotalRecordsFiltered(),
		];
	}

	public function loadForLogs() :array {
		$start = (int)$this->table_data[ 'start' ];
		$length = (int)$this->table_data[ 'length' ];
		$search = (string)$this->table_data[ 'search' ][ 'value' ] ?? '';
		$wheres = $this->buildWheresFromSearchPanes();

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

	protected function buildWheresFromSearchPanes() :array {
		return [];
	}

	protected function getOrderDirection() :string {
		if ( !isset( $this->order_dir ) ) {
			$dir = 'DESC';
			if ( !empty( $this->table_data[ 'order' ] ) ) {
				$col = $this->table_data[ 'order' ][ 0 ][ 'column' ];
				$sortCol = $this->table_data[ 'columns' ][ $col ][ 'data' ];
				$this->order_by = is_array( $sortCol ) ? $sortCol[ 'sort' ] : $sortCol;
				$dir = strtoupper( $this->table_data[ 'order' ][ 0 ][ 'dir' ] );
				if ( !in_array( $dir, [ 'ASC', 'DESC' ] ) ) {
					$dir = 'DESC';
				}
			}
			$this->order_dir = $dir;
		}
		return $this->order_dir;
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
			$content = sprintf( '<a href="%s" target="_blank" title="%s">%s</a>',
				$this->getCon()->getModule_Insights()->getUrl_IpAnalysis( $ip ),
				__( 'IP Analysis', 'wp-simple-firewall' ),
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