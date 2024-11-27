<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginDumpTelemetry;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginSetTracking;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\AdminNotice;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginNavs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Zone\Secadmin;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Users\UserMeta;

class Controller {

	use ExecOnce;
	use PluginControllerConsumer;

	private $count = 0;

	protected function run() {
		if ( self::con()->getIsPage_PluginAdmin() ) {
			remove_all_filters( 'admin_notices' );
			remove_all_filters( 'network_admin_notices' );
		}
		add_action( 'admin_notices', [ $this, 'onWpAdminNotices' ] );
		add_action( 'network_admin_notices', [ $this, 'onWpNetworkAdminNotices' ] );
		add_filter( 'login_message', [ $this, 'onLoginMessage' ] );
	}

	/**
	 * TODO doesn't handle error message highlighting
	 * @param string $loginMsg
	 * @return string
	 */
	public function onLoginMessage( $loginMsg ) {
		$msg = $this->retrieveFlashMessage();
		if ( \is_array( $msg ) && isset( $msg[ 'show_login' ] ) && $msg[ 'show_login' ] ) {
			$loginMsg .= \sprintf( '<p class="message">%s</p>', sanitize_text_field( $msg[ 'message' ] ) );
			$this->clearFlashMessage();
		}
		return $loginMsg;
	}

	/**
	 * @param \WP_User|null $user
	 * @param bool          $isError
	 * @param bool          $bShowOnLoginPage
	 * @return $this
	 */
	public function addFlash( string $msg, $user = null, $isError = false, $bShowOnLoginPage = false ) {
		$con = self::con();
		$meta = $user instanceof \WP_User ? $con->user_metas->for( $user ) : $con->user_metas->current();

		$msg = \trim( sanitize_text_field( $msg ) );
		if ( !empty( $msg ) && $meta instanceof UserMeta ) {
			$meta->flash_msg = [
				'message'    => sprintf( '[%s] %s', $con->labels->Name, $msg ),
				'expires_at' => Services::Request()->ts() + 20,
				'error'      => $isError,
				'show_login' => $bShowOnLoginPage,
			];
		}
		return $this;
	}

	public function onWpAdminNotices() {
		$this->displayNotices();
	}

	public function onWpNetworkAdminNotices() {
		$this->displayNotices();
	}

	protected function displayNotices() {
		foreach ( $this->buildNotices() as $notice ) {
			echo self::con()->action_router->render( AdminNotice::SLUG, [
				'raw_notice_data' => $notice->getRawData()
			] );
		}
	}

	private function getFlashNotice() :?NoticeVO {
		$notice = null;
		$msg = $this->retrieveFlashMessage();
		if ( \is_array( $msg ) ) {
			$notice = new NoticeVO();
			$notice->type = $msg[ 'error' ] ? 'error' : 'updated';
			$notice->render_data = [
				'notice_classes' => [
					'flash',
					$notice->type
				],
				'message'        => sanitize_text_field( $msg[ 'message' ] ),
			];
			$notice->template = '/notices/flash-message.twig';
			$notice->display = true;
			$this->clearFlashMessage();
		}
		return $notice;
	}

	private function retrieveFlashMessage() :?array {
		$msg = null;
		$meta = self::con()->user_metas->current();
		if ( !empty( $meta ) && \is_array( $meta->flash_msg ) ) {
			if ( empty( $meta->flash_msg[ 'expires_at' ] ) || Services::Request()
																	  ->ts() < $meta->flash_msg[ 'expires_at' ] ) {
				$msg = $meta->flash_msg;
			}
			else {
				$this->clearFlashMessage();
			}
		}
		return $msg;
	}

	private function clearFlashMessage() :self {
		$meta = self::con()->user_metas->current();
		if ( !empty( $meta ) ) {
			$meta->flash_msg = null;
		}
		return $this;
	}

