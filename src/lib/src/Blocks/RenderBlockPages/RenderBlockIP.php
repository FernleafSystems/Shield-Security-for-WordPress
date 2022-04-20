<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Blocks\RenderBlockPages;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Obfuscate;

class RenderBlockIP extends BaseBlockPage {

	protected function getPageSpecificData() :array {
		$con = $this->getCon();

		$autoUnblock = $this->renderAutoUnblock();
		$magicLink = $this->renderEmailMagicLinkContent();

		return [
			'content' => [
				'auto_unblock'  => $autoUnblock,
				'email_unblock' => $magicLink,
			],
			'flags'   => [
				'has_magiclink'   => !empty( $magicLink ),
				'has_autorecover' => !empty( $autoUnblock ),
			],
			'hrefs'   => [
				'how_to_unblock' => 'https://shsec.io/shieldhowtounblock',
			],
			'strings' => [
				'page_title' => sprintf( '%s | %s', __( 'Access Restricted', 'wp-simple-firewall' ), $con->getHumanName() ),
				'title'      => __( 'Access Restricted', 'wp-simple-firewall' ),
				'subtitle'   => __( 'Access from your IP address has been temporarily restricted.', 'wp-simple-firewall' ),
			],
		];
	}

	protected function getTemplateStub() :string {
		return 'block_page_ip';
	}

	private function renderAutoUnblock() :string {
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();

		$ip = Services::IP()->getRequestIp();
		$canAutoRecover = $opts->isEnabledAutoVisitorRecover()
						  && $opts->getCanIpRequestAutoUnblock( $ip );

		$content = '';

		if ( $canAutoRecover ) {
			$content = $mod->renderTemplate( '/pages/block/autorecover.twig', [
				'hrefs'   => [
					'home' => Services::WpGeneral()->getHomeUrl( '/' )
				],
				'vars'    => [
					'nonce' => $mod->getNonceActionData( 'uau-'.$ip ),
				],
				'strings' => [
					'title'   => __( 'Auto-Unblock Your IP', 'wp-simple-firewall' ),
					'you_can' => __( 'You can automatically unblock your IP address by clicking the button below.', 'wp-simple-firewall' ),
					'button'  => __( 'Unblock My IP Address', 'wp-simple-firewall' ),
				],
			] );
		}

		return $content;
	}

	protected function getRestrictionDetailsBlurb() :array {
		$blurb = array_merge(
			[
				__( "Too many requests from your IP address have triggered the site's automated defenses.", 'wp-simple-firewall' ),
			],
			parent::getRestrictionDetailsBlurb()
		);
		unset( $blurb[ 'activity_recorded' ] );
		return $blurb;
	}

	protected function getRestrictionDetailsPoints() :array {
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();
		return array_merge(
			[
				__( 'Restrictions Lifted', 'wp-simple-firewall' ) => Services::Request()
																			 ->carbon()
																			 ->addSeconds( $opts->getAutoExpireTime() )
																			 ->diffForHumans(),
			],
			parent::getRestrictionDetailsPoints()
		);
	}

	private function renderEmailMagicLinkContent() :string {
		$con = $this->getCon();
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();

		$content = '';

		$user = Services::WpUsers()->getCurrentWpUser();
		if ( $user instanceof \WP_User &&
			 $opts->isEnabledMagicEmailLinkRecover()
			 && $opts->getCanRequestAutoUnblockEmailLink( $user )
		) {

			if ( apply_filters( $con->prefix( 'can_user_magic_link' ), true ) ) {
				$content = $mod->renderTemplate( '/pages/block/magic_link.twig', [
					'flags'   => [
					],
					'hrefs'   => [
						'unblock' => add_query_arg(
							array_merge(
								$mod->getNonceActionData( 'uaum-init-'.substr( sha1( $user->user_login ), 0, 6 ) ),
								[
									'ip' => Services::IP()->getRequestIp()
								]
							),
							Services::WpGeneral()->getHomeUrl()
						)
					],
					'vars'    => [
						'email' => Obfuscate::Email( $user->user_email )
					],
					'strings' => [
						'you_may'        => __( 'You can automatically unblock your IP address by clicking the link below.', 'wp-simple-firewall' ),
						'this_will_send' => __( 'Clicking the button will send you an email letting you unblock your IP address.', 'wp-simple-firewall' ),
						'assumes_email'  => __( 'This assumes that your WordPress site has been properly configured to send email - many are not.', 'wp-simple-firewall' ),
						'dont_receive'   => __( "If you don't receive the email, check your spam and contact your site admin.", 'wp-simple-firewall' ),
						'limit_60'       => __( "You may only use this link once every 60 minutes. If you're repeatedly blocked, ask your site admin to review the audit trail to determine the cause.", 'wp-simple-firewall' ),
						'same_browser'   => __( "When you click the link from your email, it must open up in this web browser.", 'wp-simple-firewall' ),
						'click_to_send'  => __( 'Send Auto-Unblock Link To My Email', 'wp-simple-firewall' )
					],
				] );
			}
		}

		return $content;
	}
}