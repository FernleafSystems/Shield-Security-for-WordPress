<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\HiddenPlugins;

enum PhpFileActivity :string {
	case Inert = 'inert';
	case Executable = 'executable';
	case Unreadable = 'unreadable';
	case Invalid = 'invalid';

	public function isAlertable() :bool {
		return $this !== self::Inert;
	}
}
