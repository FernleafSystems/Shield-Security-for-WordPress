<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\Reporting;
use FernleafSystems\Wordpress\Services\Services;

class Footer extends Base {

	public const SLUG = 'render_email_footer';
	public const TEMPLATE = '/email/footer.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$WP = Services::WpGeneral();

		$goPro = [
			__( 'Go PRO For The Equivalent Of 1 Cappuccino Per Month &#9749;', 'wp-simple-firewall' ),
			__( 'Go PRO For The Equivalent Of 1 Beer Per Month &#127866;', 'wp-simple-firewall' ),
			__( 'Go PRO For The Equivalent Of 1 Glass Of Wine Per Month &#127863;', 'wp-simple-firewall' ),
		];
		$benefits = [
			__( 'The Easiest, Frustration-Free Pro-Upgrade Available Anywhere', 'wp-simple-firewall' ),
			__( 'MainWP Integration', 'wp-simple-firewall' ),
			__( 'Powerful, Auto-Learning Malware Scanner', 'wp-simple-firewall' ),
			__( 'Plugin and Theme File Guard', 'wp-simple-firewall' ),
			__( 'Vulnerability Scanner', 'wp-simple-firewall' ),
			__( 'Traffic Rate Limiting', 'wp-simple-firewall' ),
			__( 'WooCommerce Support', 'wp-simple-firewall' ),
			__( 'Automatic Import/Export Sync Of Options Across Your WP Portfolio', 'wp-simple-firewall' ),
			__( 'Powerful User Password Policies', 'wp-simple-firewall' ),
			__( 'Exclusive Customer Support', 'wp-simple-firewall' ),
			__( 'That Warm And Fuzzy Feeling That Comes From Supporting Future Development', 'wp-simple-firewall' ),
		];
		\shuffle( $benefits );

		$isWhitelabelled = $con->comps->whitelabel->isEnabled();
		return [
			'flags'   => [
				'is_pro'           => $con->isPremiumActive(),
				'is_whitelabelled' => $isWhitelabelled,
				'email_flags'      => \array_merge( [
					'is_admin_email' => true,
				], $this->action_data[ 'email_flags' ] ?? [] )
			],
			'hrefs'   => [
				'upgrade'             => 'https://clk.shldscrty.com/buyshieldproemailfooter',
				'much_more'           => 'https://clk.shldscrty.com/gp',
				'configure_recipient' => $con->plugin_urls->cfgForZoneComponent( Reporting::Slug() ),
			],
			'strings' => [
				'benefits'            => $benefits,
				'much_more'           => __( 'And So Much More', 'wp-simple-firewall' ),
				'upgrade'             => $goPro[ \array_rand( $goPro ) ],
				'thanks_pro'          => sprintf( __( 'Thank you for choosing %s.', 'wp-simple-firewall' ), self::con()->labels->Name ),
				'thanks_free'         => sprintf( __( 'Thank you for choosing %s (Free).', 'wp-simple-firewall' ), self::con()->labels->Name ),
				'upgrade_heading_prefix' => __( 'Upgrade To', 'wp-simple-firewall' ),
				'upgrade_heading_suffix' => __( "today and these are just some of the added benefits you'll get:", 'wp-simple-firewall' ),
				/* translators: %1$s: plugin name, %2$s: version number, %3$s: site name */
				'sent_from'           => sprintf( __( 'Email sent from the %1$s Plugin v%2$s, on %3$s.', 'wp-simple-firewall' ),
					$con->labels->Name,
					$con->cfg->version(),
					$WP->getHomeUrl()
				),
				'delays'              => __( 'Note: Any email delays or delivery issues are caused by website hosting and email providers.', 'wp-simple-firewall' ),
				'time_sent'           => sprintf( __( 'Time Sent: %s', 'wp-simple-firewall' ), $WP->getTimeStampForDisplay() ),
				'configure_recipient' => sprintf( __( 'Configure security email recipient (%s)', 'wp-simple-firewall' ),
					sprintf( __( 'currently %s', 'wp-simple-firewall' ), $con->comps->opts_lookup->getReportEmail() )
				),
			],
		];
	}
}
