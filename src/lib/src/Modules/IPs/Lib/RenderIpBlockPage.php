<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Obfuscate;

class RenderIpBlockPage extends ExecOnceModConsumer {

	protected function run() {
		$con = $this->getCon();
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		/** @var IPs\Options $opts */
		$opts = $this->getOptions();

		$ip = Services::IP()->getRequestIp();
		$timeRemaining = max( floor( $opts->getAutoExpireTime()/60 ), 0 );

		$user = Services::WpUsers()->getCurrentWpUser();
		$canUauBot = $opts->isEnabledAutoVisitorRecover() && !empty( $ip ) && $opts->getCanIpRequestAutoUnblock( $ip );
		$canUauMagic = $opts->isEnabledMagicEmailLinkRecover() &&
					   $user instanceof \WP_User
					   && $opts->getCanRequestAutoUnblockEmailLink( $user );
		$canAutoRecover = $canUauBot || $canUauMagic;

		if ( !empty( $con->getLabels()[ 'PluginURI' ] ) ) {
			$homeURL = $con->getLabels()[ 'PluginURI' ];
		}
		else {
			$homeURL = $con->cfg->meta[ 'url_repo_home' ];
		}

		$data = [
			'strings' => [
				'title'   => sprintf( __( "You've been blocked by the %s plugin", 'wp-simple-firewall' ),
					sprintf( '<a href="%s" target="_blank">%s</a>',
						$homeURL,
						$con->getHumanName()
					)
				),
				'lines'   => [
					sprintf( __( 'Time remaining on black list: %s', 'wp-simple-firewall' ),
						sprintf( _n( '%s minute', '%s minutes', $timeRemaining, 'wp-simple-firewall' ), $timeRemaining )
					),
					sprintf( __( 'You tripped the security plugin defenses a total of %s times making you a suspect.', 'wp-simple-firewall' ), $opts->getOffenseLimit() ),
					__( 'If you believe this to be in error, please contact the site owner and quote your IP address below.', 'wp-simple-firewall' ),
				],
				'your_ip' => 'Your IP address',
				'unblock' => [
					'title'   => __( 'Auto-Unblock Your IP', 'wp-simple-firewall' ),
					'you_can' => __( 'You can automatically unblock your IP address by clicking the button below.', 'wp-simple-firewall' ),
					'button'  => __( 'Unblock My IP Address', 'wp-simple-firewall' ),
				],
			],
			'content' => [
				'email_unblock' => $this->renderEmailMagicLinkContent()
			],
			'hrefs'   => [
				'home' => Services::WpGeneral()->getHomeUrl( '/' )
			],
			'vars'    => [
				'nonce' => $mod->getNonceActionData( 'uau' ),
				'ip'    => $ip,
			],
			'flags'   => [
				'is_autorecover'    => $canAutoRecover,
				'is_uaug_permitted' => $canUauBot,
				'is_uaum_permitted' => $canUauMagic,
			],
		];

		if ( $con->isPremiumActive() ) {
			$data = apply_filters( 'shield/render_data_block_page', $data );
		}

		Services::WpGeneral()->wpDie(
			$mod->renderTemplate( '/pages/block/blocklist_die.twig', $data, true )
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
			 && $opts->getCanRequestAutoUnblockEmailLink( $user ) ) {

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