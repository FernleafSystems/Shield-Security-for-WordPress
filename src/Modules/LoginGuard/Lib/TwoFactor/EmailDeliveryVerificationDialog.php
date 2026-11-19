<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\ActionData;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	MfaEmailConfirmDelivery,
	MfaEmailDisable,
	MfaEmailSendVerification
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Obfuscate;

/**
 * The dialog a Security Admin gets on every Shield page while email 2FA is switched on but email delivery isn't
 * verified. It goes away only once delivery is verified (by the emailed link, or confirmed here) or email 2FA is
 * turned off.
 *
 * @phpstan-type Delivery array{tone:'sent'|'expired'|'unsent',icon_class:string,detail:string}
 * @phpstan-type Dialog array{
 *   title:string,description:string,icon_class:string,
 *   address:string,address_source:string,
 *   delivery:Delivery,
 *   strings:array{
 *     resend:string,sending:string,address_source_label:string,skip_summary:string,skip_setting:string,
 *     risk_title:string,risk_text:string,skip_confirm:string,turn_off:string,close:string,error:string
 *   },
 *   actions:array{send:array<string,mixed>,disable:array<string,mixed>,confirm:array<string,mixed>}
 * }
 */
class EmailDeliveryVerificationDialog {

	use PluginControllerConsumer;

	public function isRequired() :bool {
		return self::con()->isPluginAdmin() && ( new EmailDeliveryVerification() )->needsVerificationAction();
	}

	/**
	 * @return Dialog
	 */
	public function build() :array {
		// The fallback WordPress admin email can be blank or malformed. Obfuscation needs an @ and a domain suffix.
		$recipient = self::con()->comps->opts_lookup->getReportEmailDetails();
		$email = $recipient[ 'address' ];
		$separator = \strpos( $email, '@' );
		$canMaskAddress = $separator !== false && \strrpos( $email, '.' ) > $separator;
		return [
			'title'          => __( "Email 2FA isn't on yet", 'wp-simple-firewall' ),
			'description'    => __( 'It turns on when you click the link in the verification email.', 'wp-simple-firewall' ),
			'icon_class'     => 'bi bi-envelope-paper',
			'address'        => $canMaskAddress ? Obfuscate::Email( $email ) : __( 'No email address set', 'wp-simple-firewall' ),
			'address_source' => $recipient[ 'is_site_admin_fallback' ] ?
				__( 'This is the WordPress admin email address, because no Report Email is set in Shield.', 'wp-simple-firewall' )
				: __( "This is Shield's Report Email address.", 'wp-simple-firewall' ),
			'delivery'       => $this->delivery( false ),
			'strings'        => [
				'resend'               => __( 'Resend', 'wp-simple-firewall' ),
				'sending'              => __( 'Sending…', 'wp-simple-firewall' ),
				'address_source_label' => __( 'Where this address comes from', 'wp-simple-firewall' ),
				'skip_summary'         => __( 'Skip verification', 'wp-simple-firewall' ),
				'skip_setting'         => __( "I'm sure this site can send email reliably", 'wp-simple-firewall' ),
				'risk_title'           => __( 'Risk of lockout:', 'wp-simple-firewall' ),
				'risk_text'            => __( "if login codes don't arrive, anyone using email 2FA, including you, can't log in.", 'wp-simple-firewall' ),
				'skip_confirm'         => __( 'Turn on without verifying', 'wp-simple-firewall' ),
				'turn_off'             => __( 'Turn off email 2FA', 'wp-simple-firewall' ),
				'close'                => __( 'Close', 'wp-simple-firewall' ),
				'error'                => __( "That didn't work. Please try again.", 'wp-simple-firewall' ),
			],
			'actions'        => [
				'send'    => ActionData::Build( MfaEmailSendVerification::class ),
				'disable' => ActionData::Build( MfaEmailDisable::class ),
				'confirm' => ActionData::Build( MfaEmailConfirmDelivery::class ),
			],
		];
	}

	/**
	 * Where the verification email stands. A send that has just failed reads as not sent, even when the link from
	 * an earlier send still works, because that earlier email is what the admin is resending.
	 * @return Delivery
	 */
	public function delivery( bool $sendFailed ) :array {
		$verification = new EmailDeliveryVerification();
		$status = $verification->status();

		if ( $sendFailed || $status === EmailDeliveryVerification::STATUS_UNSENT ) {
			$delivery = [
				'tone'       => 'unsent',
				'icon_class' => 'bi bi-envelope-x',
				'detail'     => __( "Not sent. Check your site's email setup.", 'wp-simple-firewall' ),
			];
		}
		elseif ( $status === EmailDeliveryVerification::STATUS_STALE ) {
			$delivery = [
				'tone'       => 'expired',
				'icon_class' => 'bi bi-clock-history',
				'detail'     => __( 'The link has expired.', 'wp-simple-firewall' ),
			];
		}
		else {
			$delivery = [
				'tone'       => 'sent',
				'icon_class' => 'bi bi-send-check',
				'detail'     => sprintf(
					/* translators: %s: how long ago the email was sent, such as "4 minutes ago" */
					__( 'Sent %s. Check spam too.', 'wp-simple-firewall' ),
					Services::Request()->carbon( true )->setTimestamp( $verification->sentAt() )->diffForHumans()
				),
			];
		}

		return $delivery;
	}
}
