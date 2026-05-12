<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Abilities\AbilityDefinitions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Support\QuerySurfaceAccessPolicy;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Utilities\Mcp\ServerRegistrar;
use FernleafSystems\Wordpress\Services\Utilities\Mcp\Transport\McpTransportInterface;

class McpCon {

	use ExecOnce;
	use PluginControllerConsumer;

	public const SERVER_ID = 'shield-security';
	public const ROUTE_NAMESPACE = 'shield-security';
	public const ROUTE_SEGMENT = 'mcp';

	private ?ServerRegistrar $registrar = null;

	protected function run() {
		$this->getRegistrar()->register();
	}

	public function isAvailable() :bool {
		return $this->getRegistrar()->isAvailable();
	}

	public function isTransportAvailable() :bool {
		return $this->isAvailable()
			   && $this->getTransport()->isSupported();
	}

	public function getTransport() :McpTransportInterface {
		return $this->getRegistrar()->getTransport();
	}

	protected function getRegistrar() :ServerRegistrar {
		return $this->registrar ??= $this->buildRegistrar();
	}

	protected function buildRegistrar() :ServerRegistrar {
		return ( new ServerRegistrar() )
			->setServerDefinition( [
				'server_id' => self::SERVER_ID,
				'namespace' => self::ROUTE_NAMESPACE,
				'route'     => self::ROUTE_SEGMENT,
				'version'   => self::con()->cfg->version(),
			] )
			->setCategoryDefinition( [
				'slug'        => AbilityDefinitions::CATEGORY_SLUG,
				'label'       => __( 'Shield Security', 'wp-simple-firewall' ),
				'description' => __( 'Read-only security posture and activity abilities for Shield Security.', 'wp-simple-firewall' ),
			] )
			->setAbilityDefinitions( ( new AbilityDefinitions() )->build() )
			->setAvailabilityCallback( fn() :bool => $this->getAccessPolicy()->isSiteExposureReady() );
	}

	protected function getAccessPolicy() :QuerySurfaceAccessPolicy {
		return new QuerySurfaceAccessPolicy();
	}
}
