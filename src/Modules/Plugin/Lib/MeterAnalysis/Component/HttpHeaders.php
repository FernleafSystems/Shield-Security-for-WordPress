<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

class HttpHeaders extends Base {

	use Traits\OptConfigBased;

	public const SLUG = 'http_headers';
	public const WEIGHT = 1;

	protected function testIfProtected() :bool {
		$optsCon = self::con()->opts;
		return \in_array( $optsCon->optGet( 'x_frame' ), [ 'on_sameorigin', 'on_deny' ] )
			   && $optsCon->optIs( 'x_xss_protect', 'Y' )
			   && $optsCon->optIs( 'x_content_type', 'Y' )
			   && !$optsCon->optIs( 'x_referrer_policy', 'disabled' );
	}

	protected function getOptConfigKey() :string {
		return 'x_frame';
	}

	public function title() :string {
		return __( 'HTTP Headers', 'wp-simple-firewall' );
	}

	public function descProtected() :string {
		return __( 'Important HTTP Headers are helping to protect visitors.', 'wp-simple-firewall' );
	}

	public function descUnprotected() :string {
		return __( "Important HTTP Headers aren't being used to help protect visitors.", 'wp-simple-firewall' );
	}
}