<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

class ThankYou extends Base {

	public const SLUG = 'thank_you';

	public function getName() :string {
		return __( 'Thanks!', 'wp-simple-firewall' );
	}

	protected function getStepRenderData() :array {
		$con = self::con();
		return [
			'hrefs'   => [
				'facebook'  => 'https://clk.shldscrty.com/pluginshieldsecuritygroupfb',
				'twitter'   => 'https://clk.shldscrty.com/pluginshieldsecuritytwitter',
				'email'     => 'https://clk.shldscrty.com/pluginshieldsecuritynewsletter',
				'dashboard' => $con->plugin_urls->adminHome(),
			],
			'imgs'    => [
				'facebook' => $con->svgs->raw( 'facebook.svg' ),
				'twitter'  => $con->svgs->raw( 'twitter.svg' ),
				'email'    => $con->svgs->raw( 'envelope-fill.svg' ),
			],
			'vars'    => [
				'video_id' => '269364269',
			],
			'strings' => [
				'step_title'          => sprintf( __( 'Thank You For Choosing %s', 'wp-simple-firewall' ), $con->labels->Name ),
				'blurb_intro'         => __( 'Thank you for taking the time to go through the Guided Setup Wizard.', 'wp-simple-firewall' ),
				'blurb_configured'    => sprintf( __( 'By default, %s is automatically configured to protect your site from numerous threats and block bad visitors and bots without you having to do anything.', 'wp-simple-firewall' ), $con->labels->Name ),
				'protecting_intro'    => sprintf( __( 'Here are some of the ways %s is already protecting your site:', 'wp-simple-firewall' ), $con->labels->Name ),
				'list_firewall'       => __( 'Powerful <strong>Firewall</strong> is intercepting and blocking malicious traffic.', 'wp-simple-firewall' ),
				'list_bots'           => __( 'Always <strong>watching for bots</strong> and maintaining a reputation score for each visitor.', 'wp-simple-firewall' ),
				'list_scanning'       => __( '<strong>Scanning</strong> your core WordPress files and directories for changes and new files.', 'wp-simple-firewall' ),
				'list_blocking'       => __( 'Automatically <strong>blocking</strong> malicious visitors by IP (so you don\'t have to maintain an IP list yourself).', 'wp-simple-firewall' ),
				'list_comments'       => __( 'Protecting against the #1 source of <strong>Comment SPAM</strong>.', 'wp-simple-firewall' ),
				'list_activity_log'   => __( 'Keeping a log of <em>everything</em> significant in your <strong>Activity Log</strong>.', 'wp-simple-firewall' ),
				'list_forms'          => __( 'Protecting your <strong>important user forms</strong> such as Login, Registration and Lost Password.', 'wp-simple-firewall' ),
				'next_button'         => sprintf( __( 'Go To %s Overview', 'wp-simple-firewall' ), $con->labels->Name ),
			],
		];
	}
}
