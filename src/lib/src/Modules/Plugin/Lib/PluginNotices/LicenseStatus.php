<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

class LicenseStatus extends Base {

	public function check() :?array {
		$con = self::con();
		$issue = null;
		if ( $con->isPremiumActive() && !$con->comps->api_token->hasToken() ) {
			$issue = [
				'id'        => 'api_token_missing',
				'type'      => 'warning',
				'text'      => [
					\implode( ' ', [
						sprintf( __( '%s API Token Missing.', 'wp-simple-firewall' ), 'ShieldPRO' ),
						__( "Please contact support if this message persists for over 24hrs.", 'wp-simple-firewall' ),
					] ),
				],
				'locations' => [
					'shield_admin_top_page',
				]
			];
		}

		return $issue;
	}
}
