<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

abstract class PluginImportExport_Base extends PluginBase {

	use Traits\AuthNotRequired;
	use Traits\NonceVerifyNotRequired;
}