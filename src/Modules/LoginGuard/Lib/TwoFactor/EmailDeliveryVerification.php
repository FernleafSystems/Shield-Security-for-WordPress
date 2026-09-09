<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class EmailDeliveryVerification {

	use PluginControllerConsumer;

	public const STATUS_DISABLED = 'disabled';
	public const STATUS_VERIFIED = 'verified';
	public const STATUS_UNSENT = 'unsent';
	public const STATUS_PENDING = 'pending';
	public const STATUS_STALE = 'stale';

	/**
	 * @return self::STATUS_DISABLED|self::STATUS_VERIFIED|self::STATUS_UNSENT|self::STATUS_PENDING|self::STATUS_STALE
	 */
	public function status() :string {
		$opts = self::con()->opts;

		if ( !$opts->optIs( 'enable_email_authentication', 'Y' ) ) {
			$status = self::STATUS_DISABLED;
		}
		elseif ( $opts->optGet( 'email_can_send_verified_at' ) > 0 ) {
			$status = self::STATUS_VERIFIED;
		}
		elseif ( $this->sentAt() < 1 ) {
			$status = self::STATUS_UNSENT;
		}
		elseif ( $this->isStale() ) {
			$status = self::STATUS_STALE;
		}
		else {
			$status = self::STATUS_PENDING;
		}

		return $status;
	}

	public function needsVerificationSend() :bool {
		return \in_array( $this->status(), [ self::STATUS_UNSENT, self::STATUS_STALE ], true );
	}

	public function needsVerificationAction() :bool {
		return \in_array( $this->status(), [ self::STATUS_UNSENT, self::STATUS_PENDING, self::STATUS_STALE ], true );
	}

	public function markSent() :self {
		self::con()->opts->optSet( 'email_can_send_verification_sent_at', Services::Request()->ts() );
		return $this;
	}

	public function markVerified() :self {
		self::con()->opts
			->optSet( 'email_can_send_verified_at', Services::Request()->ts() )
			->optSet( 'email_can_send_verification_sent_at', 0 );
		return $this;
	}

	public function clearSent() :self {
		self::con()->opts->optSet( 'email_can_send_verification_sent_at', 0 );
		return $this;
	}

	public function sentAt() :int {
		return (int)self::con()->opts->optGet( 'email_can_send_verification_sent_at' );
	}

	private function isStale() :bool {
		return $this->sentAt() + \DAY_IN_SECONDS <= Services::Request()->ts();
	}
}
