<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\ActiveWpUserConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AnyUserAuthRequired;

abstract class MfaUserConfigBase extends BaseAction {

	use ActiveWpUserConsumer;
	use AnyUserAuthRequired;
}