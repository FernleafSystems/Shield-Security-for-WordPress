<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationSubjectResolver;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\{
	InvalidInvestigationSubjectIdentifierException,
	UnsupportedInvestigationSubjectTypeException
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class InvestigationSubjectResolverTest extends BaseUnitTest {

	private function buildResolverWithAssets( array $plugins = [], array $themes = [] ) :InvestigationSubjectResolver {
		return new class( $plugins, $themes ) extends InvestigationSubjectResolver {

			private array $plugins;
			private array $themes;

			public function __construct( array $plugins, array $themes ) {
				$this->plugins = $plugins;
				$this->themes = $themes;
			}

			protected function getInstalledPluginSubjectIdentifiers() :array {
				return $this->plugins;
			}

			protected function getInstalledThemeSubjectIdentifiers() :array {
				return $this->themes;
			}
		};
	}

	public function testNormalizeRejectsUnsafePluginSlug() :void {
		$resolver = $this->buildResolverWithAssets( [ 'akismet/akismet.php' ] );

		$this->expectException( InvalidInvestigationSubjectIdentifierException::class );
		$resolver->normalize( 'file_scan_results', 'plugin', "test-plugin' OR 1=1 --" );
	}

	public function testNormalizeAcceptsAllowListedPluginSlug() :void {
		$slug = 'my plugin/main file.php';
		$resolver = $this->buildResolverWithAssets( [ $slug ] );
		$normalized = $resolver->normalize( 'file_scan_results', 'plugin', $slug );

		$this->assertSame( 'file_scan_results', $normalized[ 'table_type' ] );
		$this->assertSame( 'plugin', $normalized[ 'subject_type' ] );
		$this->assertSame( $slug, $normalized[ 'subject_id' ] );
	}

	public function testNormalizeAcceptsUrlEncodedAllowListedPluginSlug() :void {
		$slug = 'my plugin/main file.php';
		$resolver = $this->buildResolverWithAssets( [ $slug ] );
		$normalized = $resolver->normalize( 'file_scan_results', 'plugin', 'my%20plugin/main%20file.php' );

		$this->assertSame( $slug, $normalized[ 'subject_id' ] );
	}

	public function testNormalizeCastsAndValidatesUserId() :void {
		$resolver = $this->buildResolverWithAssets();
		$normalized = $resolver->normalize( 'sessions', 'user', '42' );

		$this->assertSame( 'sessions', $normalized[ 'table_type' ] );
		$this->assertSame( 'user', $normalized[ 'subject_type' ] );
		$this->assertSame( 42, $normalized[ 'subject_id' ] );
	}

	public function testNormalizeRejectsInvalidSubjectTypeForTable() :void {
		$resolver = $this->buildResolverWithAssets();

		$this->expectException( UnsupportedInvestigationSubjectTypeException::class );
		$resolver->normalize( 'sessions', 'ip', '1.2.3.4' );
	}

	public function testNormalizeAcceptsInstalledPluginForActivityTable() :void {
		$resolver = $this->buildResolverWithAssets( [ 'akismet/akismet.php' ] );
		$normalized = $resolver->normalize( 'activity', 'plugin', 'akismet/akismet.php' );

		$this->assertSame( 'activity', $normalized[ 'table_type' ] );
		$this->assertSame( 'plugin', $normalized[ 'subject_type' ] );
		$this->assertSame( 'akismet/akismet.php', $normalized[ 'subject_id' ] );
	}

	public function testNormalizeAcceptsInstalledThemeForActivityTable() :void {
		$resolver = $this->buildResolverWithAssets( [], [ 'twentytwentyfive' ] );
		$normalized = $resolver->normalize( 'activity', 'theme', 'twentytwentyfive' );

		$this->assertSame( 'activity', $normalized[ 'table_type' ] );
		$this->assertSame( 'theme', $normalized[ 'subject_type' ] );
		$this->assertSame( 'twentytwentyfive', $normalized[ 'subject_id' ] );
	}

	public function testNormalizeAcceptsCoreForActivityTable() :void {
		$resolver = $this->buildResolverWithAssets();
		$normalized = $resolver->normalize( 'activity', 'core', 'any-value' );

		$this->assertSame( 'activity', $normalized[ 'table_type' ] );
		$this->assertSame( 'core', $normalized[ 'subject_type' ] );
		$this->assertSame( 'core', $normalized[ 'subject_id' ] );
	}
}
