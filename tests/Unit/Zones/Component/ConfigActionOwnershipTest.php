<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Zones\Component;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	InvokesNonPublicMethods,
	PluginControllerInstaller
};
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\{
	Base as ComponentBase,
	PasswordPolicies,
	PasswordStrength,
	PwnedPasswords,
	ScanScheduling,
	TrustedCommenters
};

class ConfigActionOwnershipTest extends BaseUnitTest {

	use InvokesNonPublicMethods;

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_components_own_the_settings_their_configure_rows_point_to() :void {
		$expected = [
			[ new ScanScheduling(), [ 'scan_frequency' ], 'scan_frequency' ],
			[ new TrustedCommenters(), [ 'trusted_commenter_minimum' ], 'trusted_commenter_minimum' ],
			[ new PasswordPolicies(), [ 'enable_password_policies' ], 'enable_password_policies' ],
			[ new PwnedPasswords(), [ 'enable_password_policies', 'pass_prevent_pwned' ], 'pass_prevent_pwned' ],
			[ new PasswordStrength(), [ 'enable_password_policies', 'pass_min_strength' ], 'pass_min_strength' ],
		];
		foreach ( $expected as [ $component, $options, $configItem ] ) {
			$this->assertSame( $options, $component->getOptions(), \get_class( $component ) );
			$this->assertSame( $configItem, $this->invokeNonPublicMethod( $component, 'configItem' ), \get_class( $component ) );
		}
	}

	public function test_components_without_settings_own_none() :void {
		PluginControllerInstaller::reset();
		$this->installControllerStub( [] );

		$component = new class extends ComponentBase {
			public static function Slug() :string {
				return 'empty_component';
			}
		};

		$this->assertSame( [], $component->getOptions() );
	}

	private function installControllerStub( ?array $options = null ) :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->cfg = (object)[
			'configuration' => (object)[
				'options' => $options ?? [
					'scan_frequency' => [
						'zone_comp_slugs' => [
							ScanScheduling::Slug(),
							'module_scans',
						],
					],
					'trusted_commenter_minimum' => [
						'zone_comp_slugs' => [
							TrustedCommenters::Slug(),
							'module_spam',
						],
					],
					'enable_password_policies' => [
						'zone_comp_slugs' => [
							PasswordPolicies::Slug(),
							PwnedPasswords::Slug(),
							PasswordStrength::Slug(),
							'module_users',
						],
					],
					'pass_prevent_pwned' => [
						'zone_comp_slugs' => [
							PwnedPasswords::Slug(),
							'module_users',
						],
					],
					'pass_min_strength' => [
						'zone_comp_slugs' => [
							PasswordStrength::Slug(),
							'module_users',
						],
					],
				],
			],
		];
		$controller->svgs = new class {
			public function iconClass( string $icon ) :string {
				return 'bi bi-'.$icon;
			}
		};

		PluginControllerInstaller::install( $controller );
	}
}
