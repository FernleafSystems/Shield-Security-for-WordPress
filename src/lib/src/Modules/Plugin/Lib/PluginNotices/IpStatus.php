<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;

class IpStatus extends Base {

	public function check() :?array {
		$con = self::con();
		$ip = $con->this_req->ip;

		$issue = null;

		$ipStatus = new IpRuleStatus( $ip );
		if ( $ipStatus->isBypass() ) {
			$issue = [
				'id'        => 'self_ip_bypass',
				'type'      => 'warning',
				'text'      => [
					sprintf( __( 'Something not working? No security features apply to you because your IP (%s) is whitelisted.', 'wp-simple-firewall' ),
						sprintf( '<a href="%s" class="offcanvas_ip_analysis" data-ip="%s">%s</a>', $con->plugin_urls->ipAnalysis( $ip ), $ip, $ip ) )
				],
				'locations' => [
					'shield_admin_top_page',
				]
			];
		}
		elseif ( $ipStatus->isBlocked() ) {
			$issue = [
				'id'        => 'self_ip_blocked',
				'type'      => 'danger',
				'text'      => [
					sprintf( __( 'It looks like your IP (%s) is currently blocked.', 'wp-simple-firewall' ),
						sprintf( '<a href="%s" class="offcanvas_ip_analysis" data-ip="%s">%s</a>', $con->plugin_urls->ipAnalysis( $ip ), $ip, $ip ) )
				],
				'locations' => [
					'shield_admin_top_page',
				]
			];
		}

		return $issue;
	}
}
