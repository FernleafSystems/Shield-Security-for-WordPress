<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

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

	/**
	 * @return array{level:string,expl:string[]}
	 */
	protected function status() :array {
		$con = self::con();
		$silentCaptcha = $con->labels->getBrandName( 'silentcaptcha' );
		$complexity = $con->comps->altcha->complexityLevel();
		$minimum = $con->opts->optGet( 'antibot_minimum' );

		$status = [
			'exp' => [],
		];
		if ( $complexity === 'none' ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = sprintf( __( "%s bot detection isn't running, as bots aren't currently being challenged.", 'wp-simple-firewall' ), $silentCaptcha );
		}
		elseif ( \in_array( $complexity, [ 'legacy', 'low' ] ) ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = sprintf( __( "%s Bot challenge complexity is too low - consider increasing it.", 'wp-simple-firewall' ), $silentCaptcha );
		}

		if ( $minimum === 0 ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = sprintf( __( "%s bot detection isn't running because the minimum score is set to 0.", 'wp-simple-firewall' ), $silentCaptcha );
		}
		elseif ( $minimum < 30 ) {
			$status[ 'exp' ][] = sprintf( __( '%1$s bot minimum score is quite low (%2$s).', 'wp-simple-firewall' ), $silentCaptcha, '< 30' );
			if ( empty( $status[ 'level' ] ) ) {
				$status[ 'level' ] = EnumEnabledStatus::OKAY;
			}
		}

		if ( empty( $status[ 'level' ] ) ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		return $status;
	}

	public function postureSignals() :array {
		$minimum = (int)self::con()->opts->optGet( 'antibot_minimum' );
		$complexity = self::con()->comps->altcha->complexityLevel();
		$thresholdProtected = $minimum > 0;
		$complexityProtected = !\in_array( $complexity, [ 'none', 'legacy', 'low' ], true );

		return [
			$this->buildPostureSignal(
				'ip_ade_threshold',
				self::con()->labels->getBrandName( 'silentcaptcha' ),
				3,
				$thresholdProtected ? 3 : 0,
				$thresholdProtected ? 'good' : 'critical',
				$thresholdProtected,
				[
					$thresholdProtected
						? __( 'A minimum bot-score threshold is configured.', 'wp-simple-firewall' )
						: __( 'No minimum bot-score threshold is configured.', 'wp-simple-firewall' ),
				]
			),
			$this->buildPostureSignal(
				'silentcaptcha_complexity',
				__( 'Bot Challenge Complexity', 'wp-simple-firewall' ),
				2,
				$complexityProtected ? 2 : 0,
				$complexityProtected ? 'good' : 'critical',
				$complexityProtected,
				[
					$complexityProtected
						? __( 'Bot challenge complexity is set above the low/legacy levels.', 'wp-simple-firewall' )
						: __( 'Bot challenge complexity is too low to be considered reliable.', 'wp-simple-firewall' ),
				]
			),
		];
	}
}
