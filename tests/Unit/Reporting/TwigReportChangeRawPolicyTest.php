<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Reporting;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\PluginPathsTrait;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class TwigReportChangeRawPolicyTest extends BaseUnitTest {

	use PluginPathsTrait;

	public function test_report_change_templates_do_not_use_raw_filters() :void {
		$violations = [];

		foreach ( $this->reportChangeTemplates() as $template ) {
			$content = \file_get_contents( $this->getPluginFilePath( $template ) );
			$this->assertIsString( $content );

			if ( \strpos( $content, '|raw' ) !== false ) {
				$violations[] = $template;
			}
		}

		$this->assertSame( [], $violations );
	}

	private function reportChangeTemplates() :array {
		return [
			'templates/twig/reports/areas/changes/zone_diff.twig',
			'templates/twig/email/reports/areas/changes.twig',
		];
	}
}
