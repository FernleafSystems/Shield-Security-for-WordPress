<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Fixtures;

use Brain\Monkey;
use Symfony\Component\Filesystem\Path;
use Yoast\PHPUnitPolyfills\TestCases\TestCase as PolyfillTestCase;

/**
 * Base test case class with common setup and utilities
 */
abstract class TestCase extends PolyfillTestCase {

    public function set_up() :void {
        parent::set_up();
        Monkey\setUp();
        $this->setUpTestEnvironment();
    }

    public function tear_down() :void {
        $this->tearDownTestEnvironment();
        Monkey\tearDown();
        parent::tear_down();
    }

    /**
     * Override in child classes for additional setup
     */
    protected function setUpTestEnvironment() :void {
        // Default WordPress function mocks
        $this->mockBasicWordPressFunctions();
    }

    /**
     * Override in child classes for additional cleanup
     */
    protected function tearDownTestEnvironment() :void {
        // Override in child classes if needed
    }

    /**
     * Mock basic WordPress functions commonly used in tests
     */
    protected function mockBasicWordPressFunctions() :void {
        // Mock basic WP functions that are commonly used
        Monkey\Functions\when( 'is_admin' )->justReturn( false );
        Monkey\Functions\when( 'is_multisite' )->justReturn( false );
        Monkey\Functions\when( 'current_user_can' )->justReturn( true );
        Monkey\Functions\when( 'get_option' )->justReturn( null );
        Monkey\Functions\when( 'wp_doing_ajax' )->justReturn( false );
        Monkey\Functions\when( 'wp_doing_cron' )->justReturn( false );
        Monkey\Functions\when( 'defined' )->justReturn( false );
        Monkey\Functions\when( 'home_url' )->justReturn( 'https://example.com' );
        Monkey\Functions\when( 'site_url' )->justReturn( 'https://example.com' );
    }

    /**
     * Create a mock WordPress user
     */
    protected function createMockUser( int $id = 1, string $role = 'administrator' ) :\WP_User {
        $user = $this->createMock( \WP_User::class );
        $user->ID = $id;
        $user->roles = [ $role ];
        return $user;
    }

    /**
     * Assert that a WordPress hook has been added
     */
    protected function assertHookAdded( string $hook, $callback = null, int $priority = 10 ) :void {
        $this->assertTrue( 
            has_action( $hook, $callback ) !== false, 
            "Hook '{$hook}' should be registered" 
        );
        
        if ( $callback !== null && $priority !== 10 ) {
            $this->assertEquals( 
                $priority, 
                has_action( $hook, $callback ), 
                "Hook '{$hook}' should be registered with priority {$priority}" 
            );
        }
    }

    /**
     * Get plugin root directory
     */
    protected function getPluginDir() :string {
        return dirname( dirname( dirname( __FILE__ ) ) );
    }

    /**
     * Get plugin main file path
     */
    protected function getPluginFile() :string {
        return Path::join( $this->getPluginDir(), 'icwp-wpsf.php' );
    }
}
