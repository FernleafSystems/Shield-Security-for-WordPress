<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Tools;

use FernleafSystems\Wordpress\Plugin\Shield\ShieldNetApi\Common;

class NewsletterSubscribe extends Common\BaseShieldNetApiV2 {

	public const API_ACTION = 'newsletter/subscribe';

	public function run( array $params ) :bool {
		$this->request_method = 'post';
		$this->shield_net_params_required = true;
		$this->params_body = \array_intersect_key( $params, \array_flip( [ 'email', 'first_name', 'last_name' ] ) );
		$raw = $this->sendReq();
		return \is_array( $raw ) && empty( $raw[ 'error' ] ) && !empty( $raw[ 'success' ] );
	}
}