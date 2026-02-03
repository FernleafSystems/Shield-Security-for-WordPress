<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rest\v1\Process;

use FernleafSystems\Wordpress\Plugin\Core\Rest\Exceptions\ApiException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\AddRule;

class IpRulesAddIpRule extends IpRulesBase {

	protected function process() :array {
		$list = $this->getWpRestRequest()->get_param( 'list' );
		$label = $this->getWpRestRequest()->get_param( 'label' );

		$adder = ( new AddRule() )->setIP( $this->ip() );

		try {
			if ( \in_array( $list, [ 'block', 'black' ] ) ) {
				$adder->toManualBlacklist( $label );
			}
			elseif ( \in_array( $list, [ 'bypass', 'white' ] ) ) {
				$adder->toManualWhitelist( $label );
			}
		}
		catch ( \Exception $e ) {
			throw new ApiException( 'There was an error adding IP address to list.' );
		}

		return [
			'ip' => $this->getIpData( $this->ip(), $list )
		];
	}
}