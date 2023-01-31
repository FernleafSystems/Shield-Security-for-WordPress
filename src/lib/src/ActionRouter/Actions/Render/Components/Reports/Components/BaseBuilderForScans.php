<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Components;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;

abstract class BaseBuilderForScans extends BaseBuilder {

	public const PRIMARY_MOD = 'hack_protect';
	public const TYPE = Constants::REPORT_TYPE_ALERT;
}