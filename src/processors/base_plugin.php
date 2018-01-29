<?php

if ( class_exists( 'ICWP_WPSF_Processor_BasePlugin', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).DIRECTORY_SEPARATOR.'base_wpsf.php' );

class ICWP_WPSF_Processor_BasePlugin extends ICWP_WPSF_Processor_BaseWpsf {

	/**
	 */
	public function init() {
		parent::init();
		$oFO = $this->getFeature();
		add_filter( $oFO->prefix( 'show_marketing' ), array( $this, 'getIsShowMarketing' ) );
		add_filter( $oFO->prefix( 'delete_on_deactivate' ), array( $this, 'getIsDeleteOnDeactivate' ) );
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
		$oFO = $this->getFeature();
		$sSlug = $oFO->getFeatureSlug();
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
	 * @throws Exception
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
	 * @throws Exception
	 */
	public function addNotice_wizard_welcome( $aNoticeAttributes ) {
		/** @var ICWP_WPSF_FeatureHandler_Plugin $oFO */
		$oFO = $this->getFeature();

		$bCanWizardWelcome = $oFO->canRunWizards();

		$aRenderData = array(
			'notice_attributes' => $aNoticeAttributes,
			'strings'           => array(
				'dismiss'  => _wpsf__( "I don't need the setup wizard just now" ),
				'title'    => _wpsf__( 'Get started quickly with the Shield Security Setup Wizard' ),
				'setup'    => _wpsf__( 'The welcome wizard will help you get setup quickly and become familiar with some of the core Shield Security features.' ),
				'no_setup' => _wpsf__( "Shield Security has a helpful setup wizard to walk you through the main features. Unfortunately your PHP version is reeeaally old as it needs PHP 5.4+ " )
			),
			'hrefs'             => array(
				'wizard' => $bCanWizardWelcome ? $oFO->getUrl_Wizard( 'welcome' ) : 'javascript:{event.preventDefault();}',
			),
			'flags'             => array(
				'can_wizard' => $bCanWizardWelcome,
			)
		);
		$this->insertAdminNotice( $aRenderData );
	}

	/**
	 * removed
	 * @see autoAddToAdminNotices()
	 * @param array $aAttr
	 * @throws Exception
	 */
	protected function addNotice_php54_version_warning( $aAttr ) {
		$oDp = $this->loadDP();
		if ( $oDp->getPhpVersionIsAtLeast( '5.4.0' ) ) {
			return;
		}

		$oCon = $this->getController();
		$aRenderData = array(
			'notice_attributes' => $aAttr,
			'strings'           => array(
				'title'         => sprintf( _wpsf__( 'Your PHP version is very old: %s' ), $oDp->getPhpVersion() ),
				'not_supported' => sprintf( _wpsf__( 'Newer features of %s do not support your PHP version.' ), $oCon->getHumanName() ),
				'ask_host'      => _wpsf__( 'You should ask your host to upgrade or provide a much newer PHP version.' ),
				'questions'     => _wpsf__( 'Please read here for further information:' ),
				'dismiss'       => _wpsf__( 'Dismiss this notice' ),
				'help'          => _wpsf__( 'Dropping support for PHP 5.2 and 5.3' )
			),
			'hrefs'             => array(
				'help' => 'http://icwp.io/aq',
			)
		);
		$this->insertAdminNotice( $aRenderData );
	}

	/**
	 * @see autoAddToAdminNotices()
	 * @param array $aNoticeAttributes
	 * @throws Exception
	 */
	protected function addNotice_plugin_update_available( $aNoticeAttributes ) {
		$oPlugin = $this->getController();
		$oNotices = $this->loadAdminNoticesProcessor();

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
					'title'        => sprintf( _wpsf__( 'Update available for the %s plugin.' ), $this->getController()
																									  ->getHumanName() ),
					'click_update' => _wpsf__( 'Please click to update immediately' ),
					'dismiss'      => _wpsf__( 'Dismiss this notice' )
				),
				'hrefs'             => array(
					'upgrade_link' => $oWpPlugins->getLinkPluginUpgrade( $sBaseFile )
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
					'like_to_help' => sprintf( _wpsf__( "Can you help translate the %s plugin?" ), $this->getController()
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
	public function getIsDeleteOnDeactivate() {
		return $this->getFeature()->getOptIs( 'delete_on_deactivate', 'Y' );
	}

	/**
	 * @param bool $bShow
	 * @return bool
	 */
	public function getIsShowMarketing( $bShow ) {
		if ( !$bShow ) {
			return $bShow;
		}

		if ( $this->getInstallationDays() < 1 ) {
			$bShow = false;
		}

		$oWpFunctions = $this->loadWp();
		if ( class_exists( 'Worpit_Plugin' ) ) {
			if ( method_exists( 'Worpit_Plugin', 'IsLinked' ) ) {
				$bShow = !Worpit_Plugin::IsLinked();
			}
			else if ( $oWpFunctions->getOption( Worpit_Plugin::$VariablePrefix.'assigned' ) == 'Y'
					  && $oWpFunctions->getOption( Worpit_Plugin::$VariablePrefix.'assigned_to' ) != '' ) {

				$bShow = false;
			}
		}
		return $bShow;
	}

	/**
	 * @return bool
	 */
	protected function getIfShowAdminNotices() {
		return $this->getFeature()->getOptIs( 'enable_upgrade_admin_notice', 'Y' );
	}
}