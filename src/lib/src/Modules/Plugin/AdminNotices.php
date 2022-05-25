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

			case 'databases-not-ready':
				$this->buildNotice_DatabasesNotReady( $notice );
				break;

			case 'rules-not-running':
				$this->buildNotice_RulesNotRunning( $notice );
				break;

			case 'plugin-too-old':
				$this->buildNotice_PluginTooOld( $notice );
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

	private function buildNotice_DatabasesNotReady( NoticeVO $notice ) {
		$name = $this->getCon()->getHumanName();

		$notice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'        => sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ),
					sprintf( __( "%s Databases May Need To Be Repaired", 'wp-simple-firewall' ), $name ) ),
				'lines'        => [
					__( 'To save you manual work, the plugin tries to manage its database tables automatically for you. But sometimes the automated process may run into trouble.', 'wp-simple-firewall' ),
					__( "If this message persists for more than ~30 seconds, please use the link below to repair the plugin's database tables.", 'wp-simple-firewall' )
					.' '.__( "This will result in a loss of all activity and traffic logs.", 'wp-simple-firewall' )
				],
				'click_repair' => __( 'Click here to repair the database tables', 'wp-simple-firewall' )
			],
			'ajax'              => [
				'auto_db_repair' => $this->getMod()->getAjaxActionData( 'auto_db_repair', true )
			]
		];
	}

	private function buildNotice_RulesNotRunning( NoticeVO $notice ) {
		$name = $this->getCon()->getHumanName();

		$notice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title' => sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ),
					sprintf( __( "%s's Rules Engine Isn't Running", 'wp-simple-firewall' ), $name ) ),
				'lines' => [
					sprintf(
						__( "The Rules Engine that processes requests and protects your site doesn't appear to be operating normally.", 'wp-simple-firewall' ),
						$name
					),
					__( "This could be a webhosting configuration issue, but please reach out to our support desk for help to isolate the issue.", 'wp-simple-firewall' ),
				],
			],
		];
	}

	private function buildNotice_PluginTooOld( NoticeVO $notice ) {
		$name = $this->getCon()->getHumanName();

		$notice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'        => sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ),
					sprintf( __( "%s Plugin Is Too Old", 'wp-simple-firewall' ), $name ) ),
				'lines'        => [
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
										 .' '.'The problematic options are: "Defer Render-blocking JS" and "Remove Query Strings From Static Resources".',
				'learn_more'          => 'Click here to learn more',
				'sgoptimizer_turnoff' => __( 'Click here to automatically turn off those options.', 'wp-simple-firewall' )
			],
			'ajax'              => [
				'sgoptimizer_turnoff' => $this->getMod()->getAjaxActionData( 'sgoptimizer_turnoff', true )
			]
		];
	}

	private function buildNotice_PluginMailingListSignup( NoticeVO $notice ) {
		/** @var Options $opts */
		$opts = $this->getOptions();

		$name = $this->getCon()->getHumanName();
		$user = Services::WpUsers()->getCurrentWpUser();

		$notice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'yes'     => "Yes please! I'd love to join in and learn more",
				'dismiss' => "No thanks",
				'summary' => sprintf( 'The %s team is helping raise awareness of WP Security issues
				and to provide guidance with the %s plugin.', $name, $name ),
			],
			'hrefs'             => [
				'form' => 'https://shsec.io/shieldpluginnewsletter'
			],
			'install_days'      => $opts->getInstallationDays(),
			'vars'              => [
				'name'       => $user->first_name,
				'user_email' => $user->user_email,
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
				'launch'  => sprintf( __( "Launch the welcome wizard", 'wp-simple-firewall' ), $name ),
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
		/** @var Options $opts */
		$opts = $this->getOptions();

		switch ( $notice->id ) {

			case 'databases-not-ready':
				$needed = $this->isNeeded_DatabasesNotReady();
				break;

			case 'wizard_welcome':
				$needed = false;
				break;

			case 'rules-not-running':
				$needed = $this->isNeeded_RulesNotRunning();
				break;

			case 'plugin-too-old':
				$needed = $this->isNeeded_PluginTooOld();
				break;

			case 'override-forceoff':
				$needed = $con->this_req->is_force_off;
				break;

			case 'plugin-disabled':
				$needed = $opts->isPluginGloballyDisabled();
				break;

			case 'update-available':
				$needed = Services::WpPlugins()->isUpdateAvailable( $con->base_file );
				break;

			case 'compat-sgoptimize':
				$needed = ( new Plugin\Components\SiteGroundPluginCompatibility() )->testIsIncompatible();
				break;

			case 'allow-tracking':
				$needed = !$opts->isTrackingPermissionSet();
				break;

			default:
				$needed = parent::isDisplayNeeded( $notice );
				break;
		}
		return $needed;
	}

	private function isNeeded_DatabasesNotReady() :bool {
		$dbs = $this->getCon()->prechecks[ 'dbs' ];
		return count( $dbs ) !== count( array_filter( $dbs ) );
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

			if ( !empty( $versions ) ) {
				if ( !in_array( $con->getVersion(), $versions ) ) {
					$needed = true;
				}
				else {
					array_splice( $versions, array_search( $con->getVersion(), $versions ) );
					$needed = count( array_unique( array_map( function ( $version ) {
							return substr( $version, 0, strrpos( $version, '.' ) );
						}, $versions ) ) ) > 2;
				}
			}
		}
		return $needed;
	}

	private function isNeeded_RulesNotRunning() :bool {
		$con = $this->getCon();
		return !$con->rules->isRulesEngineReady() || !$con->rules->processComplete;
	}
}