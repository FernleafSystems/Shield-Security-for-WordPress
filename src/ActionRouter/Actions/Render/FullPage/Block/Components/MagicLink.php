<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\ActiveWpUserConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock\AutoUnblockMagicLink;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Obfuscate;

class MagicLink extends Base {

	use ActiveWpUserConsumer;

	public const SLUG = 'render_magic_link';
	public const TEMPLATE = '/pages/block/magic_link.twig';

	protected function getRenderData() :array {
		$available = $this->hasActiveWPUser()
					 && ( new AutoUnblockMagicLink() )->isUnblockAvailable()
					 && apply_filters( self::con()->prefix( 'can_user_magic_link' ), true, $this->getActiveWPUser() );
		return [
			'flags'   => [
				'is_available' => $available,
			],
			'hrefs'   => [
				'ajaxurl' => Services::WpGeneral()->ajaxURL(),
			],
			'vars'    => [
				'email' => $available ? Obfuscate::Email( $this->getActiveWPUser()->user_email ) : '',
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