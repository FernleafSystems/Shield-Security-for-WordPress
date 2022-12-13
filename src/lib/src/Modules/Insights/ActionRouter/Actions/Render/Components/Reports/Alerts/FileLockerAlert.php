<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Reports\Alerts;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Plugin\PluginURLs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

class FileLockerAlert extends Actions\Render\Components\Reports\ReportsBuilderBase {

	use Actions\Traits\AuthNotRequired;

	public const PRIMARY_MOD = 'hack_protect';
	public const SLUG = 'alert_file_locker';
	public const TEMPLATE = '/components/reports/mod/hack_protect/alert_filelocker.twig';

	protected function getRenderData() :array {
		return [
			'hrefs'   => [
				'view_results' => $this->getCon()->plugin_urls->adminTop( PluginURLs::NAV_SCANS_RESULTS ),
			],
			'strings' => [
				'title'        => __( 'File Locker Changes Detected', 'wp-simple-firewall' ),
				'file_changed' => __( 'Changes have been detected in the contents of critical files.', 'wp-simple-firewall' ),
				'total_files'  => sprintf( '%s: %s', __( 'Total Changed Files', 'wp-simple-firewall' ), $this->action_data[ 'count_with_problems' ] ),
				'view_results' => __( 'Click Here To View File Locker Results', 'wp-simple-firewall' ),
			],
			'vars'    => [
//				'count' => $this->action_data[ 'count_with_problems' ]
			],
		];
	}

	protected function getRequiredDataKeys() :array {
		return [
			'count_not_notified',
			'count_with_problems',
		];
	}
}