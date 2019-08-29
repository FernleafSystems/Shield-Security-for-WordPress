<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;

/**
 * Class PluginBadge
 * @package FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Components
 */
class PluginBadge {

	use ModConsumer;

	public function run() {
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getMod();
		if ( $oMod->isDisplayPluginBadge() ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'includeJquery' ] );
			add_action( 'login_enqueue_scripts', [ $this, 'includeJquery' ] );
			add_action( 'wp_footer', [ $this, 'printPluginBadge' ], 100 );
			add_action( 'login_footer', [ $this, 'printPluginBadge' ], 100 );
		}

		add_action( 'widgets_init', [ $this, 'addPluginBadgeWidget' ] );

		add_shortcode( 'SHIELD_BADGE', function () {
			$this->render( false );
		} );
	}

	/**
	 * https://wordpress.org/support/topic/fatal-errors-after-update-to-7-0-2/#post-11169820
	 */
	public function addPluginBadgeWidget() {
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getMod();
		if ( !empty( $oMod ) && Services::WpGeneral()->getWordpressIsAtLeastVersion( '4.6.0' )
			 && !class_exists( 'Tribe_WP_Widget_Factory' ) ) {
			register_widget( new BadgeWidget( $oMod ) );
		}
	}

	public function includeJquery() {
		wp_enqueue_script( 'jquery', null, [], false, true );
	}

	public function printPluginBadge() {
		echo $this->render( true );
	}

	/**
	 * @param bool $bFloating
	 * @return string
	 */
	public function render( $bFloating = false ) {
		$oMod = $this->getMod();
		$oCon = $this->getCon();

		$sName = $oCon->getHumanName();
		$aData = [
			'ajax'    => [
				'plugin_badge_close' => $oMod->getAjaxActionData( 'plugin_badge_close', true ),
			],
			'flags'   => [
				'nofollow'    => apply_filters( 'icwp_shield_badge_relnofollow', false ),
				'is_floating' => $bFloating
			],
			'hrefs'   => [
				'badge' => 'https://icwp.io/wpsecurityfirewall',
				'logo'  => $oCon->getPluginUrl_Image( 'shield/shield-security-logo-colour-32px.png' ),
			],
			'strings' => [
				'protected' => apply_filters( 'icwp_shield_plugin_badge_text',
					sprintf( __( 'This Site Is Protected By %s', 'wp-simple-firewall' ),
						'<br/><span class="plugin-badge-name">'.$sName.'</span>' )
				),
				'name'      => $sName,
			],
		];

		try {
			$sRender = $oMod->renderTemplate( 'snippets/plugin_badge_widget', $aData, true );
		}
		catch ( \Exception $oE ) {
			$sRender = 'Could not generate badge: '.$oE->getMessage();
		}

		return $sRender;
	}
}