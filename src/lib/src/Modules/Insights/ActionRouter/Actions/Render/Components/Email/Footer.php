<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Email;

use FernleafSystems\Wordpress\Services\Services;

class Footer extends Base {

	public const SLUG = 'render_email_footer';
	public const TEMPLATE = '/email/footer.twig';

	protected function getRenderData() :array {
		$con = $this->getCon();
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
		shuffle( $benefits );

		$isWhitelabelled = $con->getModule_SecAdmin()->getWhiteLabelController()->isEnabled();

		return [
			'strings' => [
				'benefits'  => $benefits,
				'much_more' => 'And So Much More',
				'upgrade'   => $goPro[ array_rand( $goPro ) ],
				'sent_from' => sprintf( __( 'Email sent from the %s Plugin v%s, on %s.', 'wp-simple-firewall' ),
					$this->getCon()->getHumanName(),
					$this->getCon()->getVersion(),
					$WP->getHomeUrl()
				),
				'delays'    => __( 'Note: Email delays are caused by website hosting and email providers.', 'wp-simple-firewall' ),
				'time_sent' => sprintf( __( 'Time Sent: %s', 'wp-simple-firewall' ), $WP->getTimeStampForDisplay() ),
			],
			'hrefs'   => [
				'upgrade'   => 'https://shsec.io/buyshieldproemailfooter',
				'much_more' => 'https://shsec.io/gp'
			],
			'flags'   => [
				'is_pro'           => $con->isPremiumActive(),
				'is_whitelabelled' => $isWhitelabelled
			]
		];
	}
}