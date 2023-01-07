<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\TrafficTable;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\IPs\IPGeoVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs\LoadRequestLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs\LogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\ReqLogs\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\Lib\GeoIP\Lookup;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\ForTraffic;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildTableData;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Net\IpID;

class BuildTrafficTableData extends BaseBuildTableData {

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

	protected function loadRecordsWithSearch() :array {
		return $this->loadRecordsWithDirectQuery();
	}

	protected function getSearchPanesData() :array {
		return ( new BuildSearchPanesData() )
			->setMod( $this->getCon()->getModule_Data() )
			->build();
	}

	/**
	 * @param LogRecord[] $records
	 */
	protected function buildTableRowsFromRawRecords( array $records ) :array {
		$this->users = [ 0 => __( 'No', 'wp-simple-firewall' ) ];

		return array_values( array_filter( array_map(
			function ( $log ) {
				$WPU = Services::WpUsers();

				$log->meta = array_merge(
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

				$geo = $this->getCountryIP( $this->log->ip );
				$data[ 'country' ] = empty( $geo->countryCode ) ?
					__( 'Unknown', 'wp-simple-firewall' ) : $geo->countryName;

				$userID = (int)$this->log->uid;
				if ( $userID > 0 && !isset( $users[ $userID ] ) ) {
					$user = $WPU->getUserById( $userID );
					$this->users[ $userID ] = empty( $user ) ? __( 'Unknown', 'wp-simple-firewall' ) :
						sprintf( '<a href="%s" target="_blank" title="Go To Profile">%s</a>',
							$WPU->getAdminUrl_ProfileEdit( $user ), $user->user_login );
				}

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

	/**
	 * The Wheres need to align with the structure of the Query called from getRecords()
	 */
	protected function buildWheresFromSearchParams() :array {
		$wheres = [];
		if ( !empty( $this->table_data[ 'searchPanes' ] ) ) {
			foreach ( array_filter( $this->table_data[ 'searchPanes' ] ) as $column => $selected ) {
				switch ( $column ) {
					case 'day':
						$wheres[] = $this->buildSqlWhereForDaysSearch( $selected, 'req' );
						break;
					case 'ip':
						$wheres[] = sprintf( "`ips`.ip=INET6_ATON('%s')", array_pop( $selected ) );
						break;
					case 'offense':
					case 'type':
					case 'code':
						$wheres[] = sprintf( "`req`.%s IN ('%s')", $column, implode( "','", $selected ) );
						break;
					default:
						break;
				}
			}
		}
		if ( !empty( $this->table_data[ 'search' ][ 'value' ] ) ) {
			$wheres[] = sprintf( "`req`.`path` LIKE '%%%s%%'", esc_sql( $this->table_data[ 'search' ][ 'value' ] ) );
		}
		return $wheres;
	}

	protected function getRecordsLoader() :LoadRequestLogs {
		return ( new LoadRequestLogs() )->setMod( $this->getCon()->getModule_Data() );
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
		$loader->order_by = $this->getOrderBy();
		$loader->order_dir = $this->getOrderDirection();
		return $loader->select();
	}

	private function getColumnContent_Details() :string {
		$geo = $this->getCountryIP( $this->log->ip );
		if ( empty( $geo->countryCode ) ) {
			$country = '';//__( 'Unknown', 'wp-simple-firewall' );
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
			try {
				$identity = ( new IpID( $this->log->ip ) )->run();
			}
			catch ( \Exception $e ) {
				$identity = IpID::UNKNOWN;
			}

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
			$components[] = sprintf( '%s: %s', __( 'Logged-In', 'wp-simple-firewall' ), $this->users[ $this->log->uid ] );
			if ( !empty( $country ) ) {
				$components[] = sprintf( '%s: %s', __( 'Location', 'wp-simple-firewall' ), $country );
			}
			if ( !empty( $this->log->meta[ 'ua' ] ) ) {
				$components[] = esc_html( esc_js( sprintf( '%s - %s', __( 'User Agent', 'wp-simple-firewall' ), $this->log->meta[ 'ua' ] ) ) );
			}

			$content = sprintf( '<div>%s</div>', implode( '</div><div>', $components ) );
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

		return sprintf( '<div>%s</div>', implode( '</div><div>', [
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

		$content = sprintf( '<span class="badge bg-secondary me-1">%s</span>', Handler::GetTypeName( $this->log->type ) );
		if ( $this->isWpCli() ) {
			$content .= sprintf( '<code>:> %s</code>', esc_html( $this->log->path.' '.$query ) );
		}
		else {
			$content .= strtoupper( $this->log->verb ).': <code>'.$this->log->path
						.( empty( $query ) ? '' : '?<br/>'.ltrim( $query, '?' ) ).'</code>';
		}
		return $content;
	}

	private function getIpInfo( string $ip ) {

		if ( !isset( $this->ipInfo[ $ip ] ) ) {

			if ( empty( $ip ) ) {
				$this->ipInfo[ '' ] = '';
			}
			else {
				$badgeTemplate = '<span class="badge bg-%s">%s</span>';
				$ipRuleStatus = ( new IpRuleStatus( $ip ) )->setMod( $this->getCon()->getModule_IPs() );
				if ( $ipRuleStatus->isBypass() ) {
					$status = sprintf( $badgeTemplate, 'success', __( 'Bypass', 'wp-simple-firewall' ) );
				}
				elseif ( $ipRuleStatus->isBlocked() ) {
					$status = sprintf( $badgeTemplate, 'danger', __( 'Blocked', 'wp-simple-firewall' ) );
				}
				elseif ( $ipRuleStatus->isAutoBlacklisted() ) {
					$offenses = $ipRuleStatus->getOffenses();
					$status = sprintf( $badgeTemplate,
						'warning',
						sprintf( _n( '%s offense', '%s offenses', $offenses, 'wp-simple-firewall' ), $offenses )
					);
				}
				else {
					$status = '';
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
			->lookup();
	}

	private function isWpCli() :bool {
		return $this->log->type === Handler::TYPE_WPCLI;
	}
}