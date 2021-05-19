<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

class AdminNotices extends Shield\Modules\Base\AdminNotices {

	/**
	 * @inheritDoc
	 */
	protected function processNotice( NoticeVO $notice ) {

		switch ( $notice->id ) {

			case 'plugin-too-old':
				$this->buildNotice_PluginTooOld( $notice );
				break;

			case 'php7':
				$this->buildNotice_Php7( $notice );
				break;

			case 'override-forceoff':
				$this->buildNotice_OverrideForceoff( $notice );
				break;

			case 'plugin-disabled':
				$this->buildNotice_PluginDisabled( $notice );
				break;

			case 'update-available':
				$this->buildNotice_UpdateAvailable( $notice );
				break;

			case 'compat-sgoptimize':
				$this->buildNotice_CompatSgOptimize( $notice );
				break;

			case 'plugin-mailing-list-signup':
				$this->buildNotice_PluginMailingListSignup( $notice );
				break;

			case 'wizard_welcome':
				$this->buildNotice_WelcomeWizard( $notice );
				break;

			case 'allow-tracking':
				$this->buildNotice_AllowTracking( $notice );
				break;

			case 'rate-plugin':
				$this->buildNotice_RatePlugin( $notice );
				break;

			default:
				parent::processNotice( $notice );
				break;
		}
	}

	public function handleAuthAjax( array $ajaxResponse ) :array {

		if ( empty( $ajaxResponse ) ) {
			switch ( Services::Request()->request( 'exec' ) ) {

				case 'set_plugin_tracking':
					$ajaxResponse = $this->ajaxExec_SetPluginTrackingPerm();
					break;

				default:
					$ajaxResponse = parent::handleAuthAjax( $ajaxResponse );
					break;
			}
		}
		return $ajaxResponse;
	}

	private function ajaxExec_SetPluginTrackingPerm() :array {
		/** @var Options $opts */
		$opts = $this->getOptions();
		$opts->setPluginTrackingPermission( (bool)Services::Request()->query( 'agree', false ) );
		return $this->ajaxExec_DismissAdminNotice();
	}

