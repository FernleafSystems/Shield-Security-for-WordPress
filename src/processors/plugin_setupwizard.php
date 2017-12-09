<?php

if ( class_exists( 'ICWP_WPSF_Processor_Plugin_SetupWizard', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wpsf.php' );

class ICWP_WPSF_Processor_Plugin_SetupWizard extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function run() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getFeature();
		add_action( 'init', array( $this, 'onWpInit' ), 0 );
	}

	public function onWpInit() {
		if ( $this->loadWpUsers()->isUserLoggedIn() ) { // TODO: can manage
			$this->loadWizard();
		}
	}

	public function ajaxSetupWizard() {
		var_dump( $_POST );
	}

	protected function loadWizard() {
		$this->printWizard();
		die();
	}

	/**
	 * @return bool true if valid form printed, false otherwise. Should die() if true
	 */
	public function printWizard() {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getFeature();
		$oCon = $this->getController();
		$aLoginIntentFields = apply_filters( $oFO->prefix( 'login-intent-form-fields' ), array() );

		$sMessage = $this->loadAdminNoticesProcessor()
						 ->flushFlashMessage()
						 ->getRawFlashMessageText();

		$aDisplayData = array(
			'strings' => array(
				'welcome'         => _wpsf__( 'Welcome' ),
				'time_remaining'  => _wpsf__( 'Time Remaining' ),
				'calculating'     => _wpsf__( 'Calculating' ).' ...',
				'seconds'         => strtolower( _wpsf__( 'Seconds' ) ),
				'login_expired'   => _wpsf__( 'Login Expired' ),
				'verify_my_login' => _wpsf__( 'Verify My Login' ),
				'more_info'       => _wpsf__( 'More Info' ),
				'what_is_this'    => _wpsf__( 'What is this?' ),
				'message'         => $sMessage,
				'page_title'      => sprintf( _wpsf__( '%s Login Verification' ), $oCon->getHumanName() )
			),
			'data'    => array(
				'login_fields' => $aLoginIntentFields,
			),
			'hrefs'   => array(
				'form_action'     => $this->loadDataProcessor()->getRequestUri(),
				'css_bootstrap'   => $oCon->getPluginUrl_Css( 'bootstrap3.min.css' ),
				'css_pages'       => $oCon->getPluginUrl_Css( 'pages.css' ),
				'css_steps'       => $oCon->getPluginUrl_Css( 'jquery.steps.css' ),
				'css_wizard'      => $oCon->getPluginUrl_Css( 'wizard.css' ),
				'js_jquery'       => $this->loadWpIncludes()->getUrl_Jquery(),
				'js_bootstrap'    => $oCon->getPluginUrl_Js( 'bootstrap3.min.js' ),
				'js_globalplugin' => $oCon->getPluginUrl_Js( 'global-plugin.js' ),
				'js_steps'        => $oCon->getPluginUrl_Js( 'jquery.steps.min.js' ),
				'shield_logo'     => 'https://plugins.svn.wordpress.org/wp-simple-firewall/assets/banner-1544x500-transparent.png',
				'what_is_this'    => 'https://icontrolwp.freshdesk.com/support/solutions/articles/3000064840',
				'favicon'         => $oCon->getPluginUrl_Image( 'pluginlogo_24x24.png' ),
			),
			'ajax'    => $oFO->getBaseAjaxActionRenderData( 'SetupWizard' ),

		);
		$this->loadRenderer( $this->getController()->getPath_Templates() )
			 ->setTemplate( 'pages/wizard.twig' )
			 ->setRenderVars( $aDisplayData )
			 ->setTemplateEngineTwig()
			 ->display();

		return true;
	}
}