<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\VirtualPatching\Ruleset;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\VirtualPatching\Ruleset\{
	RulesetFile,
	RulesetStore
};
use FernleafSystems\Wordpress\Services\Core\Fs;

class RulesetStoreTest extends BaseUnitTest {

	use TempDirLifecycleTrait;

	private array $servicesSnapshot = [];

	private Fs $fs;

	protected function setUp() :void {
		parent::setUp();
		$this->servicesSnapshot = ServicesState::snapshot();
		$this->fs = new class extends Fs {
			public bool $failWrites = false;

			public function fs() {
				return null;
			}

			public function mkdir( $path ) {
				return \is_dir( $path ) || \mkdir( $path, 0755, true );
			}

			public function putFileContent( $path, $contents, $compress = false ) :bool {
				if ( $this->failWrites ) {
					\file_put_contents( $path, \substr( $contents, 0, 20 ) );
					return true;
				}
				return parent::putFileContent( $path, $contents, $compress );
			}
		};
		ServicesState::mergeItems( [ 'service_wpfs' => $this->fs ] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testWriteCreatesTheDirectoryAndLeavesOnlyTheRuleset() :void {
		$path = $this->createTrackedTempDir().'/virtual-patching/ruleset.php';

		$this->assertTrue( ( new RulesetStore() )->write( $path, RulesetFileTest::RULESET ) );

		$this->assertSame( RulesetFileTest::RULESET[ 'rules' ], RulesetFile::read( $path )[ 'rules' ] );
		$this->assertSame( [ 'ruleset.php' ], $this->filesIn( \dirname( $path ) ) );
	}

	public function testRewriteReplacesTheRuleset() :void {
		$path = $this->createTrackedTempDir().'/ruleset.php';
		$store = new RulesetStore();
		$store->write( $path, RulesetFileTest::RULESET );

		$changed = RulesetFileTest::RULESET;
		$changed[ 'rules' ] = [];
		$this->assertTrue( $store->write( $path, $changed ) );

		$this->assertSame( [], RulesetFile::read( $path )[ 'rules' ] );
	}

	public function testIncompleteWriteKeepsThePreviousRuleset() :void {
		$path = $this->createTrackedTempDir().'/ruleset.php';
		$store = new RulesetStore();
		$store->write( $path, RulesetFileTest::RULESET );

		$this->fs->failWrites = true;
		$changed = RulesetFileTest::RULESET;
		$changed[ 'rules' ] = [];

		$this->assertFalse( $store->write( $path, $changed ) );
		$this->assertSame( RulesetFileTest::RULESET[ 'rules' ], RulesetFile::read( $path )[ 'rules' ] );
		$this->assertSame( [ 'ruleset.php' ], $this->filesIn( \dirname( $path ) ) );
	}

	public function testDelete() :void {
		$path = $this->createTrackedTempDir().'/ruleset.php';
		$store = new RulesetStore();
		$store->write( $path, RulesetFileTest::RULESET );

		$this->assertTrue( $store->delete( $path ) );
		$this->assertFileDoesNotExist( $path );
		$this->assertTrue( $store->delete( $path ) );
	}

	private function filesIn( string $dir ) :array {
		return \array_values( \array_diff( \scandir( $dir ), [ '.', '..' ] ) );
	}
}
