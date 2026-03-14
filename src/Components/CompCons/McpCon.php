<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\{
	Abilities\AbilityDefinitions,
	Integration\BaseIntegration,
	Integration\UnsupportedIntegration,
	Integration\Wp700Integration,
	Support\Compatibility,
	Support\QuerySurfaceAccessPolicy,
	Transport\McpTransportInterface
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class McpCon {

	use ExecOnce;
	use PluginControllerConsumer;

	public const SERVER_ID = 'shield-security';
	public const ROUTE_NAMESPACE = 'shield-security';
	public const ROUTE_SEGMENT = 'mcp';

	private ?BaseIntegration $integration = null;

	protected function run() {
		$this->getIntegration()->register();
	}

	public function isAvailable() :bool {
		return $this->getIntegration()->isSupported()
			   && $this->getAccessPolicy()->isSiteExposureReady();
	}

	public function isTransportAvailable() :bool {
		return $this->isAvailable()
			   && $this->getTransport()->isSupported();
	}

	/**
	 * @return list<array{name:string,args:array<string,mixed>}>
	 */
	public function enumAbilityDefinitions() :array {
		return ( new AbilityDefinitions() )->build();
	}

	/**
	 * @return string[]
	 */
	public function enumMcpAbilityNames() :array {
		return AbilityDefinitions::MCP_ABILITY_NAMES;
	}

	/**
	 * @return array{server_id:string,namespace:string,route:string,version:string,abilities:string[]}
	 */
	public function buildServerDefinition() :array {
		return [
			'server_id' => self::SERVER_ID,
			'namespace' => self::ROUTE_NAMESPACE,
			'route'     => self::ROUTE_SEGMENT,
			'version'   => self::con()->cfg->version(),
			'abilities' => $this->enumMcpAbilityNames(),
		];
	}

	public function getTransport() :McpTransportInterface {
		return $this->getIntegration()->getTransport();
	}

	public function getIntegration() :BaseIntegration {
		if ( $this->integration === null ) {
			$this->integration = $this->getCompatibility()->supportsAbilitiesIntegration()
				? new Wp700Integration()
				: new UnsupportedIntegration();
		}
		return $this->integration;
	}

	protected function getCompatibility() :Compatibility {
		return new Compatibility();
	}

	protected function getAccessPolicy() :QuerySurfaceAccessPolicy {
		return new QuerySurfaceAccessPolicy();
	}
}
