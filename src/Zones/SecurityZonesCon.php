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
	private array $zones;

	public function getZone( string $slug ) :Zone\Base {
		return $this->getZones()[ $slug ];
	}

	/**
	 * @return Component\Base|mixed
	 */
	public function getZoneComponent( string $slug ) :Component\Base {
		$c = $this->enumZoneComponents()[ $slug ];
		return new $c();
	}

	/**
	 * @return Component\Base[]
	 */
	public function getComponentsForZone( Zone\Base $zone ) :array {
		return \array_map( fn( string $c ) => new $c(), $zone->components() );
	}

	/**
	 * @return Zone\Base[]|mixed
	 */
	public function getZones() :array {
		return $this->zones ??= \array_map( fn( string $class ) => new $class(), $this->enumZones() );
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
			Zone\Secadmin::class,
			Zone\Firewall::class,
			Zone\Ips::class,
			Zone\Scans::class,
			Zone\Login::class,
			Zone\Users::class,
			Zone\Spam::class,
			Zone\Headers::class,
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
			Component\BotActions::class,
			Component\CommentSpamBlockBot::class,
			Component\CommentSpamBlockHuman::class,
			Component\ContactFormSpamBlockBot::class,
			Component\CrowdsecBlocking::class,
			Component\FileEditingBlock::class,
			Component\FileLocker::class,
			Component\FileScanning::class,
			Component\GlobalPluginEnable::class,
			Component\HeadersGeneral::class,
			Component\HeadersCsp::class,
			Component\ImportExport::class,
			Component\InactiveUsers::class,
			Component\InstantAlerts::class,
			Component\IpBlockingRules::class,
			Component\LoginHide::class,
			Component\LoginProtectionForms::class,
			Component\Modules\ModuleFirewall::class,
			Component\Modules\ModuleHeaders::class,
			Component\Modules\ModuleIntegrations::class,
			Component\Modules\ModuleIps::class,
			Component\Modules\ModuleLogin::class,
			Component\Modules\ModulePlugin::class,
			Component\Modules\ModuleScans::class,
			Component\Modules\ModuleSpam::class,
			Component\Modules\ModuleSecadmin::class,
			Component\Modules\ModuleUsers::class,
			Component\PasswordStrength::class,
			Component\PluginGeneral::class,
			Component\PasswordPolicies::class,
			Component\PwnedPasswords::class,
			Component\RateLimiting::class,
			Component\Reporting::class,
			Component\RequestLogging::class,
			Component\RequestLiveLogging::class,
			Component\Scans::class,
			Component\SecadminEnabled::class,
			Component\SecadminWpAdmins::class,
			Component\SecadminWpOptions::class,
			Component\ServerSoftwareStatus::class,
			Component\SessionTheftProtection::class,
			Component\SpamUserRegisterBlock::class,
			Component\SilentCaptcha::class,
			Component\TwoFactorAuth::class,
			Component\UsernameFishingBlock::class,
			Component\VulnerabilityScanning::class,
			Component\WebApplicationFirewall::class,
			Component\Whitelabel::class,
			Component\WordpressUpdates::class,
			Component\XmlRpcDisable::class,
		];
	}
}