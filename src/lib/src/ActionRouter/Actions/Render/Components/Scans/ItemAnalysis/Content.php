<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ItemAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\ConvertLineEndings;

class Content extends Base {

	public const SLUG = 'scanitemanalysis_content';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/modal/scan_item_analysis/file_content.twig';

	protected function getRenderData() :array {
		$path = \path_join( ABSPATH, $this->getScanItem()->path_fragment );
		$FS = Services::WpFs();
		if ( !$FS->isAccessibleFile( $path ) ) {
			throw new ActionException( 'File does not exist.' );
		}

		$contents = $FS->getFileContent( $path );
		if ( empty( $contents ) ) {
			throw new ActionException( 'File is empty or could not be read.' );
		}

		return [
			'lines' => \explode( "\n",
				\str_replace( "\t", "    ", ( new ConvertLineEndings() )->fileDosToLinux( $path ) )
			),
		];
	}
}