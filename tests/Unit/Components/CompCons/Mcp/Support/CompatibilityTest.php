<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Components\CompCons\Mcp\Support;

use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Mcp\Support\Compatibility;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class CompatibilityTest extends BaseUnitTest {

	public function test_supports_abilities_integration_requires_wordpress_seven_and_required_functions() :void {
		$compatibility = ( new CompatibilityTestDouble() )
			->setWordPressVersion( '6.9' )
			->setFunctions( [
				'\wp_register_ability'          => true,
				'\wp_register_ability_category' => true,
			] );

		$this->assertFalse( $compatibility->supportsAbilitiesIntegration() );

		$compatibility
			->setWordPressVersion( '7.0' )
			->setFunctions( [
				'\wp_register_ability'          => true,
				'\wp_register_ability_category' => false,
			] );

		$this->assertFalse( $compatibility->supportsAbilitiesIntegration() );

		$compatibility->setFunctions( [
			'\wp_register_ability'          => true,
			'\wp_register_ability_category' => true,
		] );

		$this->assertTrue( $compatibility->supportsAbilitiesIntegration() );
	}

	public function test_supports_adapter_transport_requires_full_runtime_contract() :void {
		$compatibility = ( new CompatibilityTestDouble() )
			->setWordPressVersion( '7.0' )
			->setFunctions( [
				'\wp_register_ability'          => true,
				'\wp_register_ability_category' => true,
			] )
			->setClasses( [
				'\WP\MCP\Core\McpAdapter'                      => true,
				'\WP\MCP\Transport\HttpTransport'             => true,
				'\WP\MCP\Handlers\WordPressErrorHandler'      => true,
				'\WP\MCP\Handlers\WordPressObservabilityHandler' => true,
			] )
			->setMethods( [
				'\WP\MCP\Core\McpAdapter' => [
					'instance'      => true,
					'create_server' => false,
				],
			] );

		$this->assertFalse( $compatibility->supportsAdapterTransport() );

		$compatibility->setMethods( [
			'\WP\MCP\Core\McpAdapter' => [
				'instance'      => true,
				'create_server' => true,
			],
		] );

		$this->assertTrue( $compatibility->supportsAdapterTransport() );
	}
}

class CompatibilityTestDouble extends Compatibility {

	private string $wordPressVersion = '7.0';

	private array $functions = [];

	private array $classes = [];

	private array $methods = [];

	public function setWordPressVersion( string $wordPressVersion ) :self {
		$this->wordPressVersion = $wordPressVersion;
		return $this;
	}

	public function setFunctions( array $functions ) :self {
		$this->functions = $functions;
		return $this;
	}

	public function setClasses( array $classes ) :self {
		$this->classes = $classes;
		return $this;
	}

	public function setMethods( array $methods ) :self {
		$this->methods = $methods;
		return $this;
	}

	protected function getWordPressVersion() :string {
		return $this->wordPressVersion;
	}

	protected function functionExists( string $function ) :bool {
		return $this->functions[ $function ] ?? false;
	}

	protected function classExists( string $class ) :bool {
		return $this->classes[ $class ] ?? false;
	}

	protected function methodExists( $classOrObject, string $method ) :bool {
		$class = \is_object( $classOrObject ) ? \get_class( $classOrObject ) : (string)$classOrObject;
		return $this->methods[ $class ][ $method ] ?? false;
	}
}
