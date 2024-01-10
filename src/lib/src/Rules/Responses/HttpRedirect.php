<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Responses;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Services\Services;

class HttpRedirect extends Base {

	public function execResponse() :void {
		if ( !$this->canRedirect() ) {
			throw new \Exception( 'Redirection skipped to prevent infinite redirects.' );
		}
		\header( 'Cache-Control: no-store, no-cache' );
		wp_redirect( $this->p->redirect_url, $this->p->status_code );
	}

	/**
	 * Prevents any sort of infinite redirects
	 */
	private function canRedirect() :bool {
		$to = $this->p->redirect_url;
		return \parse_url( $to, \PHP_URL_HOST ) !== \parse_url( Services::WpGeneral()->getWpUrl() )
			   || \trim( \parse_url( $to, \PHP_URL_PATH ), '/' ) !== \trim( $this->req->path, '/' );
	}

	public function getParamsDef() :array {
		$statusCodes = [
			'301' => __( 'Permanent Redirect (301)', 'wp-simple-firewall' ),
			'302' => __( 'Temporary Redirect (302)', 'wp-simple-firewall' ),
		];
		return [
			'redirect_url' => [
				'type'  => EnumParameters::TYPE_URL,
				'label' => __( 'Redirect Location', 'wp-simple-firewall' ),
			],
			'status_code'  => [
				'type'        => EnumParameters::TYPE_ENUM,
				'type_enum'   => \array_keys( $statusCodes ),
				'enum_labels' => $statusCodes,
				'default'     => '302',
				'label'       => __( 'Redirect Status', 'wp-simple-firewall' ),
			],
		];
	}
}