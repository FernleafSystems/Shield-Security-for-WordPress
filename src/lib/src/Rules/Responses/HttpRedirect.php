<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Services\Services;

class HttpRedirect extends Base {

	public function execResponse() :bool {
		\header( 'Cache-Control: no-store, no-cache' );
		wp_redirect( $this->params[ 'redirect_url' ], $this->params[ 'status_code' ] );
		return true;
	}

	public function getParamsDef() :array {
		$statusCodes = [
			'301' => __( 'Permanent Redirect (301)', 'wp-simple-firewall' ),
			'302' => __( 'Temporary Redirect (302)', 'wp-simple-firewall' ),
		];
		return [
			'redirect_url' => [
				'type'  => EnumParameters::TYPE_URL,
				'label' => __( 'HTTP Redirect Location', 'wp-simple-firewall' ),
			],
			'status_code'  => [
				'type'        => EnumParameters::TYPE_ENUM,
				'type_enum'   => \array_keys( $statusCodes ),
				'enum_labels' => $statusCodes,
				'default'     => '302',
				'label'       => __( 'HTTP Redirect Code', 'wp-simple-firewall' ),
			],
		];
	}
}