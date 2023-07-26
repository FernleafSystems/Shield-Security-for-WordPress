<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\MeterAnalysis\Component;

abstract class ScanEnabledAfsAreaBase extends ScanEnabledBase {

	use Traits\OptConfigBased;

	public const MINIMUM_EDITION = 'starter';

	protected function getOptConfigKey() :string {
		return 'file_scan_areas';
	}
}