	private function buildNotice_PluginTooOld( NoticeVO $notice ) {
		$name = $this->getCon()->getHumanName();

		$notice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'     => sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ),
					sprintf( __( "%s Plugin Is Too Old", 'wp-simple-firewall' ), $name ) ),
				'lines'     => [
					sprintf(
						__( 'There are at least 2 major upgrades to the %s plugin since your version.', 'wp-simple-firewall' ),
						$name
					),
					__( "We recommended keeping your Shield plugin up-to-date with the latest features.", 'wp-simple-firewall' )
					.' '.__( "We can't support old versions of Shield and certain features may not be working properly as our API develops.", 'wp-simple-firewall' ),
				],
				'click_update' => __( 'Click here to go to the WordPress updates page', 'wp-simple-firewall' )
			],
			'hrefs'             => [
				'click_update' => Services::WpGeneral()->getAdminUrl_Updates()
			]
		];
	}

	private function buildNotice_Php7( NoticeVO $notice ) {
		$name = $this->getCon()->getHumanName();

		$notice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'     => sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ),
					sprintf( __( "%s 10+ Wont Be Available For Your Site", 'wp-simple-firewall' ), $name ) ),
				'lines'     => [
					sprintf(
						__( '%s 10 wont support old versions of PHP, including yours (PHP: %s).', 'wp-simple-firewall' ),
						$name, Services::Data()->getPhpVersionCleaned( true )
					),
					__( "We recommended updating your server's PHP version ASAP.", 'wp-simple-firewall' )
					.' '.__( "Your webhost will be able to help guide you in this.", 'wp-simple-firewall' ),
				],
				'read_more' => __( 'Click here to read more about this', 'wp-simple-firewall' )
			],
			'hrefs'             => [
				'read_more' => 'https://shsec.io/h3'
			]
		];
	}

	private function buildNotice_OverrideForceoff( NoticeVO $notice ) {
		$name = $this->getCon()->getHumanName();

		$notice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'   => sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ), sprintf( __( '%s is not protecting your site', 'wp-simple-firewall' ), $name ) ),
				'message' => sprintf(
					__( 'Please delete the "%s" file to reactivate %s protection', 'wp-simple-firewall' ),
					'forceOff',
					$name
				),
				'delete'  => __( 'Click here to automatically delete the file', 'wp-simple-firewall' )
			],
			'ajax'              => [
				'delete_forceoff' => $this->getMod()->getAjaxActionData( 'delete_forceoff', true )
			]
		];
	}

	private function buildNotice_PluginDisabled( NoticeVO $notice ) {
		$name = $this->getCon()->getHumanName();

		$notice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'          => sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ), sprintf( __( '%s is not protecting your site', 'wp-simple-firewall' ), $name ) ),
				'message'        => implode( ' ', [
					__( 'The plugin is currently switched-off completely.', 'wp-simple-firewall' ),
					__( 'All features and any security protection they provide are disabled.', 'wp-simple-firewall' ),
				] ),
				'jump_to_enable' => __( 'Click to jump to the relevant option', 'wp-simple-firewall' )
			],
			'hrefs'             => [
				'jump_to_enable' => $this->getMod()->getUrl_DirectLinkToOption( 'global_enable_plugin_features' )
			]
		];
	}

	private function buildNotice_CompatSgOptimize( NoticeVO $notice ) {
		$name = $this->getCon()->getHumanName();

		$notice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'               => sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ),
					sprintf( __( 'Site Ground Optimizer plugin has a conflict', 'wp-simple-firewall' ), $name ) ),
				'message'             => sprintf(
											 __( 'The SG Optimizer plugin has 2 settings which are breaking your site and certain %s features.', 'wp-simple-firewall' ),
											 $name
										 )
										 .' '.sprintf( 'The problematic options are: "Defer Render-blocking JS" and "Remove Query Strings From Static Resources".' ),
				'learn_more'          => sprintf( 'Click here to learn more' ),
				'sgoptimizer_turnoff' => __( 'Click here to automatically turn off those options.', 'wp-simple-firewall' )
			],
			'ajax'              => [
				'sgoptimizer_turnoff' => $this->getMod()->getAjaxActionData( 'sgoptimizer_turnoff', true )
			]
		];
	}

	private function buildNotice_PluginMailingListSignup( NoticeVO $notice ) {
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();

		$name = $this->getCon()->getHumanName();
		$user = Services::WpUsers()->getCurrentWpUser();

		$notice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'yes'            => "Yes please! I'd love to join in and learn more",
				'no'             => "No thanks, I'm not interested in such groups",
				'your_name'      => __( 'Your Name', 'wp-simple-firewall' ),
				'your_email'     => __( 'Your Email', 'wp-simple-firewall' ),
				'signup'         => __( 'Sign-Up', 'wp-simple-firewall' ),
				'dismiss'        => "No thanks, I'm not interested in such informative groups",
				'summary'        => sprintf( 'The %s team is helping raise awareness of WP Security issues
				and to provide guidance with the %s plugin.', $name, $name ),
				'privacy_policy' => sprintf(
					'I certify that I have read and agree to the <a href="%s" target="_blank">Privacy Policy</a>',
					$oOpts->getDef( 'href_privacy_policy' )
				),
				'consent'        => sprintf( __( 'I agree to Ts & Cs', 'wp-simple-firewall' ) )
			],
			'hrefs'             => [
				'privacy_policy' => $oOpts->getDef( 'href_privacy_policy' )
			],
			'install_days'      => $oOpts->getInstallationDays(),
			'vars'              => [
				'name'         => $user->first_name,
				'user_email'   => $user->user_email,
				'drip_form_id' => $notice->drip_form_id
			]
		];
	}

	private function buildNotice_UpdateAvailable( NoticeVO $notice ) {
		$name = $this->getCon()->getHumanName();
		$notice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'        => sprintf( __( 'Update available for the %s plugin', 'wp-simple-firewall' ), $name ),
				'click_update' => __( 'Please click to update immediately', 'wp-simple-firewall' ),
				'dismiss'      => __( 'Dismiss this notice', 'wp-simple-firewall' )
			],
			'hrefs'             => [
				'upgrade_link' => Services::WpPlugins()->getUrl_Upgrade( $this->getCon()->base_file )
			]
		];
	}

	private function buildNotice_WelcomeWizard( NoticeVO $notice ) {
		$name = $this->getCon()->getHumanName();
		$insideWizard = Services::Request()->query( 'wizard', false ) === 'welcome';
		$notice->render_data = [
			'notice_attributes' => [
				'insideWizard' => $insideWizard ? 1 : 0,
			],
			'strings'           => [
				'dismiss' => __( "I don't need the setup wizard just now", 'wp-simple-firewall' ),
				'title'   => sprintf( __( 'Get started quickly with the %s Setup Wizard', 'wp-simple-firewall' ), $name ),
				'setup'   => sprintf( __( 'The welcome wizard will help you get setup quickly and become familiar with some of the core %s features', 'wp-simple-firewall' ), $name ),
				'launch'  =>  sprintf( __( "Launch the welcome wizard", 'wp-simple-firewall' ), $name ),
			],
			'hrefs'             => [
				'wizard' => $this->getMod()->getUrl_Wizard( 'welcome' ),
			],
		];
	}

	private function buildNotice_AllowTracking( NoticeVO $notice ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$name = $this->getCon()->getHumanName();

		$notice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'           => sprintf( __( "Make %s even better by sharing usage info?", 'wp-simple-firewall' ), $name ),
				'want_to_track'   => sprintf( __( "We're hoping to understand how %s is configured and used.", 'wp-simple-firewall' ), $name ),
				'what_we_collect' => __( "We'd like to understand how effective it is on a global scale.", 'wp-simple-firewall' ),
				'data_anon'       => __( 'The data sent is always completely anonymous and we can never track you or your site.', 'wp-simple-firewall' ),
				'can_turn_off'    => __( 'It can be turned-off at any time within the plugin options.', 'wp-simple-firewall' ),
				'click_to_see'    => __( 'Click to see the RAW data that would be sent', 'wp-simple-firewall' ),
				'learn_more'      => __( 'Learn More.', 'wp-simple-firewall' ),
				'site_url'        => 'translate.fernleafsystems.com',
				'yes'             => __( 'Absolutely', 'wp-simple-firewall' ),
				'yes_i_share'     => __( "Yes, I'd be happy share this info", 'wp-simple-firewall' ),
				'hmm_learn_more'  => __( "I'd like to learn more, please", 'wp-simple-firewall' ),
				'no_help'         => __( "No, I don't want to help", 'wp-simple-firewall' ),
			],
			'ajax'              => [
				'set_plugin_tracking' => $mod->getAjaxActionData( 'set_plugin_tracking', true ),
			],
			'hrefs'             => [
				'learn_more'       => 'https://translate.fernleafsystems.com',
				'link_to_see'      => $mod->getLinkToTrackingDataDump(),
				'link_to_moreinfo' => 'https://shsec.io/shieldtrackinginfo',

			]
		];
	}

	private function buildNotice_RatePlugin( NoticeVO $notice ) {
		$notice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'   => __( 'Can You Help Us With A Quick Review?', 'wp-simple-firewall' ),
				'dismiss' => __( "I'd rather not show this support", 'wp-simple-firewall' ).' / '.__( "I've done this already", 'wp-simple-firewall' ).' :D',
			],
			'hrefs'             => [
				'forums' => 'https://wordpress.org/support/plugin/wp-simple-firewall',
			]
		];
	}

	protected function isDisplayNeeded( NoticeVO $notice ) :bool {
		$con = $this->getCon();
		/** @var Options $oOpts */
		$oOpts = $this->getOptions();

		switch ( $notice->id ) {

			case 'plugin-too-old':
				$needed = $this->isNeeded_PluginTooOld();
				break;

			case 'override-forceoff':
				$needed = $con->getIfForceOffActive();
				break;

			case 'php7':
				$needed = !Services::Data()->getPhpVersionIsAtLeast( '7.0' );
				break;

			case 'plugin-disabled':
				$needed = $oOpts->isPluginGloballyDisabled();
				break;

			case 'update-available':
				$needed = Services::WpPlugins()->isUpdateAvailable( $con->base_file );
				break;

			case 'compat-sgoptimize':
				$needed = ( new Plugin\Components\SiteGroundPluginCompatibility() )->testIsIncompatible();
				break;

			case 'allow-tracking':
				$needed = !$oOpts->isTrackingPermissionSet();
				break;

			default:
				$needed = parent::isDisplayNeeded( $notice );
				break;
		}
		return $needed;
	}

	private function isNeeded_PluginTooOld() :bool {
		$needed = false;
		$con = $this->getCon();
		if ( Services::WpPlugins()->isUpdateAvailable( $con->base_file ) ) {
			$versions = Transient::Get( $con->prefix( 'releases' ) );
			if ( !is_array( $versions ) ) {
				$versions = ( new Shield\Utilities\Github\ListTags() )->run( 'FernleafSystems/Shield-Security-for-WordPress' );
				Transient::Set( $con->prefix( 'releases' ), $versions, WEEK_IN_SECONDS );
			}
			array_splice( $versions, array_search( $con->getVersion(), $versions ) );
			$needed = count( array_unique( array_map( function ( $version ) {
					return substr( $version, 0, strrpos( $version, '.' ) );
				}, $versions ) ) ) > 2;
		}
		return $needed;
	}
}