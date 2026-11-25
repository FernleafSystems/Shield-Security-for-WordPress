<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class BadgeWidget extends \WP_Widget {

	use PluginControllerConsumer;

	public function __construct() {
		parent::__construct(
			self::con()->prefix( 'plugin_badge', '_' ),
			/* translators: %s: plugin name */
			sprintf( __( '%s Plugin Badge', 'wp-simple-firewall' ), self::con()->labels->Name ),
			[
				/* translators: %s: plugin name */
				'description' => sprintf( __( 'You can now help spread the word about the %s plugin anywhere on your site', 'wp-simple-firewall' ),
					self::con()->labels->Name ),
			]
		);

		add_shortcode( 'SHIELD_BADGE', [ $this, 'renderBadge' ] );
	}

	/**
	 * @param array $args
	 * @param array $instance
	 * @throws \Exception
	 */
	public function widget( $args, $instance ) {
		if ( \is_array( $args ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- The before/after arguments are the theme's widget wrapper markup, which WordPress passes to be printed as is; the badge is a Twig render whose values are escaped, apart from the badge text, which is markup that Shield and the badge filters supply on purpose.
			echo sprintf( '%s%s%s%s',
				$args[ 'before_widget' ],
				!empty( $title ) ? ( $args[ 'before_title' ] ?? '' ).esc_html__( 'Site Secured', 'wp-simple-firewall' ).( $args[ 'after_title' ] ?? '' ) : '',
				$this->renderBadge(),
				$args[ 'after_widget' ]
			);
			// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	public function renderBadge() :string {
		return ( new PluginBadge() )->render();
	}
}