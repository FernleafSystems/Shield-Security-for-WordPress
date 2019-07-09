<?php

use FernleafSystems\Wordpress\Services\Services;

class ICWP_WPSF_Processor_Plugin_Badge extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		if ( $oFO->isDisplayPluginBadge() ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'includeJquery' ] );
			add_action( 'login_enqueue_scripts', [ $this, 'includeJquery' ] );
			add_action( 'wp_footer', [ $this, 'printPluginBadge' ], 100 );
			add_action( 'login_footer', [ $this, 'printPluginBadge' ], 100 );
		}
		add_action( 'widgets_init', [ $this, 'addPluginBadgeWidget' ] );
		add_filter( $oFO->prefix( 'dashboard_widget_content' ), [ $this, 'gatherPluginWidgetContent' ], 100 );
	}

	public function includeJquery() {
		wp_enqueue_script( 'jquery', null, [], false, true );
	}

	/**
	 * @param array $aContent
	 * @return array
	 */
	public function gatherPluginWidgetContent( $aContent ) {
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		$oCon = $this->getCon();

		$aLabels = $oCon->getLabels();
		$sFooter = sprintf( __( '%s is provided by %s', 'wp-simple-firewall' ), $oCon->getHumanName(),
			sprintf( '<a href="%s">%s</a>', $aLabels[ 'AuthorURI' ], $aLabels[ 'Author' ] )
		);

		$aDisplayData = [
			'sInstallationDays' => sprintf( __( 'Days Installed: %s', 'wp-simple-firewall' ), $this->getInstallationDays() ),
			'sFooter'           => $sFooter,
			'sIpAddress'        => sprintf( __( 'Your IP address is: %s', 'wp-simple-firewall' ), Services::IP() )
		];

		if ( !is_array( $aContent ) ) {
			$aContent = [];
		}
		$aContent[] = $oFO->renderTemplate( 'snippets/widget_dashboard_plugin.php', $aDisplayData );
		return $aContent;
	}

	/**
	 * https://wordpress.org/support/topic/fatal-errors-after-update-to-7-0-2/#post-11169820
	 */
	public function addPluginBadgeWidget() {
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		if ( !empty( $oFO ) && Services::WpGeneral()->getWordpressIsAtLeastVersion( '4.6.0' )
			 && !class_exists( 'Tribe_WP_Widget_Factory' ) ) {
			register_widget( new ICWP_WPSF_Processor_Plugin_BadgeWidget( $oFO ) );
		}
	}

	/**
	 * @uses echo
	 */
	public function printPluginBadge() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		try {
			echo $oFO->renderPluginBadge();
		}
		catch ( \Exception $oE ) {
		}
	}
}