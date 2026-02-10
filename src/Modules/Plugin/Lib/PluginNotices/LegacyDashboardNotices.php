<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginNotices;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\PluginDumpTelemetry;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\AdminNotices\NoticeVO;
use FernleafSystems\Wordpress\Services\Services;

class LegacyDashboardNotices extends Base {

	/** @var NoticeVO[] */
	private ?array $noticeDefs = null;

	public function check() :?array {
		$con = self::con();
		$installedAt = $con->comps->opts_lookup->getInstalledAt();
		if ( empty( $installedAt ) ) {
			return null;
		}

		$installDays = (int)\round( ( Services::Request()->ts() - $installedAt )/\DAY_IN_SECONDS );

		foreach ( [
			'allow-tracking',
			'rate-plugin',
			'email-verification-sent',
		] as $noticeID ) {

			$notice = $this->getNoticeDef( $noticeID );
			if ( !$notice instanceof NoticeVO ) {
				continue;
			}

			if ( !$this->isAllowedByLegacyVisibility( $notice, $installDays ) ) {
				continue;
			}

			if ( $notice->can_dismiss && $this->isDismissed( $notice->id ) ) {
				continue;
			}

			if ( !$this->isNeeded( $notice->id ) ) {
				continue;
			}

			return $this->buildPayload( $notice );
		}

		return null;
	}

	private function isDismissed( string $noticeID ) :bool {
		return ( self::con()->admin_notices->getDismissed()[ $noticeID ] ?? 0 ) > 0;
	}

	private function isNeeded( string $noticeID ) :bool {
		$con = self::con();
		switch ( $noticeID ) {
			case 'allow-tracking':
				$needed = $con->opts->optGet( 'tracking_permission_set_at' ) === 0;
				break;
			case 'rate-plugin':
				// Legacy parity: this notice currently has no display condition and is effectively disabled.
				$needed = false;
				break;
			case 'email-verification-sent':
				$needed = $con->opts->optIs( 'enable_email_authentication', 'Y' )
						  && $con->opts->optGet( 'email_can_send_verified_at' ) < 1;
				break;
			default:
				$needed = false;
				break;
		}
		return $needed;
	}

	private function buildPayload( NoticeVO $notice ) :array {
		switch ( $notice->id ) {
			case 'allow-tracking':
				return $this->buildAllowTrackingPayload( $notice );
			case 'rate-plugin':
				return $this->buildRatePluginPayload( $notice );
			case 'email-verification-sent':
				return $this->buildEmailVerificationPayload( $notice );
			default:
				return [];
		}
	}

	private function buildAllowTrackingPayload( NoticeVO $notice ) :array {
		$con = self::con();
		$name = $con->labels->Name;

		return [
			'id'          => $notice->id,
			'type'        => $this->normaliseNoticeType( (string)$notice->type ),
			'text'        => [
				sprintf(
					'<strong>%s</strong> %s %s',
					sprintf( __( "Make %s even better by sharing usage info?", 'wp-simple-firewall' ), $name ),
					sprintf( __( "We're hoping to understand how %s is configured and used.", 'wp-simple-firewall' ), $name ),
					__( "We'd like to understand how effective it is on a global scale.", 'wp-simple-firewall' )
				),
				sprintf(
					'%s %s <a target="_blank" href="%s">%s</a>',
					__( 'The data sent is always completely anonymous and we can never track you or your site.', 'wp-simple-firewall' ),
					__( 'It can be turned-off at any time within the plugin options.', 'wp-simple-firewall' ),
					$con->plugin_urls->noncedPluginAction( PluginDumpTelemetry::class ),
					__( 'Click to see the RAW data that would be sent', 'wp-simple-firewall' )
				),
				sprintf(
					'<a href="#" class="button button-primary me-3" id="icwpButtonPluginTrackingAgree">%s</a> <a target="_blank" href="%s" class="button me-3" id="icwpButtonPluginTrackingMore">%s</a> <a href="#" id="icwpButtonPluginTrackingDisagree">%s</a>',
					__( "Yes, I'd be happy share this info", 'wp-simple-firewall' ),
					'https://clk.shldscrty.com/shieldtrackinginfo',
					__( "I'd like to learn more, please", 'wp-simple-firewall' ),
					__( "No, I don't want to help", 'wp-simple-firewall' )
				),
			],
			'locations'   => [
				'shield_admin_top_page',
			],
			'can_dismiss' => (bool)$notice->can_dismiss,
		];
	}

