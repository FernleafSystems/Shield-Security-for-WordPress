<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use Twig\{
	Environment,
	Loader\FilesystemLoader
};

class ScanItemAnalysisInfoTwigTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function test_file_path_and_status_are_escaped() :void {
		$fragmentPayload = 'wp-content/<script>alert(1)</script>.php';
		$pathPayload = '/srv/www/<img src=x onerror=alert(1)>.php';
		$statusPayload = '<script>alert(1)</script>';

		$html = '<div>'.$this->twig()->render(
			'/wpadmin_pages/insights/scans/modal/scan_item_analysis/file_info.twig',
			[
				'flags'   => [
					'show_malai_status' => false,
				],
				'strings' => [
					'info'                  => 'info-label',
					'file_full_path_label'  => 'path-label',
					'file_status_label'     => 'status-label',
					'file_description'      => 'description-label',
					'recommendations'       => 'recommendations-label',
				],
				'vars'    => [
					'path_fragment'         => $fragmentPayload,
					'file_full_path'        => $pathPayload,
					'file_status'           => $statusPayload,
					'file_description'      => [],
					'recommendations_lines' => [],
				],
			]
		).'</div>';
		$xpath = $this->createDomXPathFromHtml( $html );

		$this->assertSame( 0, $xpath->query( '//script' )->length );
		$this->assertSame( 0, $xpath->query( '//img' )->length );
		$this->assertSame( 1, $xpath->query( '//h4/span/text()[. = "'.$fragmentPayload.'"]' )->length );
		$this->assertSame( 1, $xpath->query( '//code/text()[. = "'.$pathPayload.'"]' )->length );
		$this->assertSame( 1, $xpath->query( '//li/text()[contains(., "'.$statusPayload.'")]' )->length );
	}

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

	private function createDomXPathFromHtml( string $html ) :\DOMXPath {
		$doc = new \DOMDocument();
		$previous = \libxml_use_internal_errors( true );
		try {
			$doc->loadHTML(
				'<?xml encoding="utf-8" ?>'.$html,
				\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
			);
		}
		finally {
			\libxml_clear_errors();
			\libxml_use_internal_errors( $previous );
		}

		return new \DOMXPath( $doc );
	}
}
