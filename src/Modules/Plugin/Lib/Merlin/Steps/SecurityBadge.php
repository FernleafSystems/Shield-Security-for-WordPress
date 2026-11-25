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
				'step_title'                 => __( "Show Your Visitors That You Take Security Seriously!", 'wp-simple-firewall' ),
				// One name for it throughout: the badge.
				/* translators: %s: plugin name */
				'security_badge_text_1'      => sprintf( __( 'The %s badge shows your visitors that you take security seriously.', 'wp-simple-firewall' ), self::con()->labels->Name ),
				'security_badge_text_2'      => __( 'Turning this on adds a translucent badge to the footer of your website.', 'wp-simple-firewall' ),
				'badge_heading'              => __( 'Show the badge', 'wp-simple-firewall' ),
				'badge_label'                => __( 'Where the badge shows', 'wp-simple-firewall' ),
				'security_badge_text_button' => __( 'Save badge setting', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'security_badge_options' => \array_map(
					static fn( array $option ) :array => \array_merge( $option, [
						'selected' => $option[ 'value_key' ] === $mode,
					] ),
					PluginBadgeMode::selectOptions()
				),
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
