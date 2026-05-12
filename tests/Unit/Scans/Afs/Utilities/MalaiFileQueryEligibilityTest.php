<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Scans\Afs\Utilities;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\ResultItem;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Utilities\MalaiFileQueryEligibility;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\Fs;

class MalaiFileQueryEligibilityTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		$this->servicesSnapshot = ServicesState::snapshot();
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_supported_accessible_file_can_be_offered() :void {
		$path = '/var/www/wp-content/plugins/example/file.PHTML';
		$this->installFs( [ $path => true ], [ $path => 123 ] );

		$this->assertSame(
			$path,
			( new MalaiFileQueryEligibility() )->assertCanOfferQuery( $this->item( $path ) )
		);
	}

	public function test_unsupported_extension_is_rejected() :void {
		$path = '/var/www/wp-content/uploads/readme.txt';
		$this->installFs( [ $path => true ], [ $path => 123 ] );

		$this->expectException( \Exception::class );

		( new MalaiFileQueryEligibility() )->assertCanOfferQuery( $this->item( $path ) );
	}

	public function test_inaccessible_file_is_rejected() :void {
		$path = '/var/www/wp-content/plugins/example/missing.php';
		$this->installFs( [ $path => false ], [ $path => 123 ] );

		$this->expectException( \Exception::class );

		( new MalaiFileQueryEligibility() )->assertCanOfferQuery( $this->item( $path ) );
	}

	public function test_empty_file_cannot_be_submitted() :void {
		$path = '/var/www/wp-content/plugins/example/empty.php';
		$this->installFs( [ $path => true ], [ $path => 0 ] );

		$this->expectException( \Exception::class );

		( new MalaiFileQueryEligibility() )->assertCanSubmitQuery( $this->item( $path ) );
	}

	public function test_already_malware_file_is_rejected() :void {
		$path = '/var/www/wp-content/plugins/example/malware.php';
		$this->installFs( [ $path => true ], [ $path => 123 ] );

		$this->expectException( \Exception::class );

		( new MalaiFileQueryEligibility() )->assertCanOfferQuery( $this->item( $path, true ) );
	}

	private function installFs( array $accessibleByPath, array $sizeByPath ) :void {
		ServicesState::installItems( [
			'service_wpfs' => new MalaiEligibilityFs( $accessibleByPath, $sizeByPath ),
		] );
	}

	private function item( string $path, bool $isMal = false ) :ResultItem {
		/** @var ResultItem $item */
		$item = ( new ResultItem() )->applyFromArray( [
			'path_full'     => $path,
			'path_fragment' => \basename( $path ),
			'is_mal'        => $isMal,
		] );
		return $item;
	}
}

class MalaiEligibilityFs extends Fs {

	private array $accessibleByPath;

	private array $sizeByPath;

	public function __construct( array $accessibleByPath, array $sizeByPath ) {
		$this->accessibleByPath = $accessibleByPath;
		$this->sizeByPath = $sizeByPath;
	}

	public function isAccessibleFile( string $path ) :bool {
		return (bool)( $this->accessibleByPath[ $path ] ?? false );
	}

	public function getFileSize( $path ) :?int {
		return $this->sizeByPath[ $path ] ?? null;
	}
}
