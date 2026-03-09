<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use Twig\{
	Environment,
	Loader\FilesystemLoader,
	TemplateWrapper
};

class ShieldDetailComponentsTwigTest extends BaseUnitTest {

	use PluginPathsTrait;

	private function twig() :Environment {
		return new Environment(
			new FilesystemLoader( $this->getPluginFilePath( 'templates/twig' ) ),
			[
				'cache'            => false,
				'debug'            => false,
				'strict_variables' => false,
			]
		);
	}

	/**
	 * @return string[]
	 */
	private function templates() :array {
		return [
			'/wpadmin/components/page/shield_detail_row.twig',
			'/wpadmin/components/page/shield_detail_expansion.twig',
			'/wpadmin/components/page/shield_rail_sidebar.twig',
			'/wpadmin/components/page/shield_rail_layout.twig',
			'/wpadmin/components/page/shield_detail_demo.twig',
		];
	}

	public function testDetailComponentTemplatesCompileWithTwigParser() :void {
		$twig = $this->twig();

		foreach ( $this->templates() as $templatePath ) {
			try {
				$template = $twig->load( $templatePath );
			}
			catch ( \Throwable $e ) {
				$this->fail(
					\sprintf(
						'Failed compiling %s. %s: %s',
						$templatePath,
						\get_class( $e ),
						$e->getMessage()
					)
				);
				return;
			}

			$this->assertInstanceOf( TemplateWrapper::class, $template );
		}
	}

	public function testDetailComponentsRenderWithMinimalPayloads() :void {
		$twig = $this->twig();

		$twig->render( '/wpadmin/components/page/shield_detail_row.twig', [
			'row' => [
				'status' => 'good',
				'title'  => 'Minimal Row',
			],
		] );

		$twig->render( '/wpadmin/components/page/shield_detail_expansion.twig', [
			'expansion' => [
				'id'   => 'exp-minimal',
				'body' => '<p>Body</p>',
			],
		] );

		$twig->render( '/wpadmin/components/page/shield_rail_sidebar.twig', [
			'rail' => [
				'items' => [
					[
						'key'    => 'summary',
						'label'  => 'Summary',
						'status' => 'good',
					],
				],
			],
		] );

		$twig->render( '/wpadmin/components/page/shield_rail_layout.twig', [
			'layout' => [
				'rail_html'    => '<div>Rail</div>',
				'content_html' => '<div>Content</div>',
			],
		] );

		$this->addToAssertionCount( 1 );
	}

	public function testDetailComponentsRenderWithExpandedVariantsAndDemoTemplate() :void {
		$twig = $this->twig();

		$twig->render( '/wpadmin/components/page/shield_detail_row.twig', [
			'row' => [
				'status'        => 'critical',
				'title'         => 'Complex Row',
				'description'   => 'Has actions and explanations',
				'count_badge'   => 2,
				'expandable'    => true,
				'expand_target' => 'exp-complex',
				'show_gear'     => true,
				'explanations'  => [ 'First explanation', 'Second explanation' ],
				'actions'       => [
					[
						'type'    => 'update',
						'label'   => 'Update now',
						'href'    => '#',
						'tooltip' => 'Update tooltip',
					],
					[
						'type'    => 'deactivate',
						'label'   => 'Deactivate',
						'href'    => '#',
						'tooltip' => 'Deactivate tooltip',
					],
				],
			],
		] );

		$twig->render( '/wpadmin/components/page/shield_detail_expansion.twig', [
			'expansion' => [
				'id'     => 'exp-options',
				'type'   => 'options',
				'status' => 'warning',
				'body'   => '<div>Option content</div>',
			],
		] );

		$twig->render( '/wpadmin/components/page/shield_rail_sidebar.twig', [
			'rail' => [
				'accent_status' => 'critical',
				'items'         => [
					[
						'key'       => 'summary',
						'label'     => 'Summary',
						'status'    => 'critical',
						'is_active' => true,
						'count'     => 3,
					],
					[
						'key'    => 'general',
						'label'  => 'General',
						'status' => 'neutral',
						'count'  => 1,
					],
				],
			],
		] );

		$twig->render( '/wpadmin/components/page/shield_detail_demo.twig' );

		$this->addToAssertionCount( 1 );
	}
}
