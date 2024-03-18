<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class PluginBadge {

	use ExecOnce;
	use PluginControllerConsumer;

	protected function run() {
		$this->floatingBadge();
		add_action( 'widgets_init', [ $this, 'addPluginBadgeWidget' ] );
		add_shortcode( 'SHIELD_BADGE', function () {
			$this->render();
		} );
	}

	private function floatingBadge() {
		if ( !Services::WpGeneral()->isAjax() && !( is_admin() || is_network_admin() ) ) {

			$display = apply_filters( 'shield/show_security_badge',
				self::con()->opts->optIs( 'display_plugin_badge', 'Y' )
				&& ( Services::Request()->cookie( $this->getCookieIdBadgeState() ) != 'closed' )
			);

			if ( $display ) {
				add_filter( 'shield/custom_enqueue_assets', function ( array $assets ) {
					return \array_merge( $assets, [ 'badge' ] );
				} );
				add_action( 'wp_footer', [ $this, 'printPluginBadge' ], 100 );
				add_action( 'login_footer', [ $this, 'printPluginBadge' ], 100 );
			}
		}
	}

	/**
	 * https://wordpress.org/support/topic/fatal-errors-after-update-to-7-0-2/#post-11169820
	 */
	public function addPluginBadgeWidget() {
		if ( !\class_exists( 'Tribe_WP_Widget_Factory' ) ) {
			register_widget( BadgeWidget::class );
		}
	}

	private function getCookieIdBadgeState() :string {
		return self::con()->prefix( 'badgeState' );
	}

	public function printPluginBadge() {
		echo $this->render( true );
	}

	public function render( bool $isFloating = false ) :string {
		return self::con()->action_router->render( Actions\Render\Components\RenderPluginBadge::SLUG, [
			'is_floating' => $isFloating,
		] );
	}

	public function setBadgeStateClosed() :bool {
		return (bool)Services::Response()->cookieSet(
			$this->getCookieIdBadgeState(),
			'closed',
			DAY_IN_SECONDS
		);
	}
}