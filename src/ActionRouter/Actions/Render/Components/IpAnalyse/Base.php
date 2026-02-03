<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Services\Services;

class Base extends Render\BaseRender {

	protected function getRequiredDataKeys() :array {
		return [ 'ip' ];
	}

	protected function getTimeAgo( int $ts ) :string {
		return Services::Request()
					   ->carbon()
					   ->setTimestamp( $ts )
					   ->diffForHumans();
	}

	/**
	 * @throws ActionException
	 */
	protected function getAnalyseIP() :string {
		if ( !Services::IP()->isValidIp( $this->action_data[ 'ip' ] ) ) {
			throw new ActionException( __( "A valid IP address wasn't provided.", 'wp-simple-firewall' ) );
		}
		return $this->action_data[ 'ip' ];
	}
}