<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Services\Services;

class PluginBadge extends Modules\Base\Common\ExecOnceModConsumer {

	public const MOD = Plugin\ModCon::SLUG;

	protected function run() {

		$display = apply_filters( 'shield/show_security_badge',
			$this->getOptions()->isOpt( 'display_plugin_badge', 'Y' )
			&& ( Services::Request()->cookie( $this->getCookieIdBadgeState() ) != 'closed' )
		);

		if ( $display ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'includeJquery' ] );
			add_action( 'login_enqueue_scripts', [ $this, 'includeJquery' ] );
			add_action( 'wp_footer', [ $this, 'printPluginBadge' ], 100 );
			add_action( 'login_footer', [ $this, 'printPluginBadge' ], 100 );
		}

		add_action( 'widgets_init', [ $this, 'addPluginBadgeWidget' ] );

		add_shortcode( 'SHIELD_BADGE', function () {
			$this->render();
		} );
	}

	/**
	 * https://wordpress.org/support/topic/fatal-errors-after-update-to-7-0-2/#post-11169820
	 */
	public function addPluginBadgeWidget() {
		if ( !empty( $this->getMod() ) && !class_exists( 'Tribe_WP_Widget_Factory' ) ) {
			register_widget( new BadgeWidget( $this->getMod() ) );
		}
	}

	private function getCookieIdBadgeState() :string {
		return $this->getCon()->prefix( 'badgeState' );
	}

	public function includeJquery() {
		wp_enqueue_script( 'jquery', null, [], false, true );
	}

	public function printPluginBadge() {
		echo $this->render( true );
	}

	public function render( bool $isFloating = false ) :string {
		return $this->getCon()->action_router->render( Actions\Render\Components\RenderPluginBadge::SLUG, [
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

	/**
	 * @deprecated 17.0
	 */
	public function setIsDisplayPluginBadge( bool $isDisplay ) {
		$this->getOptions()->setOpt( 'display_plugin_badge', $isDisplay ? 'Y' : 'N' );
	}
}