<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\ShieldPlatform\Tooling\PluginPackager\TwigTemplateReferenceVerifier;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempDirLifecycleTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\TempPathJoinTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class TwigTemplateReferenceVerifierTest extends TestCase {

	use TempDirLifecycleTrait;
	use TempPathJoinTrait;

	private Filesystem $fs;

	private string $tempDir;

	protected function setUp() :void {
		parent::setUp();
		$this->fs = new Filesystem();
		$this->tempDir = $this->createTrackedTempDir( 'shield-twig-verifier-' );
	}

	protected function tearDown() :void {
		$this->cleanupTrackedTempDirs();
		parent::tearDown();
	}

	public function testStaticReferencesResolveAcrossAbsoluteAndRelativeTemplatePaths() :void {
		$this->writeTemplate( 'base.twig', '' );
		$this->writeTemplate( 'embed.twig', '' );
		$this->writeTemplate( 'common/macros.twig', '' );
		$this->writeTemplate( 'admin/partial.twig', '' );
		$this->writeTemplate( 'admin/trimmed.twig', '' );
		$this->writeTemplate( 'admin/page.twig', <<<'TWIG'
{% import '/common/macros.twig' as macros %}
{% from '/common/macros.twig' import renderAttributes %}
{% extends '/base.twig' %}
{% include 'partial.twig' with { row: row } only %}
{% embed '/embed.twig' %}{% endembed %}
{%- include 'trimmed.twig' -%}
TWIG );

		$this->assertSame( [], $this->verifier()->findMissingReferences( $this->tempDir ) );
	}

	public function testMultiLineStaticReferencesResolve() :void {
		$this->writeTemplate( 'base.twig', '' );
		$this->writeTemplate( 'admin/partial.twig', '' );
		$this->writeTemplate( 'admin/page.twig', <<<'TWIG'
{% extends
	'/base.twig'
%}
{% include
	'partial.twig'
	with { row: row }
	only
%}
TWIG );

		$this->assertSame( [], $this->verifier()->findMissingReferences( $this->tempDir ) );
	}

	public function testMissingStaticReferenceReportsSourceLineAndTarget() :void {
		$this->writeTemplate( 'base.twig', '' );
		$this->writeTemplate( 'wpadmin/plugin_pages/inner/import.twig', <<<'TWIG'
{% extends '/base.twig' %}
{% include '/wpadmin_pages/insights/importexport/to_file.twig' %}
TWIG );

		$missing = $this->verifier()->findMissingReferences( $this->tempDir );

		$this->assertSame( [
			[
				'source'    => 'wpadmin/plugin_pages/inner/import.twig',
				'line'      => 2,
				'reference' => '/wpadmin_pages/insights/importexport/to_file.twig',
				'target'    => 'wpadmin_pages/insights/importexport/to_file.twig',
			],
		], $missing );
	}

	public function testMissingStaticImportReferenceReportsSourceLineAndTarget() :void {
		$this->writeTemplate( 'wpadmin/components/page/shield_detail_row.twig', <<<'TWIG'
{% import '/common/macros.twig' as icwp_macros %}
{{ icwp_macros.renderAttributes({}) }}
TWIG );

		$missing = $this->verifier()->findMissingReferences( $this->tempDir );

		$this->assertSame( [
			[
				'source'    => 'wpadmin/components/page/shield_detail_row.twig',
				'line'      => 1,
				'reference' => '/common/macros.twig',
				'target'    => 'common/macros.twig',
			],
		], $missing );
	}

	public function testMissingMultiLineStaticReferenceReportsSourceLineAndTarget() :void {
		$this->writeTemplate( 'base.twig', '' );
		$this->writeTemplate( 'wpadmin/plugin_pages/inner/import.twig', <<<'TWIG'
{% extends '/base.twig' %}

{% include
	'/wpadmin_pages/insights/importexport/to_file.twig'
	with { export: export }
	only
%}
TWIG );

		$missing = $this->verifier()->findMissingReferences( $this->tempDir );

		$this->assertSame( [
			[
				'source'    => 'wpadmin/plugin_pages/inner/import.twig',
				'line'      => 3,
				'reference' => '/wpadmin_pages/insights/importexport/to_file.twig',
				'target'    => 'wpadmin_pages/insights/importexport/to_file.twig',
			],
		], $missing );
	}

	public function testDynamicAndIgnoreMissingReferencesAreNotTreatedAsRequiredStaticContracts() :void {
		$this->writeTemplate( 'admin/page.twig', <<<'TWIG'
{% include '/components/config/custom/'~opt_section.slug~'.twig' ignore missing %}
{% include '/optional/missing.twig' ignore missing %}
{% include pane.body_include with pane.body_vars|default({}) only %}
{% include
	'/optional/multi-line.twig'
	ignore missing
%}
{% include
	pane.body_include
	with pane.body_vars|default({})
	only
%}
TWIG );

		$this->assertSame( [], $this->verifier()->findMissingReferences( $this->tempDir ) );
	}

	private function verifier() :TwigTemplateReferenceVerifier {
		return new TwigTemplateReferenceVerifier();
	}

	private function writeTemplate( string $relativePath, string $content ) :void {
		$this->fs->dumpFile( $this->tempPath( $relativePath ), $content );
	}
}
