<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;

class ReportAreaChanges extends ReportAreaBase {

	public const SLUG = 'report_area_changes';
	public const TEMPLATE = '/reports/areas/changes/main.twig';

	protected function getRenderData() :array {

		$changes = \array_filter(
			$this->report()->areas_data[ Constants::REPORT_AREA_CHANGES ],
			function ( array $zone ) {
				// Only display zones that have changes
				return isset( $zone[ 'title' ], $zone[ 'total' ], $zone[ 'detailed' ] )
					   && \is_array( $zone[ 'detailed' ] )
					   && (int)$zone[ 'total' ] > 0
					   && !empty( $zone[ 'detailed' ] );
			}
		);

		return [
			'flags' => [
				'has_diffs' => !empty( $changes ),
			],
			'vars'  => [
				'changes' => $changes,
			],
		];
	}
}
