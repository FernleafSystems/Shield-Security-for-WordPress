<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

class GuidedSetupWelcome extends Base {

	public const SLUG = 'guided_setup_welcome';

	public function getName() :string {
		return __( 'Welcome', 'wp-simple-firewall' );
	}

	protected function getStepRenderData() :array {
		$pluginName = self::con()->labels->Name;
		return [
			'strings' => [
				'step_title'          => __( "Get Started In Under 3 Minutes", 'wp-simple-firewall' ),
				/* translators: %s: plugin name */
				'welcome_text1'       => sprintf( __( '%s does a lot, so it can be hard to know where to begin.', 'wp-simple-firewall' ), $pluginName ),
				/* translators: %s: plugin name */
				'welcome_text2'       => sprintf( __( "This wizard will walk you through some of %s's key features, to get you up and running in under 3 minutes.", 'wp-simple-firewall' ), $pluginName ),
				/* translators: %s: plugin name */
				'welcome_video_title' => sprintf( __( '%s, Founder Introduction', 'wp-simple-firewall' ), $pluginName ),
				'lets_go'             => __( "Let's go!", 'wp-simple-firewall' ),
			],
		];
	}
}
