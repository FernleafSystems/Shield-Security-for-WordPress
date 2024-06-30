<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class SecurityZonesCon {

	use ExecOnce;
	use PluginControllerConsumer;

	/**
	 * @var Zone\Base[]
	 */
	private $zones = null;

	protected function run() {
	}

	/**
	 * @return Zone\Base|mixed
	 */
	public function getZone( string $slug ) {
		return $this->getZones()[ $slug ];
	}

	/**
	 * @return Component\Base|mixed
	 */
	public function getZoneComponent( string $slug ) {
		$class = $this->enumZoneComponents()[ $slug ];
		return new $class();
	}

	/**
	 * @param Zone\Base|mixed $zone
	 * @return Component\Base[]
	 */
	public function getComponentsForZone( $zone ) :array {
		return \array_map(
			function ( string $class ) {
				return new $class();
			},
			$zone->components()
		);
	}

	/**
	 * @return Zone\Base[]|mixed
	 */
	public function getZones() :array {
		if ( $this->zones === null ) {
			$this->zones = \array_map(
				function ( string $class ) {
					return new $class();
				},
				$this->enumZones()
			);
		}
		return $this->zones;
	}

	public function getZoneSlugs() :array {
		return \array_keys( $this->enumZones() );
	}

	public function enumZoneComponents() :array {
		$indexed = [];
		foreach ( $this->rawEnumZoneComponents() as $class ) {
			$indexed[ $class::Slug() ] = $class;
		}
		return $indexed;
	}

	public function enumZones() :array {
		$indexed = [];
		foreach ( $this->rawEnumZones() as $class ) {
			$indexed[ $class::Slug() ] = $class;
		}
		return $indexed;
	}

	/**
	 * @return string[]|Zone\Base[]
	 */
	private function rawEnumZones() :array {
		return [
			Zone\Firewall::class,
			Zone\BotsIPs::class,
			Zone\Scans::class,
			Zone\Login::class,
			Zone\Users::class,
			Zone\Spam::class,
		];
	}

	/**
	 * @return string[]|Component\Base[]
	 */
	private function rawEnumZoneComponents() :array {
		return [
			Component\ActivityLogging::class,
			Component\AnonRestApiDisable::class,
			Component\AutoIpBlocking::class,
			Component\CommentSpamBlockBot::class,
			Component\CommentSpamBlockHuman::class,
			Component\ContactFormSpamBlockBot::class,
			Component\CrowdsecBlocking::class,
			Component\InstantAlerts::class,
			Component\LimitLogin::class,
			Component\PwnedPasswords::class,
			Component\RateLimiting::class,
			Component\Reporting::class,
			Component\RequestLogging::class,
			Component\SessionTheftProtection::class,
			Component\TwoFactorAuth::class,
			Component\Whitelabel::class,
			Component\XmlRpcDisable::class,
		];
	}
}