	private function buildRatePluginPayload( NoticeVO $notice ) :array {
		return [
			'id'          => $notice->id,
			'type'        => $this->normaliseNoticeType( (string)$notice->type ),
			'text'        => [
				sprintf(
					'<strong>%s</strong> %s',
					__( 'Can You Help Us With A Quick Review?', 'wp-simple-firewall' ),
					sprintf( __( 'A lot of work goes into %s, and we need your help to spread the word about it. :)', 'wp-simple-firewall' ), self::con()->labels->Name )
				),
				__( "Even just a 1-liner review that says you're happy with it, will help us immensely.", 'wp-simple-firewall' ),
				sprintf(
					'<a href="%s" class="button button-primary" target="_blank">%s</a>',
					'https://clk.shldscrty.com/wpsfreview',
					__( 'Click to leave a review on WordPress.org ->', 'wp-simple-firewall' )
				),
			],
			'locations'   => [
				'shield_admin_top_page',
			],
			'can_dismiss' => (bool)$notice->can_dismiss,
		];
	}

	private function buildEmailVerificationPayload( NoticeVO $notice ) :array {
		return [
			'id'          => $notice->id,
			'type'        => $this->normaliseNoticeType( (string)$notice->type ),
			'text'        => [
				sprintf(
					'<strong>%s</strong> %s %s %s',
					sprintf( '%s: %s', self::con()->labels->Name, __( 'Please verify email has been received', 'wp-simple-firewall' ) ),
					__( "Before we can activate email 2-factor authentication, we need you to confirm your website can send emails.", 'wp-simple-firewall' ),
					__( 'Please click the link in the email you received.', 'wp-simple-firewall' ),
					sprintf( __( 'The email has been sent to you at blog admin address: %s', 'wp-simple-firewall' ), get_bloginfo( 'admin_email' ) )
				),
				sprintf(
					'<a href="#" class="shield_admin_notice_action" data-notice_action="resend_verification_email">%s</a> / <a href="#" class="shield_admin_notice_action" data-notice_action="mfa_email_disable">%s</a>',
					__( 'Resend verification email', 'wp-simple-firewall' ),
					__( 'Disable 2FA by email', 'wp-simple-firewall' )
				)
			],
			'locations'   => [
				'shield_admin_top_page',
			],
			'can_dismiss' => (bool)$notice->can_dismiss,
		];
	}

	private function normaliseNoticeType( string $type ) :string {
		switch ( $type ) {
			case 'promo':
				$normalised = 'info';
				break;
			case 'error':
				$normalised = 'danger';
				break;
			default:
				$normalised = $type;
				break;
		}
		return $normalised;
	}

	private function isAllowedByLegacyVisibility( NoticeVO $notice, int $installDays ) :bool {
		$con = self::con();
		if ( $notice->plugin_page_only && !$con->isPluginAdminPageRequest() ) {
			$allowed = false;
		}
		elseif ( $notice->type == 'promo' && !self::con()->opts->optIs( 'enable_upgrade_admin_notice', 'Y' ) ) {
			$allowed = false;
		}
		elseif ( $notice->valid_admin && !$con->isValidAdminArea() ) {
			$allowed = false;
		}
		elseif ( $notice->plugin_admin == 'yes' && !$con->isPluginAdmin() ) {
			$allowed = false;
		}
		elseif ( $notice->plugin_admin == 'no' && $con->isPluginAdmin() ) {
			$allowed = false;
		}
		elseif ( $notice->min_install_days > 0 && $notice->min_install_days > $installDays ) {
			$allowed = false;
		}
		else {
			$allowed = true;
		}
		return $allowed;
	}

	private function getNoticeDef( string $noticeID ) :?NoticeVO {
		return $this->getNoticeDefs()[ $noticeID ] ?? null;
	}

	/**
	 * @return NoticeVO[]
	 */
	private function getNoticeDefs() :array {
		if ( \is_array( $this->noticeDefs ) ) {
			return $this->noticeDefs;
		}

		$this->noticeDefs = [];
		foreach ( self::con()->admin_notices->getAdminNotices() as $notice ) {
			$this->noticeDefs[ $notice->id ] = $notice;
		}

		return $this->noticeDefs;
	}
}
