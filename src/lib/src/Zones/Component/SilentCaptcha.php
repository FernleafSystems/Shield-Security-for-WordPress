<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class SilentCaptcha extends Base {

	public function title() :string {
		return __( 'silentCAPTCHA', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return sprintf( __( "silentCAPTCHA is %s's exclusive WordPress Bad Bot Detection technology.", 'wp-simple-firewall' ),
			self::con()->labels->Name );
	}

	protected function tooltip() :string {
		return __( 'Edit settings that control how silentCAPTCHA detects bots', 'wp-simple-firewall' );
	}

	/**
	 * @return array{level:string,expl:string[]}
	 */
	protected function status() :array {
		$con = self::con();
		$complexity = $con->comps->altcha->complexityLevel();
		$minimum = $con->opts->optGet( 'antibot_minimum' );

		$status = [
			'exp' => [],
		];
		if ( $complexity === 'none' ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( "silentCAPTCHA's bot detection isn't running, as bots aren't currently being challenged.", 'wp-simple-firewall' );
		}
		elseif ( \in_array( $complexity, [ 'legacy', 'low' ] ) ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( "silentCAPTCHA's Bot challenge complexity is too low - consider increasing it.", 'wp-simple-firewall' );
		}

		if ( $minimum === 0 ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( "silentCAPTCHA's bot detection isn't running because the minimum score is set to 0.", 'wp-simple-firewall' );
		}
		elseif ( $minimum < 30 ) {
			$status[ 'exp' ][] = sprintf( __( "silentCAPTCHA's bot minimum score is quite low (%s).", 'wp-simple-firewall' ), '< 30' );
			if ( empty( $status[ 'level' ] ) ) {
				$status[ 'level' ] = EnumEnabledStatus::OKAY;
			}
		}

		if ( empty( $status[ 'level' ] ) ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		return $status;
	}
}