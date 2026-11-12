<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\SilentCaptchaComplexity;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class SilentCaptcha extends Base {

	public function title() :string {
		return self::con()->labels->getBrandName( 'silentcaptcha' );
	}

	public function subtitle() :string {
		return sprintf( __( '%s is our WordPress Bad Bot Detection technology.', 'wp-simple-firewall' ), self::con()->labels->getBrandName( 'silentcaptcha' ) );
	}

	protected function tooltip() :string {
		return sprintf( __( 'Edit settings that control how %s detects bots', 'wp-simple-firewall' ), self::con()->labels->getBrandName( 'silentcaptcha' ) );
	}

	protected function configureStatus() :array {
		$state = $this->silentCaptchaState();
		$status = parent::status();

		if ( $state[ 'challenge_disabled' ] ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = $state[ 'challenge_disabled_message' ];
		}
		elseif ( $state[ 'challenge_weak' ] ) {
			$status[ 'exp' ][] = $state[ 'challenge_warning_message' ];
		}

		if ( !$state[ 'threshold_enabled' ] ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = $state[ 'threshold_disabled_message' ];
		}
		elseif ( $state[ 'threshold_weak' ] ) {
			$status[ 'exp' ][] = $state[ 'threshold_warning_message' ];
		}

		if ( $status[ 'level' ] !== EnumEnabledStatus::BAD ) {
			$status[ 'level' ] = empty( $status[ 'exp' ] ) ? EnumEnabledStatus::GOOD : EnumEnabledStatus::OKAY;
		}

		return $status;
	}

	/**
	 * @return array{level:string,exp:string[]}
	 */
	protected function status() :array {
		$state = $this->silentCaptchaState();
		$status = parent::status();

		if ( $state[ 'challenge_disabled' ] ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = $state[ 'challenge_disabled_message' ];
		}
		elseif ( $state[ 'challenge_weak' ] ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = $state[ 'challenge_warning_message' ];
		}

		if ( !$state[ 'threshold_enabled' ] ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = $state[ 'threshold_disabled_message' ];
		}
		elseif ( $state[ 'threshold_weak' ] ) {
			$status[ 'exp' ][] = $state[ 'threshold_warning_message' ];
			if ( empty( $status[ 'level' ] ) ) {
				$status[ 'level' ] = EnumEnabledStatus::OKAY;
			}
		}

		if ( empty( $status[ 'level' ] ) ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		return $status;
	}

	/**
	 * @return array{
	 *   threshold_enabled:bool,
	 *   threshold_weak:bool,
	 *   challenge_disabled:bool,
	 *   challenge_weak:bool,
	 *   threshold_disabled_message:string,
	 *   threshold_warning_message:string,
	 *   challenge_disabled_message:string,
	 *   challenge_warning_message:string
	 * }
	 */
	private function silentCaptchaState() :array {
		$silentCaptcha = self::con()->labels->getBrandName( 'silentcaptcha' );
		$complexity = (string)self::con()->comps->altcha->complexityLevel();
		$minimum = (int)self::con()->opts->optGet( 'antibot_minimum' );

		return [
			'threshold_enabled'          => $minimum > 0,
			'threshold_weak'             => $minimum > 0 && $minimum < 30,
			'challenge_disabled'         => $complexity === SilentCaptchaComplexity::NONE,
			'challenge_weak'             => $complexity === SilentCaptchaComplexity::LOW,
			'threshold_disabled_message' => sprintf( __( "%s bot detection isn't running because the minimum score is set to 0.", 'wp-simple-firewall' ), $silentCaptcha ),
			'threshold_warning_message'  => sprintf( __( '%1$s bot minimum score is quite low (%2$s).', 'wp-simple-firewall' ), $silentCaptcha, '< 30' ),
			'challenge_disabled_message' => sprintf( __( "%s bot detection isn't running, as bots aren't currently being challenged.", 'wp-simple-firewall' ), $silentCaptcha ),
			'challenge_warning_message'  => sprintf( __( "%s bot challenge complexity is too low - consider increasing it.", 'wp-simple-firewall' ), $silentCaptcha ),
		];
	}
}
