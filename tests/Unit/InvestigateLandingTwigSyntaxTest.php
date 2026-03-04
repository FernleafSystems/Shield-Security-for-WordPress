<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Twig\{
	Environment,
	Loader\FilesystemLoader,
	TemplateWrapper
};

class InvestigateLandingTwigSyntaxTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function testInvestigateLandingTemplateCompilesWithTwigParser() :void {
		$templatesRoot = $this->getPluginFilePath( 'templates/twig' );
		$this->assertFileExistsWithDebug( $templatesRoot, 'Twig templates root directory should exist.' );

		$twig = new Environment(
			new FilesystemLoader( $templatesRoot ),
			[
				'cache'            => false,
				'debug'            => false,
				'strict_variables' => false,
			]
		);

		try {
			$template = $twig->load( '/wpadmin/plugin_pages/inner/investigate_landing.twig' );
		}
		catch ( \Throwable $e ) {
			$this->fail(
				\sprintf(
					'Investigate landing template should compile without Twig syntax errors. %s: %s',
					\get_class( $e ),
					$e->getMessage()
				)
			);
			return;
		}

		$this->assertInstanceOf( TemplateWrapper::class, $template );
	}
}
