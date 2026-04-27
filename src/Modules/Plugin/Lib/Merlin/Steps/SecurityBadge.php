<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Opts\PluginBadgeMode;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Response;

class SecurityBadge extends Base {

	public const SLUG = 'security_badge';

	public function getName() :string {
		return __( 'Badge', 'wp-simple-firewall' );
	}

	protected function getStepRenderData() :array {
		$mode = self::con()->comps->opts_lookup->getPluginBadgeMode();

		return [
			'strings' => [
				'step_title' => __( "Show Your Visitors That You Take Security Seriously!", 'wp-simple-firewall' ),
			],
			'vars'    => [
				'security_badge_mode'    => $mode,
				'security_badge_options' => \array_map(
					static fn( array $option ) :array => \array_merge( $option, [
						'selected' => $option[ 'value_key' ] === $mode,
					] ),
					PluginBadgeMode::selectOptions()
				),
				'video_id'               => '552430272',
			],
		];
	}

	public function processStepFormSubmit( array $form ) :Response {
		$value = PluginBadgeMode::normalise( $form[ 'SecurityPluginBadge' ] ?? PluginBadgeMode::DISABLED );
		self::con()->opts->optSet( 'display_plugin_badge', $value )->store();

		$resp = parent::processStepFormSubmit( $form );
		$resp->success = true;
		$resp->message = PluginBadgeMode::isEnabled( $value ) ? __( 'The Security Badge will be displayed to your visitors', 'wp-simple-firewall' )
			: __( "The Security Badge won't be displayed to your visitors", 'wp-simple-firewall' );
		return $resp;
	}
}
