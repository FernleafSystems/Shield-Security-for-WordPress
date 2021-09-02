<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\TrafficTable;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\GeoIp\Lookup;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\DB\LoadLogs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\DB\LogRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Traffic\Lib\Ops\ConvertLegacy;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseLoadTableData;
use FernleafSystems\Wordpress\Services\Services;

class LoadRawTableData extends BaseLoadTableData {

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

	public function loadForLogs() :array {
		( new ConvertLegacy() )
			->setMod( $this->getMod() )
			->run();

		$this->users = [ 0 => __( 'No', 'wp-simple-firewall' ) ];

		return array_values( array_map(
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
				$data[ 'page' ] = $this->log->ip;
				$data[ 'code' ] = $this->log->meta[ 'code' ];
				$data[ 'offense' ] = $this->log->meta[ 'offense' ] ? 'Offense' : 'Not Offense';
				$data[ 'rid' ] = $this->log->rid ?? __( 'Unknown', 'wp-simple-firewall' );
				$data[ 'path' ] = empty( $this->log->meta[ 'path' ] ) ? '-'
					: explode( '?', $this->log->meta[ 'path' ], 2 )[ 0 ];

				$ipGeo = $this->getCountryIP( $this->log->ip );
				$data[ 'country' ] = empty( $ipGeo->getCountryCode() ) ?
					__( 'Unknown', 'wp-simple-firewall' ) : $ipGeo->getCountryName();

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
			$this->getLogRecords()
		) );
	}

	/**
	 * @return LogRecord[]
	 */
	private function getLogRecords() :array {
		return ( new LoadLogs() )
			->setMod( $this->getCon()->getModule_Traffic() )
			->run();
	}

	private function getColumnContent_Details() :string {
		$countryGeo = $this->getCountryIP( $this->log->ip );
		$countryISO = $countryGeo->getCountryCode();
		if ( empty( $countryISO ) ) {
			$country = __( 'Unknown', 'wp-simple-firewall' );
		}
		else {
			$country = sprintf(
				'<img class="icon-flag" src="%s" alt="%s" width="24px"/> %s',
				sprintf( 'https://api.aptoweb.com/api/v1/country/flag/%s.svg', strtolower( $countryISO ) ),
				$countryISO,
				$countryGeo->getCountryName()
			);
		}

		return sprintf( '<div>%s</div>', implode( '</div><div>', [
			sprintf( '%s: %s', __( 'IP', 'wp-simple-firewall' ), $this->getIpAnalysisLink( $this->log->ip ) ),
			sprintf( '%s: %s', __( 'IP Status', 'wp-simple-firewall' ), $ipInfos[ $this->log->ip ] ?? 'n/a' ),
			sprintf( '%s: %s', __( 'Logged-In', 'wp-simple-firewall' ), $this->users[ $this->log->meta[ 'uid' ] ] ),
			sprintf( '%s: %s', __( 'Location', 'wp-simple-firewall' ), $country ),
			esc_html( esc_js( sprintf( '%s - %s', __( 'User Agent', 'wp-simple-firewall' ), $this->log->meta[ 'ua' ] ) ) ),
		] ) );
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
				sprintf( '<span class="badge badge-%s">%s</span>', $codeType, $this->log->meta[ 'code' ] ) ),
			sprintf( '%s: %s', __( 'Offense', 'wp-simple-firewall' ),
				sprintf(
					'<span class="badge badge-%s">%s</span>',
					@$this->log->meta[ 'offense' ] ? 'danger' : 'info',
					@$this->log->meta[ 'offense' ] ? __( 'Yes', 'wp-simple-firewall' ) : __( 'No', 'wp-simple-firewall' )
				)
			),
		] ) );
	}

	private function getColumnContent_Page() :string {
		list( $preQuery, $query ) = explode( '?', $this->log->meta[ 'path' ].'?', 2 );
		return strtoupper( $this->log->meta[ 'verb' ] ).': <code>'.$preQuery
			   .( empty( $query ) ? '' : '?<br/>'.$query ).'</code>';
	}

	private function getCountryIP( string $ip ) {
		if ( empty( $this->geoLookup ) ) {
			$this->geoLookup = ( new Lookup() )
				->setDbHandler( $this->getCon()
									 ->getModule_Plugin()
									 ->getDbHandler_GeoIp()
				);
		}
		return $this->geoLookup
			->setIP( $this->log->ip )
			->lookupIp();
	}
}