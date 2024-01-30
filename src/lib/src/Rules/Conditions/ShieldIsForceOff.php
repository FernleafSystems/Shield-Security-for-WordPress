<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class ShieldIsForceOff extends Base {

	use Traits\TypeShield;

	public const SLUG = 'is_force_off';

	public function getDescription() :string {
		return __( 'Is the Shield plugin in "forceoff" state.', 'wp-simple-firewall' );
	}

	protected function getPreviousResult() :?bool {
		return $this->req->is_force_off;
	}

	protected function postExecConditionCheck( bool $result ) :void {
		$this->req->is_force_off = $result;
	}

	protected function getSubConditions() :array {
		return [
			'conditions' => DirContainsFile::class,
			'params'     => [
				'path_dir'        => self::con()->getRootDir(),
				'path_basename'   => 'forceoff',
				'is_fuzzy_search' => true,
			]
		];
	}
}