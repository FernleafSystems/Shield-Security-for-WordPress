<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Enum\EnumParameters;
use FernleafSystems\Wordpress\Services\Services;

class DirContainsFile extends Base {

	use Traits\TypeFilesystem;

	public const SLUG = 'dir_contains_file';

	protected function execConditionCheck() :bool {
		$FS = Services::WpFs();

		$dir = $this->p->path_dir;
		$file = $this->p->path_basename;

		$result = false;
		if ( !empty( $dir ) && !empty( $file ) && $FS->isAccessibleDir( $dir ) ) {
			$result = $this->p->is_fuzzy_search ?
				!empty( $FS->findFileInDir( $file, $dir, false ) )
				: $FS->isAccessibleFile( path_join( $dir, $file ) );
		}

		return $result;
	}

	public function getDescription() :string {
		return __( 'Does a given file exist in a given directory.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'path_dir'        => [
				'type'  => EnumParameters::TYPE_STRING,
				'label' => __( 'Path Dir', 'wp-simple-firewall' ),
			],
			'path_basename'   => [
				'type'  => EnumParameters::TYPE_STRING,
				'label' => __( 'File Name', 'wp-simple-firewall' ),
			],
			'is_fuzzy_search' => [
				'type'    => EnumParameters::TYPE_BOOL,
				'default' => true,
				'label'   => __( 'Fuzzy Search', 'wp-simple-firewall' ),
			],
		];
	}
}