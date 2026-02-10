<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ItemAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\ConvertLineEndings;
use FernleafSystems\Wordpress\Services\Utilities\File\Paths;

class Content extends BaseComponent {

	public const SLUG = 'scanitemanalysis_content';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/modal/scan_item_analysis/file_content.twig';

	protected function getRenderData() :array {
		$path = \path_join( ABSPATH, $this->getScanItem()->path_fragment );
		$FS = Services::WpFs();
		if ( !$FS->isAccessibleFile( $path ) ) {
			throw new ActionException( __( 'File does not exist.', 'wp-simple-firewall' ) );
		}

		$contents = $FS->getFileContent( $path );
		if ( empty( $contents ) ) {
			throw new ActionException( __( 'File is empty or could not be read.', 'wp-simple-firewall' ) );
		}

		return [
			'code_language' => $this->getCodeLanguage( $path ),
			'lines' => \explode( "\n",
				\str_replace( "\t", "    ", ( new ConvertLineEndings() )->fileDosToLinux( $path ) )
			),
		];
	}

	private function getCodeLanguage( string $path ) :string {
		$ext = \strtolower( Paths::Ext( $path ) );

		switch ( $ext ) {
			case 'php5':
			case 'php7':
			case 'phtml':
			case 'phtm':
				$language = 'php';
				break;
			case 'js':
			case 'mjs':
			case 'cjs':
				$language = 'javascript';
				break;
			case 'html':
			case 'htm':
			case 'svg':
				$language = 'xml';
				break;
			case 'sh':
				$language = 'bash';
				break;
			default:
				$language = $ext;
				break;
		}

		return $language;
	}
}