	/**
	 * @return NoticeVO[]
	 */
	private function buildNotices() :array {
		$notices = \array_filter(
			\array_map(
				function ( NoticeVO $notice ) {
					$this->preProcessNotice( $notice );
					if ( $notice->display ) {
						try {
							$this->processNotice( $notice );
						}
						catch ( \Exception $e ) {
						}
					}
					return $notice;
				},
				$this->getAdminNotices()
			),
			function ( NoticeVO $notice ) {
				return $notice->display;
			}
		);

		$notices[] = $this->getFlashNotice();

		return \array_filter( $notices );
	}

	/**
	 * @return NoticeVO[]
	 */
	public function getAdminNotices() :array {
		return \array_map(
			function ( $noticeDef ) {
				$noticeDef = Services::DataManipulation()->mergeArraysRecursive( [
					'schedule'         => 'conditions',
					'type'             => 'promo',
					'plugin_page_only' => true,
					'valid_admin'      => true,
					'plugin_admin'     => 'yes',
					'can_dismiss'      => true,
					'per_user'         => false,
					'display'          => false,
					'min_install_days' => 0,
					'twig'             => true,
				], $noticeDef );
				return ( new NoticeVO() )->applyFromArray( $noticeDef );
			},
			self::con()->cfg->configuration->admin_notices
		);
	}

	protected function preProcessNotice( NoticeVO $notice ) {
		$con = self::con();

		$installedAt = $con->comps->opts_lookup->getInstalledAt();
		if ( !empty( $installedAt ) ) {
			$installDays = (int)\round( ( Services::Request()->ts() - $installedAt )/\DAY_IN_SECONDS );

			if ( $notice->plugin_page_only && !$con->isPluginAdminPageRequest() ) {
				$notice->non_display_reason = 'plugin_page_only';
			}
			elseif ( $notice->type == 'promo' && !self::con()->opts->optIs( 'enable_upgrade_admin_notice', 'Y' ) ) {
				$notice->non_display_reason = 'promo_hidden';
			}
			elseif ( $notice->valid_admin && !$con->isValidAdminArea() ) {
				$notice->non_display_reason = 'not_admin_area';
			}
			elseif ( $notice->plugin_admin == 'yes' && !$con->isPluginAdmin() ) {
				$notice->non_display_reason = 'not_plugin_admin';
			}
			elseif ( $notice->plugin_admin == 'no' && $con->isPluginAdmin() ) {
				$notice->non_display_reason = 'is_plugin_admin';
			}
			elseif ( $notice->min_install_days > 0 && $notice->min_install_days > $installDays ) {
				$notice->non_display_reason = 'min_install_days';
			}
			elseif ( $this->count > 0 && $notice->type !== 'error' ) {
				$notice->non_display_reason = 'max_nonerror_count';
			}
			elseif ( $notice->can_dismiss && $this->isNoticeDismissed( $notice ) ) {
				$notice->non_display_reason = 'dismissed';
			}
			elseif ( !$this->isDisplayNeeded( $notice ) ) {
				$notice->non_display_reason = 'not_needed';
			}
			else {
				$this->count++;
				$notice->display = true;
				$notice->non_display_reason = 'n/a';
			}

			$notice->template = '/notices/'.$notice->id;
		}
	}

	private function isNoticeDismissed( NoticeVO $notice ) :bool {
		$dismissedUser = $this->isNoticeDismissedForCurrentUser( $notice );

		$at = ( $this->getDismissed()[ $notice->id ] ?? 0 ) > 0;

		if ( !$notice->per_user && $dismissedUser && !$at ) {
			$this->setNoticeDismissed( $notice );
		}

		return $dismissedUser || $at;
	}

	private function isNoticeDismissedForCurrentUser( NoticeVO $notice ) :bool {
		$dismissed = false;

		$meta = self::con()->user_metas->current();
		if ( !empty( $meta ) ) {
			$noticeMetaKey = $this->getNoticeMetaKey( $notice );

			if ( isset( $meta->{$noticeMetaKey} ) ) {
				$dismissed = true;

				// migrate from old-style array storage to plain Timestamp
				if ( \is_array( $meta->{$noticeMetaKey} ) ) {
					$meta->{$noticeMetaKey} = $meta->{$noticeMetaKey}[ 'time' ];
				}
			}
		}

		return $dismissed;
	}

