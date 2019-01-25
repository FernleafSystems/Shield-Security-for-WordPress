<?php

class ICWP_WPSF_Processor_BasePlugin extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function init() {
		parent::init();
		$oFO = $this->getMod();

		$sFunc = $oFO->isOpt( 'delete_on_deactivate', 'Y' ) ? '__return_true' : '__return_false';
		add_filter( $oFO->prefix( 'delete_on_deactivate' ), $sFunc );
	}

	/**
	 */
	public function run() {
	}

	/**
	 * Override the original collection to then add plugin statistics to the mix
	 * @param $aData
	 * @return array
	 */
	public function tracking_DataCollect( $aData ) {
		$aData = parent::tracking_DataCollect( $aData );
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();
		$sSlug = $oFO->getSlug();
		if ( empty( $aData[ $sSlug ][ 'options' ][ 'unique_installation_id' ] ) ) {
			$aData[ $sSlug ][ 'options' ][ 'unique_installation_id' ] = $oFO->getPluginInstallationId();
		}
		return $aData;
	}

	/**
	 * @param array $aAttrs
	 * @return bool
	 */
	protected function getIfDisplayAdminNotice( $aAttrs ) {

		if ( !parent::getIfDisplayAdminNotice( $aAttrs ) ) {
			return false;
		}
		if ( isset( $aAttrs[ 'delay_days' ] ) && is_int( $aAttrs[ 'delay_days' ] )
			 && ( $this->getInstallationDays() < $aAttrs[ 'delay_days' ] ) ) {
			return false;
		}
		return true;
	}

	/**
	 * @param $aAttr
	 * @throws \Exception
	 */
	public function addNotice_rate_plugin( $aAttr ) {

		$aRenderData = array(
			'notice_attributes' => $aAttr,
			'strings'           => array(
				'title'   => 'Will you help us out with a quick WordPress.org review?',
				'dismiss' => _wpsf__( "I'd rather not show this support" ).' / '._wpsf__( "I've done this already" ).' :D',
				'forums'  => __( 'Support Forums' )
			),
			'hrefs'             => array(
				'forums' => 'https://wordpress.org/support/plugin/wp-simple-firewall',
			)
		);
		$this->insertAdminNotice( $aRenderData );
	}

	/**
	 * @param array $aNoticeAttributes
	 * @throws \Exception
	 */
	public function addNotice_wizard_welcome( $aNoticeAttributes ) {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getMod();

		$sName = $this->getCon()->getHumanName();
		$aRenderData = array(
			'notice_attributes' => $aNoticeAttributes,
			'strings'           => array(
				'dismiss'  => _wpsf__( "I don't need the setup wizard just now" ),
				'title'    => sprintf( _wpsf__( 'Get started quickly with the %s Setup Wizard' ), $sName ),
				'setup'    => sprintf( _wpsf__( 'The welcome wizard will help you get setup quickly and become familiar with some of the core %s features' ), $sName ),
				'no_setup' => sprintf( _wpsf__( "%s has a helpful setup wizard to walk you through the main features. Unfortunately your PHP version is reeeaally old as it needs PHP 5.4+" ), $sName ),
			),
			'hrefs'             => array(
				'wizard' => $oFO->getUrl_Wizard( 'welcome' ),
			),
			'flags'             => array()
		);
		$this->insertAdminNotice( $aRenderData );
	}

	/**
	 * @see autoAddToAdminNotices()
	 * @param array $aNoticeAttributes
	 * @throws \Exception
	 */
	protected function addNotice_plugin_update_available( $aNoticeAttributes ) {
		$oPlugin = $this->getCon();
		$oNotices = $this->loadWpNotices();

		if ( $oNotices->isDismissed( 'plugin-update-available' ) ) {
			$aMeta = $oNotices->getMeta( 'plugin-update-available' );
			if ( $aMeta[ 'time' ] > $oPlugin->getReleaseTimestamp() ) {
				return;
			}
		}

		if ( !$this->getIfShowAdminNotices() ) {
			return;
		}

		$oWp = $this->loadWp();
		$oWpPlugins = $this->loadWpPlugins();
		$sBaseFile = $oPlugin->getPluginBaseFile();
		if ( !$oWp->getIsPage_Updates() && $oWpPlugins->isUpdateAvailable( $sBaseFile ) ) { // Don't show on the update page
			$aRenderData = array(
				'notice_attributes' => $aNoticeAttributes,
				'render_slug'       => 'plugin-update-available',
				'strings'           => array(
					'title'        => sprintf( _wpsf__( 'Update available for the %s plugin.' ), $this->getCon()
																									  ->getHumanName() ),
					'click_update' => _wpsf__( 'Please click to update immediately' ),
					'dismiss'      => _wpsf__( 'Dismiss this notice' )
				),
				'hrefs'             => array(
					'upgrade_link' => $oWpPlugins->getUrl_Upgrade( $sBaseFile )
				)
			);
			$this->insertAdminNotice( $aRenderData );
		}
	}

	/**
	 * @see autoAddToAdminNotices()
	 * @param array $aNoticeAttributes
	 */
	protected function addNotice_translate_plugin( $aNoticeAttributes ) {
		if ( $this->getIfShowAdminNotices() ) {
			$aRenderData = array(
				'notice_attributes' => $aNoticeAttributes,
				'strings'           => array(
					'title'        => 'Você não fala Inglês? No hablas Inglés? Heeft u geen Engels spreekt?',
					'like_to_help' => sprintf( _wpsf__( "Can you help translate the %s plugin?" ), $this->getCon()
																										->getHumanName() ),
					'head_over_to' => sprintf( _wpsf__( 'Head over to: %s' ), '' ),
					'site_url'     => 'translate.icontrolwp.com',
					'dismiss'      => _wpsf__( 'Dismiss this notice' )
				),
				'hrefs'             => array(
					'translate' => 'http://translate.icontrolwp.com'
				)
			);
			$this->insertAdminNotice( $aRenderData );
		}
	}

	/**
	 * @return bool
	 */
	protected function getIfShowAdminNotices() {
		return $this->getMod()->isOpt( 'enable_upgrade_admin_notice', 'Y' );
	}
}