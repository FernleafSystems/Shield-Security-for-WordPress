<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Scans\Afs;

use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\FileScanner;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Utilities\IsExcludedPhpTranslationFile;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldWordPressTestCase;

class IsExcludedPhpTranslationFileIntegrationTest extends ShieldWordPressTestCase {

	private string $testRoot = '';

	public function set_up() {
		parent::set_up();

		if ( !\defined( 'WP_LANG_DIR' ) ) {
			$this->markTestSkipped( 'WP_LANG_DIR is unavailable in this test environment.' );
		}

		$this->testRoot = wp_normalize_path( path_join( WP_LANG_DIR, 'shield-tests-l10n' ) );
		if ( !is_dir( $this->testRoot ) && !wp_mkdir_p( $this->testRoot ) ) {
			$this->markTestSkipped( 'Unable to create temporary language test directory.' );
		}

		if ( !is_writable( $this->testRoot ) ) {
			$this->markTestSkipped( 'Language test directory is not writable.' );
		}
	}

	public function tear_down() {
		$this->deleteDirectoryRecursively( $this->testRoot );
		parent::tear_down();
	}

	public function testExcludesValidPluginLanguageFileWithinWpLangDir() :void {
		$path = $this->createTranslationPhpFile(
			'plugins/wp-simple-firewall-tr_TR.l10n.php',
			$this->validTranslationContentWithUtf8Bom()
		);

		$this->assertTrue( ( new IsExcludedPhpTranslationFile() )->check( $path ) );
	}

	public function testExcludesCoreStyleThreeLetterLocaleLanguageFiles() :void {
		$corePath = $this->createTranslationPhpFile(
			'bel.l10n.php',
			$this->validTranslationContent()
		);
		$networkPath = $this->createTranslationPhpFile(
			'admin-network-bel.l10n.php',
			$this->validTranslationContent()
		);

		$subject = new IsExcludedPhpTranslationFile();
		$this->assertTrue( $subject->check( $corePath ) );
		$this->assertTrue( $subject->check( $networkPath ) );
	}

	public function testDoesNotExcludeFilesOutsideWordpressLanguageDirectory() :void {
		$outsideDir = wp_normalize_path( path_join( wp_normalize_path( \sys_get_temp_dir() ), 'shield-tests-l10n-outside' ) );
		if ( !is_dir( $outsideDir ) && !wp_mkdir_p( $outsideDir ) ) {
			$this->markTestSkipped( 'Unable to create outside test directory.' );
		}

		$outsidePath = wp_normalize_path( path_join( $outsideDir, 'wp-simple-firewall-tr_TR.l10n.php' ) );
		\file_put_contents( $outsidePath, $this->validTranslationContent() );

		$this->assertFalse( ( new IsExcludedPhpTranslationFile() )->check( $outsidePath ) );

		@unlink( $outsidePath );
		@rmdir( $outsideDir );
	}

	public function testDoesNotExcludeExecutablePhpDisguisedAsTranslationFile() :void {
		$path = $this->createTranslationPhpFile(
			'plugins/wp-simple-firewall-tr_TR.l10n.php',
			$this->maliciousTranslationLikeContent()
		);

		$this->assertFalse( ( new IsExcludedPhpTranslationFile() )->check( $path ) );
	}

	public function testFileScannerExclusionGatewayTreatsVerifiedTranslationFileAsExcluded() :void {
		$path = $this->createTranslationPhpFile(
			'plugins/wp-simple-firewall-tr_TR.l10n.php',
			$this->validTranslationContent()
		);

		$scanner = new FileScanner();
		$reflection = new \ReflectionClass( $scanner );
		$method = $reflection->getMethod( 'isFileExcludedFromScans' );
		$method->setAccessible( true );

		$this->assertTrue( (bool)$method->invoke( $scanner, $path ) );
	}

	public function testFileScannerExclusionGatewayDoesNotExcludeMaliciousTranslationLikeFile() :void {
		$path = $this->createTranslationPhpFile(
			'plugins/wp-simple-firewall-tr_TR.l10n.php',
			$this->maliciousTranslationLikeContent()
		);

		$scanner = new FileScanner();
		$reflection = new \ReflectionClass( $scanner );
		$method = $reflection->getMethod( 'isFileExcludedFromScans' );
		$method->setAccessible( true );

		$this->assertFalse( (bool)$method->invoke( $scanner, $path ) );
	}

	private function createTranslationPhpFile( string $relativePath, string $content ) :string {
		$path = wp_normalize_path( path_join( $this->testRoot, \ltrim( wp_normalize_path( $relativePath ), '/' ) ) );
		$dir = \dirname( $path );

		if ( !is_dir( $dir ) && !wp_mkdir_p( $dir ) ) {
			$this->fail( sprintf( 'Failed to create directory: %s', $dir ) );
		}
		if ( \file_put_contents( $path, $content ) === false ) {
			$this->fail( sprintf( 'Failed to write file: %s', $path ) );
		}

		return $path;
	}

	private function validTranslationContent() :string {
		return "<?php return ['x-generator'=>'GlotPress/4.0','translation-revision-date'=>'2026-01-01','messages'=>['Original'=>'Translated']];";
	}

	private function validTranslationContentWithUtf8Bom() :string {
		return "\xEF\xBB\xBF".$this->validTranslationContent();
	}

	private function maliciousTranslationLikeContent() :string {
		return "<?php return ['x-generator'=>'GlotPress/4.0','translation-revision-date'=>'2026-01-01','messages'=>['Original'=>'Translated']]; system('id');";
	}

	private function deleteDirectoryRecursively( string $dir ) :void {
		$dir = wp_normalize_path( $dir );
		if ( empty( $dir ) || !is_dir( $dir ) ) {
			return;
		}

		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $items as $item ) {
			/** @var \SplFileInfo $item */
			if ( $item->isDir() ) {
				@rmdir( $item->getPathname() );
			}
			else {
				@unlink( $item->getPathname() );
			}
		}
		@rmdir( $dir );
	}
}
