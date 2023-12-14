<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string $path_dir
 * @property string $file_name
 * @property bool   $fuzzy
 */
class DirContainsFile extends Base {

	use Traits\RequestIP;
	use Traits\TypeFilesystem;

	public const SLUG = 'dir_contains_file';

	public function getDescription() :string {
		return __( 'Does a given file exist in a given directory.', 'wp-simple-firewall' );
	}

	public function getParamsDef() :array {
		return [
			'path_dir'  => [
				'type'  => 'string',
				'label' => __( 'Path Dir', 'wp-simple-firewall' ),
			],
			'file_name' => [
				'type'  => 'string',
				'label' => __( 'File Name', 'wp-simple-firewall' ),
			],
			'fuzzy'     => [
				'type'    => 'bool',
				'default' => true,
				'label'   => __( 'Fuzzy Search', 'wp-simple-firewall' ),
			],
		];
	}

	protected function execConditionCheck() :bool {
		$FS = Services::WpFs();

		$dir = $this->path_dir;
		$file = $this->file_name;

		$result = false;
		if ( !empty( $dir ) && !empty( $file ) && $FS->isAccessibleDir( $dir ) ) {
			if ( !isset( $this->fuzzy ) || $this->fuzzy ) {
				$foundFile = Services::WpFs()->findFileInDir( $file, $dir, false );
				$result = !empty( $foundFile );
			}
			else {
				$result = Services::WpFs()->isAccessibleFile( path_join( $dir, $file ) );
			}
		}

		return $result;
	}
}