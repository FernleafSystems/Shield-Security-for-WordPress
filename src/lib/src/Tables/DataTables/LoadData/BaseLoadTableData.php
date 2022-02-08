<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property int    $start
 * @property int    $length
 * @property string $search
 */
abstract class BaseLoadTableData extends DynPropertiesClass {

	use ModConsumer;

	public function loadForLogs() :array {
		$searchableColumns = array_flip( $this->getSearchableColumns() );
		error_log( var_export( $this->start, true ) );
		if ( empty( $this->search ) || empty( $searchableColumns ) ) {
			$results = $this->buildTableRowsFromRawLogs(
				$this->getRecords( $this->start, $this->length )
			);
		}
		else {
			// We keep building logs and filtering by the search string until we have
			// enough records built to return in order to satisfy the start + length.
			$results = [];
			$page = 0;
			$pageLength = 100;
			do {
				$interimResults = $this->buildTableRowsFromRawLogs(
					$this->getRecords( $page*$pageLength )
				);
				// no more table results to process, so go with what we have.
				if ( empty( $interimResults ) ) {
					break;
				}

				foreach ( $interimResults as $result ) {
					$searchable = array_intersect_key( $result, $searchableColumns );
					foreach ( $searchable as $value ) {
						$value = wp_strip_all_tags( $value );
						if ( stripos( $value, $this->search ) !== false ) {
							$results[] = $result;
							break;
						}
					}
				}

				$page++;
			} while ( count( $results ) < $this->start + $this->length );

			$results = array_values( $results );
			if ( count( $results ) < $this->start ) {
				$results = [];
			}
			else {
				$results = array_splice( $results, $this->start, $this->length );
			}
		}
		return array_values( $results );
	}

	protected function getRecords( int $offset = 0, int $limit = 0 ) :array {
		return [];
	}

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