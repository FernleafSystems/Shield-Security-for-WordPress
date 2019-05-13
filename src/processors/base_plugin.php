<?php

use FernleafSystems\Wordpress\Services\Services;

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

		$aRenderData = [
			'notice_attributes' => $aAttr,
			'strings'           => [
				'title'   => 'Will you help us out with a quick WordPress.org review?',
				'dismiss' => __( "I'd rather not show this support", 'wp-simple-firewall' ).' / '.__( "I've done this already", 'wp-simple-firewall' ).' :D',
				'forums'  => __( 'Support Forums' )
			],
			'hrefs'             => [
				'forums' => 'https://wordpress.org/support/plugin/wp-simple-firewall',
			]
		];
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
		$aRenderData = [
			'notice_attributes' => $aNoticeAttributes,
			'strings'           => [
				'dismiss'  => __( "I don't need the setup wizard just now", 'wp-simple-firewall' ),
				'title'    => sprintf( __( 'Get started quickly with the %s Setup Wizard', 'wp-simple-firewall' ), $sName ),
				'setup'    => sprintf( __( 'The welcome wizard will help you get setup quickly and become familiar with some of the core %s features', 'wp-simple-firewall' ), $sName ),
				'no_setup' => sprintf( __( "%s has a helpful setup wizard to walk you through the main features. Unfortunately your PHP version is reeeaally old as it needs PHP 5.4+", 'wp-simple-firewall' ), $sName ),
			],
			'hrefs'             => [
				'wizard' => $oFO->getUrl_Wizard( 'welcome' ),
			],
			'flags'             => []
		];
		$this->insertAdminNotice( $aRenderData );
	}

	/**
	 * @param array $aNoticeAttributes
	 * @throws \Exception
	 * @see autoAddToAdminNotices()
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

		$oWpPlugins = Services::WpPlugins();
		$sBaseFile = $oPlugin->getPluginBaseFile();
		if ( $this->getIfShowAdminNotices() && $oWpPlugins->isUpdateAvailable( $sBaseFile )
			 && !Services::WpPost()->isPage_Updates() ) { // Don't show on the update page
			$aRenderData = [
				'notice_attributes' => $aNoticeAttributes,
				'render_slug'       => 'plugin-update-available',
				'strings'           => [
					'title'        => sprintf( __( 'Update available for the %s plugin.', 'wp-simple-firewall' ), $this->getCon()
																													   ->getHumanName() ),
					'click_update' => __( 'Please click to update immediately', 'wp-simple-firewall' ),
					'dismiss'      => __( 'Dismiss this notice', 'wp-simple-firewall' )
				],
				'hrefs'             => [
					'upgrade_link' => $oWpPlugins->getUrl_Upgrade( $sBaseFile )
				]
			];
			$this->insertAdminNotice( $aRenderData );
		}
	}

	/**
	 * @param array $aNoticeAttributes
	 * @see autoAddToAdminNotices()
	 */
	protected function addNotice_translate_plugin( $aNoticeAttributes ) {
		if ( $this->getIfShowAdminNotices() ) {
			$aRenderData = [
				'notice_attributes' => $aNoticeAttributes,
				'strings'           => [
					'title'        => 'Você não fala Inglês? No hablas Inglés? Heeft u geen Engels spreekt?',
					'like_to_help' => sprintf( __( "Can you help translate the %s plugin?", 'wp-simple-firewall' ), $this->getCon()
																														 ->getHumanName() ),
					'head_over_to' => sprintf( __( 'Head over to: %s', 'wp-simple-firewall' ), '' ),
					'site_url'     => 'translate.icontrolwp.com',
					'dismiss'      => __( 'Dismiss this notice', 'wp-simple-firewall' )
				],
				'hrefs'             => [
					'translate' => 'http://translate.icontrolwp.com'
				]
			];
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