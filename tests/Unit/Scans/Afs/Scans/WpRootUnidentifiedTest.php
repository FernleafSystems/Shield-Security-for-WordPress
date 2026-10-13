<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Scans\Afs\Scans;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Exceptions\WpRootFileUnidentifiedException;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Scans\WpRootUnidentified;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState,
	UnitTestControllerFactory
};
use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\EarlyLoading\EarlyLoadingController;
use FernleafSystems\Wordpress\Services\Core\Fs;

class WpRootUnidentifiedTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	private string $root;

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		// Mirrors core: backslashes become slashes and repeated slashes collapse, except a leading "//".
		Functions\when( 'wp_normalize_path' )->alias(
			static fn( $path ) :string => (string)\preg_replace( '|(?<=.)/+|', '/', \str_replace( '\\', '/', (string)$path ) )
		);
		$this->root = \rtrim( \str_replace( '\\', '/', ABSPATH ), '/' );
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_unidentified_file_in_root_is_reported() :void {
		$this->expectException( WpRootFileUnidentifiedException::class );
		$this->scan( $this->root.'/shell.php' );
	}

	public function test_root_file_is_reported_when_its_path_form_differs_from_path_join() :void {
		// A root ABSPATH ("//") gives "//shell.php" from the directory iterator but "/shell.php" from path_join().
		$this->expectException( WpRootFileUnidentifiedException::class );
		$this->scan( $this->root.'//shell.php' );
	}

	public function test_files_outside_the_root_directory_are_not_reported() :void {
		foreach (
			[
				'subdirectory'      => $this->root.'/wp-admin/shell.php',
				'outside ABSPATH'   => \dirname( $this->root ).'/shell.php',
				'sibling directory' => $this->root.'-other/shell.php',
				'excluded file'     => $this->root.'/wp-config.php',
			] as $case => $path
		) {
			$this->assertFalse( $this->scan( $path ), $case );
		}
	}

	public function test_inaccessible_root_file_is_not_reported() :void {
		$this->assertFalse( $this->scan( $this->root.'/shell.php', false ) );
	}

	public function test_shield_early_loader_stub_is_skipped_only_while_shield_generated_it() :void {
		$stub = $this->root.'/shield-early-loader.php';

		$this->installEarlyLoading( true );
		$this->assertFalse( $this->scan( $stub ) );

		$this->installEarlyLoading( false );
		$this->expectException( WpRootFileUnidentifiedException::class );
		$this->scan( $stub );
	}

	private function installEarlyLoading( bool $generated ) :void {
		UnitTestControllerFactory::install( null, null, (object)[
			'comps' => (object)[
				'early_loading' => new class( $generated ) extends EarlyLoadingController {

					private bool $generated;

					public function __construct( bool $generated ) {
						$this->generated = $generated;
					}

					public function isGeneratedStub( string $path ) :bool {
						return $this->generated;
					}
				},
			],
		] );
	}

	private function scan( string $path, bool $accessible = true ) :bool {
		ServicesState::installItems( [
			'service_wpfs' => new WpRootUnidentifiedTestFs( $accessible ? [ $path ] : [] ),
		] );
		return ( new WpRootUnidentified( $path ) )->isFileValid();
	}
}

class WpRootUnidentifiedTestFs extends Fs {

	private array $accessibleFiles;

	public function __construct( array $accessibleFiles ) {
		$this->accessibleFiles = $accessibleFiles;
	}

	public function isAccessibleFile( string $path ) :bool {
		return \in_array( $path, $this->accessibleFiles, true );
	}
}
