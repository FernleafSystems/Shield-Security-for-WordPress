<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;


abstract class ActivityLogBase extends BaseCmd {

	protected function getCmdBase() :array {
		return \array_merge( parent::getCmdBase(), [
			'activity-log'
		] );
	}
}
