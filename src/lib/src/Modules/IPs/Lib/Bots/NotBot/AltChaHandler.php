<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\NotBot;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class AltChaHandler {

	use PluginControllerConsumer;

	/**
	 * Very basic adaptive complexity for now. More to come.
	 */
	public function complexityLevel() :string {
		$opt = self::con()->opts->optGet( 'silentcaptcha_complexity' );
		if ( $opt === 'adaptive' ) {
			$opt = wp_is_mobile() ? 'medium' : 'high';
		}
		return $opt;
	}

	public function enabled() :bool {
		return !\in_array( $this->complexityLevel(), [ 'none', 'legacy' ] ) && $this->reqsMet();
	}

	/**
	 * @throws \Exception
	 */
	private function hmacKey() :string {
		$key = wp_salt( 'shield-altcha' );
		if ( empty( $key ) ) {
			throw new \Exception( "Can't generate HMAC Key" );
		}
		return $key;
	}

	public function reqsMet() :bool {
		try {
			$this->hmacKey();
			$met = true;
		}
		catch ( \Exception $e ) {
			$met = false;
		}
		return $met;
	}

	/**
	 * https://altcha.org/docs/server-integration/
	 * @throws \Exception
	 */
	public function verifySolution( string $algo, string $salt, string $challenge, string $signature, string $number, int $expires ) :bool {
		if ( $algo !== 'SHA-256' ) {
			throw new \Exception( 'Algorithm not supported' );
		}
		if ( $expires < Services::Request()->ts() ) {
			throw new \Exception( 'Challenge has expired.' );
		}
		// challenge_ok = equals(data.challenge, sha2_hex(concat(data.salt, data.number)))
		if ( !\hash_equals( \hash( 'sha256', $salt.$number ), $challenge ) ) {
			throw new \Exception( 'Challenge Failed' );
		}
		// signature_ok = equals(data.signature, hmac_sha2_hex(data.challenge, hmac_key))
		if ( !\hash_equals( \hash_hmac( 'sha256', $challenge, $this->hmacKey() ), $signature ) ) {
			throw new \Exception( 'Signature Failed' );
		}
		return true;
	}

	/**
	 * @throws \Exception
	 */
	public function generateChallenge() :array {
		$this->hmacKey();
		switch ( $this->complexityLevel() ) {
			case 'high':
				$secretMin = 10000;
				$secretMax = 100000;
				break;
			case 'low':
				$secretMin = 100;
				$secretMax = 1000;
				break;
			case 'medium':
			default:
				$secretMin = 1000;
				$secretMax = 20000;
				break;
		}
		$expires = Services::Request()->ts() + MINUTE_IN_SECONDS*5;
		$salt = \bin2hex( \random_bytes( 12 ) ).$expires;
		$challenge = \hash( 'sha256', $salt.\random_int( $secretMin, $secretMax ) );
		$signature = \hash_hmac( 'sha256', $challenge, $this->hmacKey() );
		return [
			'algorithm' => 'SHA-256',
			'challenge' => $challenge,
			'maxnumber' => $secretMax,
			'salt'      => $salt,
			'signature' => $signature,
			'expires'   => $expires,
		];
	}
}