	public function setNoticeDismissed( NoticeVO $notice ) {
		$meta = self::con()->user_metas->current();
		$noticeMetaKey = $this->getNoticeMetaKey( $notice );

		if ( $notice->per_user ) {
			if ( !empty( $meta ) ) {
				$meta->{$noticeMetaKey} = Services::Request()->ts();
			}
		}
		else {
			$allDismissed = $this->getDismissed();
			$allDismissed[ $notice->id ] = Services::Request()->ts();
			self::con()->opts->optSet( 'dismissed_notices', $allDismissed );

			// Clear out any old
			if ( !empty( $meta ) ) {
				unset( $meta->{$noticeMetaKey} );
			}
		}
	}

	/**
	 * @return string[]
	 */
	public function getDismissed() :array {
		return self::con()->opts->optGet( 'dismissed_notices' );
	}

	private function getNoticeMetaKey( NoticeVO $notice ) :string {
		return 'notice_'.\str_replace( [ '-', '_' ], '', $notice->id );
	}

	/**
	 * @throws \Exception
	 */
	private function processNotice( NoticeVO $notice ) :void {
		switch ( $notice->id ) {
			case 'blockdown-active':
				$this->buildNotice_SiteLockdownActive( $notice );
				break;
			case 'override-forceoff':
				$this->buildNotice_OverrideForceoff( $notice );
				break;
			case 'allow-tracking':
				$this->buildNotice_AllowTracking( $notice );
				break;
			case 'rate-plugin':
				$this->buildNotice_RatePlugin( $notice );
				break;
			case 'email-verification-sent':
				$this->buildNotice_EmailVerificationSent( $notice );
				break;
			case 'admin-users-restricted':
				$this->buildNotice_AdminUsersRestricted( $notice );
				break;
			case 'certain-options-restricted':
				$this->buildNotice_CertainOptionsRestricted( $notice );
				break;
			default:
				throw new \Exception( 'Unsupported Notice ID: '.$notice->id );
		}
	}

