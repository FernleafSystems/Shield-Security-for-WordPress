<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Services\Services;

class AccessiblePathExists extends Base {

	use Traits\TypeFilesystem;

	public const SLUG = 'accessible_path_exists';

	protected function execConditionCheck() :bool {
		$FS = Services::WpFs();
		return $FS->isAccessibleDir( $this->p->match_path ) || $FS->isAccessibleFile( $this->p->match_path );
	}

	public function getDescription() :string {
		return __( 'Does a given path (file or directory) exist.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'match_path' => [
				'type'  => EnumParameters::TYPE_STRING,
				'label' => __( 'Path To Check', 'wp-simple-firewall' ),
			],
		];
	}
}