<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules\IpRuleStatus;
use FernleafSystems\Wordpress\Services\Services;

class IpStatus extends Base {

	public const ID_NOT_PUBLIC = 'self_ip_not_public';
	private const NOT_PUBLIC_DISMISSED_USER_META = 'self_ip_not_public_dismissed_at';

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
					/* translators: %s: IP address, shown as a link */
					sprintf( __( 'Something not working? No security features apply to you because your IP (%s) is whitelisted.', 'wp-simple-firewall' ),
						sprintf( '<a href="%s" class="offcanvas_ip_analysis text-reset text-decoration-underline" data-ip="%s">%s</a>', $con->plugin_urls->ipAnalysis( $ip ), $ip, $ip ) )
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
					/* translators: %s: IP address, shown as a link */
					sprintf( __( 'It looks like your IP (%s) is currently blocked.', 'wp-simple-firewall' ),
						sprintf( '<a href="%s" class="offcanvas_ip_analysis" data-ip="%s">%s</a>', $con->plugin_urls->ipAnalysis( $ip ), $ip, $ip ) )
				],
				'locations' => [
					'shield_admin_top_page',
				]
			];
		}
		elseif ( !empty( $ip ) && !$con->this_req->ip_is_public && !$this->isNotPublicDismissed() ) {
			$issue = [
				'id'          => self::ID_NOT_PUBLIC,
				'type'        => 'warning',
				'text'        => [
					/* translators: %s: IP address, shown as a link */
					sprintf( __( 'Something not working? No security features apply to you because your IP (%s) is a private or local address.', 'wp-simple-firewall' ),
						sprintf( '<a href="%s" class="offcanvas_ip_analysis text-reset text-decoration-underline" data-ip="%s">%s</a>', $con->plugin_urls->ipAnalysis( $ip ), esc_attr( $ip ), esc_html( $ip ) ) )
				],
				'locations'   => [
					'shield_admin_top_page',
				],
				'can_dismiss' => true,
			];
		}

		return $issue;
	}

	public static function dismissNotPublicForCurrentUser() :bool {
		$meta = self::con()->user_metas->current();
		if ( $meta === null ) {
			return false;
		}

		$meta->{self::NOT_PUBLIC_DISMISSED_USER_META} = Services::Request()->ts();
		return true;
	}

	private function isNotPublicDismissed() :bool {
		$meta = self::con()->user_metas->current();
		return $meta !== null && (int)$meta->{self::NOT_PUBLIC_DISMISSED_USER_META} > 0;
	}
}
