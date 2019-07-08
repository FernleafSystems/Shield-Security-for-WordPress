<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class AdminNotices extends Shield\Modules\Base\AdminNotices {

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 * @throws \Exception
	 */
	protected function processNotice( $oNotice ) {

		switch ( $oNotice->id ) {

			case 'override-forceoff':
				$this->buildNotice_OverrideForceoff( $oNotice );
				break;

			case 'plugin-mailing-list-signup':
				$this->buildNotice_PluginMailingListSignup( $oNotice );
				break;

			case 'plugin-update-available':
				$this->buildNotice_UpdateAvailable( $oNotice );
				break;

			case 'wizard_welcome':
				$this->buildNotice_WelcomeWizard( $oNotice );
				break;

			case 'allow-tracking':
				$this->buildNotice_AllowTracking( $oNotice );
				break;

			default:
				parent::processNotice( $oNotice );
				break;
		}
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 */
	private function buildNotice_OverrideForceoff( $oNotice ) {
		$oCon = $this->getCon();
		$oMod = $this->getMod();

		$oNotice->display = $oCon->getIfForceOffActive();

		$oNotice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'   => sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ), sprintf( __( '%s is not protecting your site', 'wp-simple-firewall' ), $oCon->getHumanName() ) ),
				'message' => sprintf(
					__( 'Please delete the "%s" file to reactivate %s protection', 'wp-simple-firewall' ),
					'forceOff',
					$oCon->getHumanName()
				),
				'delete'  => __( 'Click here to automatically delete the file', 'wp-simple-firewall' )
			],
			'ajax'              => [
				'delete_forceoff' => $oMod->getAjaxActionData( 'delete_forceoff', true )
			]
		];
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 */
	private function buildNotice_PluginMailingListSignup( $oNotice ) {
		$oMod = $this->getMod();
		$oOpts = $oMod->getOptions();

		$sName = $this->getCon()->getHumanName();
		$oUser = Services::WpUsers()->getCurrentWpUser();

		$oNotice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'yes'            => "Yes please! I'd love to join in and learn more",
				'no'             => "No thanks, I'm not interested in such groups",
				'your_name'      => __( 'Your Name', 'wp-simple-firewall' ),
				'your_email'     => __( 'Your Email', 'wp-simple-firewall' ),
				'signup'         => __( 'Sign-Up', 'wp-simple-firewall' ),
				'dismiss'        => "No thanks, I'm not interested in such informative groups",
				'summary'        => sprintf( 'The %s team is helping raise awareness of WP Security issues
				and to provide guidance with the %s plugin.', $sName, $sName ),
				'privacy_policy' => sprintf(
					'I certify that I have read and agree to the <a href="%s" target="_blank">Privacy Policy</a>',
					$oMod->getDef( 'href_privacy_policy' )
				),
				'consent'        => sprintf( __( 'I agree to Ts & Cs', 'wp-simple-firewall' ) )
			],
			'hrefs'             => [
				'privacy_policy' => $oOpts->getDef( 'href_privacy_policy' )
			],
			'install_days'      => $oOpts->getInstallationDays(),
			'vars'              => [
				'name'         => $oUser->first_name,
				'user_email'   => $oUser->user_email,
				'drip_form_id' => $oNotice->drip_form_id
			]
		];
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 */
	private function buildNotice_UpdateAvailable( $oNotice ) {
		$oWpPlugins = Services::WpPlugins();
		$sBaseFile = $this->getCon()->getPluginBaseFile();
		$oNotice->display = $oWpPlugins->isUpdateAvailable( $sBaseFile ) && !Services::WpPost()->isPage_Updates();

		$sName = $this->getCon()->getHumanName();

		$oNotice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'        => sprintf( __( 'Update available for the %s plugin', 'wp-simple-firewall' ), $sName ),
				'click_update' => __( 'Please click to update immediately', 'wp-simple-firewall' ),
				'dismiss'      => __( 'Dismiss this notice', 'wp-simple-firewall' )
			],
			'hrefs'             => [
				'upgrade_link' => $oWpPlugins->getUrl_Upgrade( $sBaseFile )
			]
		];
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 */
	private function buildNotice_WelcomeWizard( $oNotice ) {
		$sName = $this->getCon()->getHumanName();
		$oNotice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'dismiss' => __( "I don't need the setup wizard just now", 'wp-simple-firewall' ),
				'title'   => sprintf( __( 'Get started quickly with the %s Setup Wizard', 'wp-simple-firewall' ), $sName ),
				'setup'   => sprintf( __( 'The welcome wizard will help you get setup quickly and become familiar with some of the core %s features', 'wp-simple-firewall' ), $sName ),
				'launch'  => sprintf( __( "Launch the welcome wizard", 'wp-simple-firewall' ), $sName ),
			],
			'hrefs'             => [
				'wizard' => $this->getMod()->getUrl_Wizard( 'welcome' ),
			],
		];
	}

	/**
	 * @param Shield\Utilities\AdminNotices\NoticeVO $oNotice
	 */
	private function buildNotice_AllowTracking( $oNotice ) {
		/** @var \ICWP_WPSF_FeatureHandler_Plugin $oMod */
		$oMod = $this->getMod();
		$sName = $this->getCon()->getHumanName();
		$oNotice->display = !$oMod->isTrackingPermissionSet();
		$oNotice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'           => sprintf( __( "Make %s even better by sharing usage info?", 'wp-simple-firewall' ), $sName ),
				'want_to_track'   => sprintf( __( "We're hoping to understand how %s is configured and used.", 'wp-simple-firewall' ), $sName ),
				'what_we_collect' => __( "We'd like to understand how effective it is on a global scale.", 'wp-simple-firewall' ),
				'data_anon'       => __( 'The data sent is always completely anonymous and we can never track you or your site.', 'wp-simple-firewall' ),
				'can_turn_off'    => __( 'It can be turned-off at any time within the plugin options.', 'wp-simple-firewall' ),
				'click_to_see'    => __( 'Click to see the RAW data that would be sent', 'wp-simple-firewall' ),
				'learn_more'      => __( 'Learn More.', 'wp-simple-firewall' ),
				'site_url'        => 'translate.icontrolwp.com',
				'yes'             => __( 'Absolutely', 'wp-simple-firewall' ),
				'yes_i_share'     => __( "Yes, I'd be happy share this info", 'wp-simple-firewall' ),
				'hmm_learn_more'  => __( "I'd like to learn more, please", 'wp-simple-firewall' ),
				'no_help'         => __( "No, I don't want to help", 'wp-simple-firewall' ),
			],
			'ajax'              => [
				'set_plugin_tracking_perm' => $oMod->getAjaxActionData( 'set_plugin_tracking_perm', true ),
			],
			'hrefs'             => [
				'learn_more'       => 'http://translate.icontrolwp.com',
				'link_to_see'      => $oMod->getLinkToTrackingDataDump(),
				'link_to_moreinfo' => 'https://icwp.io/shieldtrackinginfo',

			]
		];
	}
}