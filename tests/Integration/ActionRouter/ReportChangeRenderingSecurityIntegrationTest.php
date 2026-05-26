<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use DOMDocument;
use DOMXPath;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Components\ReportAreaChanges;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Contexts\EmailReportInfo;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\{
	Constants,
	ReportVO
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Email\Support\BuildReportEmailFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class ReportChangeRenderingSecurityIntegrationTest extends ShieldIntegrationTestCase {

	use BuildReportEmailFixture;

	public function test_web_report_changes_escape_malicious_change_values() :void {
		$report = $this->buildReportFixture( Constants::REPORT_TYPE_INFO );
		$this->replaceChangesAreaData( $report );

		$html = $this->requireController()->action_router->render( ReportAreaChanges::class, [
			'report' => $report->getRawData(),
		] );

		$this->assertRenderedHtmlHasNoExecutablePayloads( $html );
	}

	public function test_email_report_changes_escape_malicious_change_values() :void {
		$report = $this->buildReportFixture( Constants::REPORT_TYPE_INFO );
		$this->replaceChangesAreaData( $report );

		$html = $this->requireController()->action_router->render( EmailReportInfo::class, [
			'home_url'     => 'https://example.com',
			'report'       => $report,
			'detail_level' => 'detailed',
		] );

		$this->assertRenderedHtmlHasNoExecutablePayloads( $html );
	}

	private function maliciousChangesAreaData() :array {
		$payload = '<script>alert(1)</script><img src=x onerror=alert(1)>';

		return [
			'plugins' => [
				'title'    => 'Plugins',
				'total'    => 1,
				'detailed' => [
					[
						'uniq' => 'malicious-plugin',
						'name' => 'Plugin '.$payload,
						'link' => [],
						'rows' => [
							[
								'lines'       => [
									'Updated from 1.0 '.$payload,
									'Automatic update '.$payload,
								],
								'count'       => 2,
								'detail_time' => 'Time '.$payload,
								'detail_who'  => 'User '.$payload,
							],
						],
					],
				],
			],
		];
	}

	private function replaceChangesAreaData( ReportVO $report ) :void {
		$areasData = $report->areas_data;
		$areasData[ Constants::REPORT_AREA_CHANGES ] = $this->maliciousChangesAreaData();
		$report->areas_data = $areasData;
	}

	private function assertRenderedHtmlHasNoExecutablePayloads( string $html ) :void {
		$xpath = $this->xpathForHtml( $html );

		$this->assertSame( 0, $xpath->query( '//script' )->length );
		foreach ( $xpath->query( '//@*' ) as $attribute ) {
			$name = \strtolower( $attribute->nodeName );
			$value = \trim( $attribute->nodeValue );
			$this->assertFalse( \strpos( $name, 'on' ) === 0, 'Inline event handler rendered: '.$attribute->nodeName );
			$this->assertFalse(
				$name === 'href' && \stripos( $value, 'javascript:' ) === 0,
				'javascript: URL rendered: '.$value
			);
		}
	}

	private function xpathForHtml( string $html ) :DOMXPath {
		$doc = new DOMDocument();
		$previous = \libxml_use_internal_errors( true );
		try {
			$doc->loadHTML( '<!doctype html><html><body>'.$html.'</body></html>' );
		}
		finally {
			\libxml_clear_errors();
			\libxml_use_internal_errors( $previous );
		}
		return new DOMXPath( $doc );
	}
}
