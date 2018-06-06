<?php

if ( class_exists( 'ICWP_WPSF_Processor_Plugin_Badge', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_wpsf.php' );

class ICWP_WPSF_Processor_Plugin_Badge extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getFeature();
		if ( $oFO->isDisplayPluginBadge() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'includeJquery' ) );
			add_action( 'login_enqueue_scripts', array( $this, 'includeJquery' ) );
			add_action( 'wp_footer', array( $this, 'printPluginBadge' ), 100 );
			add_action( 'login_footer', array( $this, 'printPluginBadge' ), 100 );
		}
		add_action( 'widgets_init', array( $this, 'addPluginBadgeWidget' ) );
		add_filter( $oFO->prefix( 'dashboard_widget_content' ), array( $this, 'gatherPluginWidgetContent' ), 100 );
	}

	public function includeJquery() {
		wp_enqueue_script( 'jquery', null, array(), false, true );
	}

	/**
	 * @param array $aContent
	 * @return array
	 */
	public function gatherPluginWidgetContent( $aContent ) {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getFeature();
		$oCon = $this->getController();

		$aLabels = $oCon->getPluginLabels();
		$sFooter = sprintf( _wpsf__( '%s is provided by %s' ), $oCon->getHumanName(),
			sprintf( '<a href="%s">%s</a>', $aLabels[ 'AuthorURI' ], $aLabels[ 'Author' ] )
		);

		$aDisplayData = array(
			'sInstallationDays' => sprintf( _wpsf__( 'Days Installed: %s' ), $this->getInstallationDays() ),
			'sFooter'           => $sFooter,
			'sIpAddress'        => sprintf( _wpsf__( 'Your IP address is: %s' ), $this->ip() )
		);

		if ( !is_array( $aContent ) ) {
			$aContent = array();
		}
		$aContent[] = $oFO->renderTemplate( 'snippets/widget_dashboard_plugin.php', $aDisplayData );
		return $aContent;
	}

	public function addPluginBadgeWidget() {
		$this->loadWpWidgets();
		require_once( dirname( __FILE__ ).'/plugin_badgewidget.php' );
		ICWP_WPSF_Processor_Plugin_BadgeWidget::SetFeatureOptions( $this->getFeature() );
		register_widget( 'ICWP_WPSF_Processor_Plugin_BadgeWidget' );
	}

	/**
	 * @uses echo
	 */
	public function printPluginBadge() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getFeature();
		try {
			echo $oFO->renderPluginBadge();
		}
		catch ( Exception $oE ) {
		}
	}
}