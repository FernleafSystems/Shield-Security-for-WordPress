<?php

if ( class_exists( 'ICWP_WPSF_Processor_Plugin_Badge', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wpsf.php' );

class ICWP_WPSF_Processor_Plugin_Badge extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 * TODO: add ajax call when badge is closed
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getFeature();
		if ( $oFO->isDisplayPluginBadge() ) {
			add_action( 'wp_footer', array( $this, 'printPluginBadge' ) );
		}
		add_action( 'widgets_init', array( $this, 'addPluginBadgeWidget' ) );
		add_filter( $oFO->prefix( 'dashboard_widget_content' ), array( $this, 'gatherPluginWidgetContent' ), 100 );
	}

	/**
	 * @param array $aContent
	 * @return array
	 */
	public function gatherPluginWidgetContent( $aContent ) {

		$sFooter = sprintf( _wpsf__( '%s is provided by %s' ),
			$this->getController()->getHumanName(),
			sprintf( '<a href="%s">iControlWP</a>', 'http://icwp.io/7f' )
		);
		$aDisplayData = array(
			'sInstallationDays' => sprintf( _wpsf__( 'Days Installed: %s' ), $this->getInstallationDays() ),
			'sFooter'           => $sFooter,
			'sIpAddress'        => sprintf( _wpsf__( 'Your IP address is: %s' ), $this->human_ip() )
		);

		if ( !is_array( $aContent ) ) {
			$aContent = array();
		}
		$aContent[] = $this->getFeature()
						   ->renderTemplate( 'snippets/widget_dashboard_plugin.php', $aDisplayData );
		return $aContent;
	}

	public function addPluginBadgeWidget() {
		$this->loadWpWidgets();
		require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'plugin_badgewidget.php' );
		ICWP_WPSF_Processor_Plugin_BadgeWidget::SetFeatureOptions( $this->getFeature() );
		register_widget( 'ICWP_WPSF_Processor_Plugin_BadgeWidget' );
	}

	/**
	 * @uses echo
	 */
	public function printPluginBadge() {
		echo $this->renderPluginBadge();
	}

	/**
	 * @return string
	 */
	public function renderPluginBadge() {
		$oCon = $this->getController();
		$oRender = $this->loadRenderer( $oCon->getPath_Templates() );
		$sContents = $oRender
			->clearRenderVars()
			->setTemplateEnginePhp()
			->setRenderVars(
				array(
					'icwp_ajax_action_pluginbadge' => $this->getFeature()->prefix( 'PluginBadgeClose' ),
					'ajaxurl'                      => admin_url( 'admin-ajax.php' ),
				)
			)
			->setTemplate( 'snippets/plugin_badge' )
			->render();
		$sBadgeText = sprintf(
			_wpsf__( 'This Site Is Protected By %s' ),
			sprintf(
				'<br /><span style="font-weight: bold;">The %s &rarr;</span>',
				$oCon->getHumanName()
			)
		);
		$sBadgeText = apply_filters( 'icwp_shield_plugin_badge_text', $sBadgeText );
		return sprintf( $sContents, $oCon->getPluginUrl_Image( 'pluginlogo_32x32.png' ), $oCon->getHumanName(), $sBadgeText );
	}
}