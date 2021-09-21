<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

class BaseLoadTableData {

	use ModConsumer;

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
}