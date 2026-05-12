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
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginControllerInstaller;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Component\{
	PasswordPolicies,
	PasswordStrength,
	PwnedPasswords,
	ScanScheduling,
	TrustedCommenters
};

class ConfigActionOwnershipTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		parent::tearDown();
	}

	public function test_components_route_config_actions_to_real_owner_forms() :void {
		$scanSchedulingAction = ( new ScanScheduling() )->getActions()[ 'config' ] ?? [];
		$this->assertSame( ScanScheduling::Slug(), $scanSchedulingAction[ 'data' ][ 'zone_component_slug' ] ?? '' );
		$this->assertSame( 'scan_frequency', $scanSchedulingAction[ 'data' ][ 'config_item' ] ?? '' );

		$trustedCommentersAction = ( new TrustedCommenters() )->getActions()[ 'config' ] ?? [];
		$this->assertSame( TrustedCommenters::Slug(), $trustedCommentersAction[ 'data' ][ 'zone_component_slug' ] ?? '' );
		$this->assertSame( 'trusted_commenter_minimum', $trustedCommentersAction[ 'data' ][ 'config_item' ] ?? '' );

		$passwordPoliciesAction = ( new PasswordPolicies() )->getActions()[ 'config' ] ?? [];
		$this->assertSame( PasswordPolicies::Slug(), $passwordPoliciesAction[ 'data' ][ 'zone_component_slug' ] ?? '' );
		$this->assertSame( 'enable_password_policies', $passwordPoliciesAction[ 'data' ][ 'config_item' ] ?? '' );

		$pwnedPasswordsAction = ( new PwnedPasswords() )->getActions()[ 'config' ] ?? [];
		$this->assertSame( PwnedPasswords::Slug(), $pwnedPasswordsAction[ 'data' ][ 'zone_component_slug' ] ?? '' );
		$this->assertSame( 'pass_prevent_pwned', $pwnedPasswordsAction[ 'data' ][ 'config_item' ] ?? '' );

		$passwordStrengthAction = ( new PasswordStrength() )->getActions()[ 'config' ] ?? [];
		$this->assertSame( PasswordStrength::Slug(), $passwordStrengthAction[ 'data' ][ 'zone_component_slug' ] ?? '' );
		$this->assertSame( 'pass_min_strength', $passwordStrengthAction[ 'data' ][ 'config_item' ] ?? '' );
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->cfg = (object)[
			'configuration' => (object)[
				'options' => [
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
