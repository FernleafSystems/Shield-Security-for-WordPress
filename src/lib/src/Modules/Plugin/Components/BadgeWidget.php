<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModCon;

class BadgeWidget extends \WP_Widget {

	use ModConsumer;

	public const MOD = ModCon::SLUG;

	public function __construct( $mod ) {
		if ( empty( $mod ) ) {
			return;
		}
		$this->setMod( $mod );

		parent::__construct(
			$this->con()->prefixOption( 'plugin_badge' ),
			sprintf( __( '%s Plugin Badge', 'wp-simple-firewall' ), $this->con()->getHumanName() ),
			[
				'description' => sprintf( __( 'You can now help spread the word about the %s plugin anywhere on your site', 'wp-simple-firewall' ),
					$this->con()->getHumanName() ),
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
		if ( is_array( $args ) ) {
			echo sprintf( '%s%s%s%s',
				$args[ 'before_widget' ],
				!empty( $title ) ? ( $args[ 'before_title' ] ?? '' ).__( 'Site Secured', 'wp-simple-firewall' ).( $args[ 'after_title' ] ?? '' ) : '',
				$this->renderBadge(),
				$args[ 'after_widget' ]
			);
		}
	}

	public function renderBadge() :string {
		return ( new PluginBadge() )
			->setMod( $this->mod() )
			->render();
	}
}