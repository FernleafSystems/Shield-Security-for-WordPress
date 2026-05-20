<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\AltCha;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\SilentCaptcha\SilentCaptchaComplexity;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Bots\BotSignalsRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class AltChaHandler {

	use PluginControllerConsumer;

	private const COMPLEXITY_PROFILES = [
		SilentCaptchaComplexity::LOW    => [
			'cost'        => 1000,
			'counter_min' => 400,
			'counter_max' => 1000,
		],
		SilentCaptchaComplexity::MEDIUM => [
			'cost'        => 2500,
			'counter_min' => 1000,
			'counter_max' => 2500,
		],
		SilentCaptchaComplexity::HIGH   => [
			'cost'        => 5000,
			'counter_min' => 2000,
			'counter_max' => 5000,
		],
	];

	/**
	 * Very basic adaptive complexity for now. More to come.
	 */
	public function complexityLevel() :string {
		return SilentCaptchaComplexity::resolve( self::con()->opts->optGet( 'silentcaptcha_complexity' ) );
	}

	public function enabled() :bool {
		return $this->complexityLevel() !== SilentCaptchaComplexity::NONE && $this->reqsMet();
	}

	/**
	 * @throws \Exception
	 */
	private function hmacKey() :string {
		$key = wp_salt( 'shield-altcha' );
		if ( empty( $key ) ) {
			throw new \Exception( __( "Can't generate HMAC Key", 'wp-simple-firewall' ) );
		}
		return $key;
	}

	public function reqsMet() :bool {
		try {
			$this->hmacKey();
			$met = $this->protocol()->requirementsMet();
		}
		catch ( \Exception $e ) {
			$met = false;
		}
		return $met;
	}

	/**
	 * @throws \Exception
	 */
	public function verifySolution( string $challengeJson, string $solutionJson ) :bool {
		$protocol = $this->protocol();
		$hmacKey = $this->hmacKey();

		return $protocol->verifySolution(
			$challengeJson,
			$solutionJson,
			$hmacKey,
			$protocol->keySignatureSecret( $hmacKey ),
			Services::Request()->ts()
		);
	}

	/**
	 * @throws \Exception
	 */
	public function generateChallenge() :array {
		$protocol = $this->protocol();
		$hmacKey = $this->hmacKey();
		$complexity = $this->effectiveComplexityLevel();
		if ( !isset( self::COMPLEXITY_PROFILES[ $complexity ] ) ) {
			throw new \Exception( 'ALTCHA challenge generation is disabled.' );
		}
		$profile = self::COMPLEXITY_PROFILES[ $complexity ];
		$expires = Services::Request()->ts() + MINUTE_IN_SECONDS*5;
		$challenge = $protocol->buildChallenge(
			$hmacKey,
			$protocol->keySignatureSecret( $hmacKey ),
			$profile[ 'cost' ],
			\random_int( $profile[ 'counter_min' ], $profile[ 'counter_max' ] ),
			$expires
		);

		return [
			'altcha_version'   => AltChaV2Pbkdf2::VERSION,
			'altcha_challenge' => $protocol->encodeChallenge( $challenge ),
		];
	}

	private function protocol() :AltChaV2Pbkdf2 {
		return new AltChaV2Pbkdf2();
	}

	private function effectiveComplexityLevel() :string {
		$complexity = $this->complexityLevel();
		if ( \in_array( $complexity, [
			SilentCaptchaComplexity::NONE,
			SilentCaptchaComplexity::HIGH,
		], true ) ) {
			return $complexity;
		}

		if ( $this->hasRecentPageLoadSignal() ) {
			return $complexity;
		}

		if ( $complexity === SilentCaptchaComplexity::LOW ) {
			return SilentCaptchaComplexity::MEDIUM;
		}

		return SilentCaptchaComplexity::HIGH;
	}

	private function hasRecentPageLoadSignal() :bool {
		try {
			$record = ( new BotSignalsRecord() )
				->setIP( self::con()->this_req->ip )
				->retrieve();
			$lastPageLoadAt = \max( (int)$record->frontpage_at, (int)$record->loginpage_at );
		}
		catch ( \Exception $e ) {
			$lastPageLoadAt = 0;
		}

		return $lastPageLoadAt > 0 && Services::Request()->ts() - $lastPageLoadAt <= HOUR_IN_SECONDS;
	}

}
