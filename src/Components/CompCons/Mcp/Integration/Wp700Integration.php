<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Integration;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Abilities\AbilityDefinitions;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Support\Compatibility;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Transport\{
	McpTransportInterface,
	NullTransport,
	WpMcpAdapterTransport
};

class Wp700Integration extends BaseIntegration {

	private ?McpTransportInterface $transport = null;

	public function isSupported() :bool {
		return $this->getCompatibility()->supportsAbilitiesIntegration();
	}

	public function register() :void {
		if ( !$this->isSupported() ) {
			return;
		}

		\add_action( 'wp_abilities_api_categories_init', [ $this, 'registerAbilityCategory' ] );
		\add_action( 'wp_abilities_api_init', [ $this, 'registerAbilities' ] );

		$this->getTransport()->registerServer( self::con()->comps->mcp->buildServerDefinition() );
	}

	public function getTransport() :McpTransportInterface {
		if ( $this->transport === null ) {
			$this->transport = $this->getCompatibility()->supportsAdapterTransport()
				? new WpMcpAdapterTransport()
				: new NullTransport();
		}
		return $this->transport;
	}

	public function registerAbilityCategory() :void {
		$slug = AbilityDefinitions::CATEGORY_SLUG;
		if ( \function_exists( '\wp_has_ability_category' ) && \wp_has_ability_category( $slug ) ) {
			return;
		}

		\wp_register_ability_category( $slug, [
			'label'       => __( 'Shield Security', 'wp-simple-firewall' ),
			'description' => __( 'Read-only security posture and activity abilities for Shield Security.', 'wp-simple-firewall' ),
		] );
	}

	public function registerAbilities() :void {
		foreach ( self::con()->comps->mcp->enumAbilityDefinitions() as $definition ) {
			if ( \function_exists( '\wp_has_ability' ) && \wp_has_ability( $definition[ 'name' ] ) ) {
				continue;
			}

			\wp_register_ability( $definition[ 'name' ], $definition[ 'args' ] );
		}
	}

	protected function getCompatibility() :Compatibility {
		return new Compatibility();
	}
}
