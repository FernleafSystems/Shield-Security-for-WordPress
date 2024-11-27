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
			'Go PRO For The Equivalent Of 1 Cappuccino Per Month &#9749;',
			'Go PRO For The Equivalent Of 1 Beer Per Month &#127866;',
			'Go PRO For The Equivalent Of 1 Glass Of Wine Per Month &#127863;',
		];
		$benefits = [
			'The Easiest, Frustration-Free Pro-Upgrade Available Anywhere',
			'MainWP Integration',
			'Powerful, Auto-Learning Malware Scanner',
			'Plugin and Theme File Guard',
			'Vulnerability Scanner',
			'Traffic Rate Limiting',
			'WooCommerce Support',
			'Automatic Import/Export Sync Of Options Across Your WP Portfolio',
			'Powerful User Password Policies',
			'Exclusive Customer Support',
			'That Warm And Fuzzy Feeling That Comes From Supporting Future Development',
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
				'much_more'           => 'And So Much More',
				'upgrade'             => $goPro[ \array_rand( $goPro ) ],
				'sent_from'           => sprintf( __( 'Email sent from the %s Plugin v%s, on %s.', 'wp-simple-firewall' ),
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