	private function buildNotice_OverrideForceoff( NoticeVO $notice ) {
		$name = self::con()->labels->Name;
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
		];
	}

	private function buildNotice_SiteLockdownActive( NoticeVO $notice ) {
		$notice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'     => sprintf( '%s: %s', __( 'Warning', 'wp-simple-firewall' ), __( 'Site In Lockdown', 'wp-simple-firewall' ) ),
				'message'   => __( 'All access to your site is blocked.', 'wp-simple-firewall' ),
				'configure' => __( 'Configure lockdown', 'wp-simple-firewall' ),

			],
			'hrefs'             => [
				'configure' => self::con()->plugin_urls->adminTopNav( PluginNavs::NAV_TOOLS, PluginNavs::SUBNAV_TOOLS_BLOCKDOWN )
			],
		];
	}

	private function buildNotice_AllowTracking( NoticeVO $notice ) {
		$name = self::con()->labels->Name;

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
				'set_plugin_tracking' => ActionData::BuildJson( PluginSetTracking::class ),
			],
			'hrefs'             => [
				'learn_more'       => 'https://translate.fernleafsystems.com',
				'link_to_see'      => self::con()->plugin_urls->noncedPluginAction( PluginDumpTelemetry::class ),
				'link_to_moreinfo' => 'https://clk.shldscrty.com/shieldtrackinginfo',
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

	private function buildNotice_EmailVerificationSent( NoticeVO $notice ) {
		$notice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'             => self::con()->labels->Name
									   .': '.__( 'Please verify email has been received', 'wp-simple-firewall' ),
				'need_you_confirm'  => __( "Before we can activate email 2-factor authentication, we need you to confirm your website can send emails.", 'wp-simple-firewall' ),
				'please_click_link' => __( "Please click the link in the email you received.", 'wp-simple-firewall' ),
				'email_sent_to'     => sprintf(
					__( "The email has been sent to you at blog admin address: %s", 'wp-simple-firewall' ),
					get_bloginfo( 'admin_email' )
				),
				'how_resend_email'  => __( "Resend verification email", 'wp-simple-firewall' ),
				'how_turn_off'      => __( "Disable 2FA by email", 'wp-simple-firewall' ),
			],
		];
	}

	private function buildNotice_CertainOptionsRestricted( NoticeVO $notice ) {
		$con = self::con();
		$notice->render_data = [
			'notice_attributes' => [],
			'strings'           => [
				'title'          => sprintf( __( '%s Security Restrictions Applied', 'wp-simple-firewall' ), $con->labels->Name ),
				'notice_message' => __( 'Altering certain options has been restricted by your WordPress security administrator.', 'wp-simple-firewall' )
									.' '.__( 'Repeated failed attempts to authenticate will probably lock you out of this site.', 'wp-simple-firewall' )
			],
			'hrefs'             => [
				'setting_page' => sprintf(
					'<a href="%s" title="%s">%s</a>',
					$con->plugin_urls->zone( Secadmin::Slug() ),
					__( 'Admin Access Login', 'wp-simple-firewall' ),
					sprintf( __( 'Go here to manage settings and authenticate with the %s plugin.', 'wp-simple-firewall' ), $con->labels->Name )
				)
			]
		];
	}

	private function buildNotice_AdminUsersRestricted( NoticeVO $notice ) {
		$con = self::con();
		$notice->render_data = [
			'notice_attributes' => [], // TODO
			'strings'           => [
				'title'          => sprintf( __( '%s Security Restrictions Applied', 'wp-simple-firewall' ), $con->labels->Name ),
				'notice_message' => __( 'Editing existing administrators, promoting existing users to the administrator role, or deleting administrator users is currently restricted.', 'wp-simple-firewall' )
									.' '.__( 'Please authenticate with the Security Admin system before attempting any administrator user modifications.', 'wp-simple-firewall' ),
				'unlock_link'    => sprintf(
					'<a href="%1$s" title="%2$s" class="thickbox">%3$s</a>',
					'#TB_inline?width=400&height=180&inlineId=WpsfAdminAccessLogin',
					__( 'Security Admin Login', 'wp-simple-firewall' ),
					__( 'Unlock Now', 'wp-simple-firewall' )
				),
			],
			'hrefs'             => [
				'setting_page' => sprintf(
					'<a href="%s" title="%s">%s</a>',
					$con->plugin_urls->zone( Secadmin::Slug() ),
					__( 'Security Admin Login', 'wp-simple-firewall' ),
					sprintf( __( 'Go here to manage settings and authenticate with the %s plugin.', 'wp-simple-firewall' ), $con->labels->Name )
				)
			]
		];
	}

	protected function isDisplayNeeded( NoticeVO $notice ) :bool {
		$con = self::con();
		switch ( $notice->id ) {
			case 'override-forceoff':
				$needed = $con->this_req->is_force_off && !$con->isPluginAdminPageRequest();
				break;
			case 'allow-tracking':
				$needed = $con->opts->optGet( 'tracking_permission_set_at' ) === 0;
				break;
			case 'blockdown-active':
				$needed = $con->this_req->is_site_lockdown_active && !$con->isPluginAdminPageRequest();
				break;
			case 'email-verification-sent':
				$needed = $con->opts->optIs( 'enable_email_authentication', 'Y' )
						  && $con->opts->optGet( 'email_can_send_verified_at' ) < 1;
				break;
			case 'admin-users-restricted':
				$needed = \in_array( Services::WpPost()
											 ->getCurrentPage(), self::con()->cfg->configuration->def( 'restricted_pages_users' ) );
				break;
			case 'certain-options-restricted':
				$def = $con->cfg->configuration->def( 'options_to_restrict' );
				$restricted = $def[ ( Services::WpGeneral()->isMultisite() ? 'wpms' : 'wp' ).'_pages' ] ?? [];
				$needed = empty( Services::Request()->query( 'page' ) )
						  && \in_array( Services::WpPost()->getCurrentPage(), $restricted );
				break;
			default:
				$needed = false;
				break;
		}
		return $needed;
	}
}