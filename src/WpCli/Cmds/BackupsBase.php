<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

abstract class BackupsBase extends BaseCmd {

	protected function getCmdBase() :array {
		return \array_merge( parent::getCmdBase(), [
			'backups'
		] );
	}
}
