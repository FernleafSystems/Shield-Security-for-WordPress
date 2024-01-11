<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;

class DisableRestApiRequest extends Base {

	public function execResponse() :void {
		add_filter( 'rest_authentication_errors', [ $this, 'disableAnonymousRestApi' ], $this->p->priority );
	}

	/**
	 * @param \WP_Error|true|null $mStatus
	 */
	public function disableAnonymousRestApi( $mStatus ) {
		return ( $mStatus === true || is_wp_error( $mStatus ) ) ?
			$mStatus :
			new \WP_Error(
				'shield_rule_disable_rest_api',
				$this->p->message,
				[ 'status' => $this->p->status_code ]
			);
	}

	public function getParamsDef() :array {
		return [
			'message'     => [
				'type'  => EnumParameters::TYPE_STRING,
				'label' => __( 'Rest API Error Message', 'wp-simple-firewall' ),
			],
			'status_code' => [
				'type'    => EnumParameters::TYPE_INT,
				'default' => 401,
				'label'   => __( 'Rest API Error Code', 'wp-simple-firewall' ),
			],
			'priority'    => [
				'type'    => EnumParameters::TYPE_INT,
				'default' => 99,
				'label'   => __( 'Hook Priority', 'wp-simple-firewall' ),
			],
		];
	}
}