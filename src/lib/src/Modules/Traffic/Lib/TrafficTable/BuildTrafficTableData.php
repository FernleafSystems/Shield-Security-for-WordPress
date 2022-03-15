<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\TrafficTable;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPGeoVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\Lib\GeoIP\Lookup;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops\LookupIpOnList;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs\LoadLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs\LogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Traffic\ForTraffic;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildTableData;
use FernleafSystems\Wordpress\Services\Services;

class BuildTrafficTableData extends BaseBuildTableData {

	use ModConsumer;

	/**
	 * @var LogRecord
	 */
	private $log;

	/**
	 * @var Lookup
	 */
	private $geoLookup;

	private $users = [];

	private $ipInfo = [];

	protected function getSearchPanesData() :array {
		return ( new BuildSearchPanesData() )
			->setMod( $this->getCon()->getModule_Data() )
			->build();
	}

	/**
	 * @param LogRecord[] $records
	 */
	protected function buildTableRowsFromRawLogs( array $records ) :array {
		$this->users = [ 0 => __( 'No', 'wp-simple-firewall' ) ];

		return array_values( array_filter( array_map(
			function ( $log ) {
				$WPU = Services::WpUsers();

				$log->meta = array_merge(
					[
						'path'    => '',
						'code'    => '200',
						'ua'      => 'Unknown',
						'verb'    => 'Unknown',
						'offense' => false,
						'uid'     => 0
					],
					$log->meta
				);

				$this->log = $log;

				$data = $log->getRawData();

				$data[ 'ip' ] = $this->log->ip;
				$data[ 'code' ] = $this->log->meta[ 'code' ];
				$data[ 'offense' ] = $this->log->meta[ 'offense' ] ? 'Offense' : 'Not Offense';
				$data[ 'rid' ] = $this->log->rid ?? __( 'Unknown', 'wp-simple-firewall' );
				$data[ 'path' ] = empty( $this->log->meta[ 'path' ] ) ? '-'
					: explode( '?', $this->log->meta[ 'path' ], 2 )[ 0 ];

				$geo = $this->getCountryIP( $this->log->ip );
				$data[ 'country' ] = empty( $geo->countryCode ) ?
					__( 'Unknown', 'wp-simple-firewall' ) : $geo->countryName;

				$userID = $this->log->meta[ 'uid' ] ?? 0;
				if ( $userID > 0 ) {
					if ( !isset( $users[ $userID ] ) ) {
						$user = $WPU->getUserById( $userID );
						$this->users[ $userID ] = empty( $user ) ? __( 'Unknown', 'wp-simple-firewall' ) :
							sprintf( '<a href="%s" target="_blank" title="Go To Profile">%s</a>',
								$WPU->getAdminUrl_ProfileEdit( $user ), $user->user_login );
					}
				}

				$data[ 'page' ] = $this->getColumnContent_Page();
				$data[ 'details' ] = $this->getColumnContent_Details();
				$data[ 'response' ] = $this->getColumnContent_Response();
				$data[ 'created_since' ] = $this->getColumnContent_Date( $this->log->created_at );
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
		$loader->wheres = $this->buildWheresFromSearchPanes();
		return $loader->countAll();
	}

	/**
	 * The Wheres need to align with the structure of the Query called from getRecords()
	 */
	protected function buildWheresFromSearchPanes() :array {
		$wheres = [];
		if ( !empty( $this->table_data[ 'searchPanes' ] ) ) {
			foreach ( array_filter( $this->table_data[ 'searchPanes' ] ) as $column => $selected ) {
				switch ( $column ) {
					case 'ip':
						$wheres[] = sprintf( "ips.ip=INET6_ATON('%s')", array_pop( $selected ) );
						break;
					default:
						break;
				}
			}
		}
		return $wheres;
	}

	protected function getRecordsLoader() :LoadLogs {
		return ( new LoadLogs() )->setMod( $this->getCon()->getModule_Data() );
	}

	protected function getSearchableColumns() :array {
		// Use the DataTables definition builder to locate searchable columns
		return array_filter( array_map(
			function ( $column ) {
				return ( $column[ 'searchable' ] ?? false ) ? $column[ 'data' ] : '';
			},
			( new ForTraffic() )
				->setMod( $this->getMod() )
				->buildRaw()[ 'columns' ]
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
		$loader->order_dir = $this->getOrderDirection();
		return $loader->run();
	}

	private function getColumnContent_Details() :string {
		$geo = $this->getCountryIP( $this->log->ip );
		if ( empty( $geo->countryCode ) ) {
			$country = __( 'Unknown', 'wp-simple-firewall' );
		}
		else {
			$country = sprintf(
				'<img class="icon-flag" src="%s" alt="%s" width="24px"/> %s',
				sprintf( 'https://api.aptoweb.com/api/v1/country/flag/%s.svg', strtolower( $geo->countryCode ) ),
				$geo->countryCode,
				$geo->countryName
			);
		}

		if ( $this->isWpCli() ) {
			$content = 'WP-CLI';
		}
		else {
			$content = sprintf( '<div>%s</div>', implode( '</div><div>', [
				sprintf( '%s: %s', __( 'IP', 'wp-simple-firewall' ), $this->getIpAnalysisLink( $this->log->ip ) ),
				sprintf( '%s: %s', __( 'IP Status', 'wp-simple-firewall' ), $this->getIpInfo( $this->log->ip ) ),
				sprintf( '%s: %s', __( 'Logged-In', 'wp-simple-firewall' ), $this->users[ $this->log->meta[ 'uid' ] ] ),
				sprintf( '%s: %s', __( 'Location', 'wp-simple-firewall' ), $country ),
				esc_html( esc_js( sprintf( '%s - %s', __( 'User Agent', 'wp-simple-firewall' ), $this->log->meta[ 'ua' ] ) ) ),
			] ) );
		}

		return $content;
	}

	private function getColumnContent_Response() :string {
		if ( $this->log->meta[ 'code' ] >= 400 ) {
			$codeType = 'danger';
		}
		elseif ( $this->log->meta[ 'code' ] >= 300 ) {
			$codeType = 'warning';
		}
		else {
			$codeType = 'success';
		}

		return sprintf( '<div>%s</div>', implode( '</div><div>', [
			sprintf( '%s: %s', __( 'Response', 'wp-simple-firewall' ),
				sprintf( '<span class="badge bg-%s">%s</span>', $codeType, $this->log->meta[ 'code' ] ) ),
			sprintf( '%s: %s', __( 'Offense', 'wp-simple-firewall' ),
				sprintf(
					'<span class="badge bg-%s">%s</span>',
					@$this->log->meta[ 'offense' ] ? 'danger' : 'info',
					@$this->log->meta[ 'offense' ] ? __( 'Yes', 'wp-simple-firewall' ) : __( 'No', 'wp-simple-firewall' )
				)
			),
		] ) );
	}

	private function getColumnContent_Page() :string {
		if ( $this->isWpCli() ) {
			$content = sprintf( '<code>:> %s</code>', esc_html( $this->log->meta[ 'path' ] ) );
		}
		else {
			list( $preQuery, $query ) = explode( '?', $this->log->meta[ 'path' ].'?', 2 );
			$content = strtoupper( $this->log->meta[ 'verb' ] ).': <code>'.$preQuery
					   .( empty( $query ) ? '' : '?<br/>'.rtrim( $query, '?' ) ).'</code>';
		}
		return $content;
	}

	private function getIpInfo( string $ip ) {

		if ( !isset( $this->ipInfo[ $ip ] ) ) {

			if ( empty( $ip ) ) {
				$this->ipInfo[ '' ] = 'n/a';
			}
			else {
				$badgeTemplate = '<span class="badge bg-%s">%s</span>';
				$status = __( 'No Record', 'wp-simple-firewall' );

				$record = ( new LookupIpOnList() )
					->setDbHandler( $this->getCon()->getModule_IPs()->getDbHandler_IPs() )
					->setIP( $ip )
					->lookup();

				if ( empty( $record ) ) {
					$status = __( 'No Record', 'wp-simple-firewall' );
				}
				elseif ( $record->blocked_at > 0 || $record->list === ModCon::LIST_MANUAL_BLACK ) {
					$status = sprintf( $badgeTemplate, 'danger', __( 'Blocked', 'wp-simple-firewall' ) );
				}
				elseif ( $record->list === ModCon::LIST_AUTO_BLACK ) {
					$status = sprintf( $badgeTemplate,
						'warning',
						sprintf( _n( '%s offense', '%s offenses', $record->transgressions, 'wp-simple-firewall' ), $record->transgressions )
					);
				}
				elseif ( $record->list === ModCon::LIST_MANUAL_WHITE ) {
					$status = sprintf( $badgeTemplate,
						'success',
						__( 'Bypass', 'wp-simple-firewall' )
					);
				}
				$this->ipInfo[ $ip ] = $status;
			}
		}

		return $this->ipInfo[ $ip ];
	}

	private function getCountryIP( string $ip ) :IPGeoVO {
		if ( empty( $this->geoLookup ) ) {
			$this->geoLookup = ( new Lookup() )->setCon( $this->getCon() );
		}
		return $this->geoLookup
			->setIP( $ip )
			->lookupIp();
	}

	private function isWpCli() :bool {
		return $this->log->meta[ 'ua' ] === 'wpcli';
	}
}