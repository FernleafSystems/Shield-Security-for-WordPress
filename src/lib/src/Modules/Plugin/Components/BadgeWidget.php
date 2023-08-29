<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;

class BadgeWidget extends \WP_Widget {

	use ModConsumer;

	public function __construct() {
		parent::__construct(
			self::con()->prefixOption( 'plugin_badge' ),
			sprintf( __( '%s Plugin Badge', 'wp-simple-firewall' ), self::con()->getHumanName() ),
			[
				'description' => sprintf( __( 'You can now help spread the word about the %s plugin anywhere on your site', 'wp-simple-firewall' ),
					self::con()->getHumanName() ),
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
			echo sprintf( '%s%s%s%s',
				$args[ 'before_widget' ],
				!empty( $title ) ? ( $args[ 'before_title' ] ?? '' ).__( 'Site Secured', 'wp-simple-firewall' ).( $args[ 'after_title' ] ?? '' ) : '',
				$this->renderBadge(),
				$args[ 'after_widget' ]
			);
		}
	}

	public function renderBadge() :string {
		return ( new PluginBadge() )->render();
	}
}