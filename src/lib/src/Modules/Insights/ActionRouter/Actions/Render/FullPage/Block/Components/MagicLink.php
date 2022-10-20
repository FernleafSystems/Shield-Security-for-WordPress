<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\FullPage\Block\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\IpAutoUnblockShieldUserLinkRequest;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Options;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Obfuscate;

class MagicLink extends Base {

	const SLUG = 'render_magic_link';
	const TEMPLATE = '/pages/block/magic_link.twig';

	protected function getRenderData() :array {
		$con = $this->getCon();
		/** @var Options $opts */
		$opts = $this->primary_mod->getOptions();
		$user = Services::WpUsers()->getCurrentWpUser();
		$available = $user instanceof \WP_User;
		return [
			'flags'   => [
				'is_available' => $available && $opts->isEnabledMagicEmailLinkRecover()
								  && apply_filters( $con->prefix( 'can_user_magic_link' ), true, $user ),
			],
			'hrefs'   => [
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
			],
			'vars'    => [
				'email'         => $available ? Obfuscate::Email( $user->user_email ) : '',
				'nonce_unblock' => ActionData::BuildJson( IpAutoUnblockShieldUserLinkRequest::SLUG, true, [
					'ip' => $con->this_req->ip
				] ),
			],
			'strings' => [
				'you_may'        => __( 'You can automatically unblock your IP address by clicking the link below.', 'wp-simple-firewall' ),
				'this_will_send' => __( 'Clicking the button will send you an email letting you unblock your IP address.', 'wp-simple-firewall' ),
				'assumes_email'  => __( 'This assumes that your WordPress site has been properly configured to send email - many are not.', 'wp-simple-firewall' ),
				'dont_receive'   => __( "If you don't receive the email, check your spam and contact your site admin.", 'wp-simple-firewall' ),
				'limit_60'       => __( "You may only use this link once every 60 minutes. If you're repeatedly blocked, ask your site admin to review the Activity Log to determine the cause.", 'wp-simple-firewall' ),
				'same_browser'   => __( "When you click the link from your email, it must open up in this web browser.", 'wp-simple-firewall' ),
				'click_to_send'  => __( 'Send Auto-Unblock Link To My Email', 'wp-simple-firewall' )
			],
		];
	}
}