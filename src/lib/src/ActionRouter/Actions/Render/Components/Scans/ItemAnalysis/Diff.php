<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ItemAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\File\ConvertLineEndings;
use FernleafSystems\Wordpress\Services\Utilities\Integrations\WpHashes\Util\Diff as DiffUtil;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg;

class Diff extends Base {

	public const SLUG = 'scanitemanalysis_diff';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/modal/scan_item_analysis/file_diff.twig';

	protected function getRenderData() :array {
		$item = $this->getScanItem();
		$pathFull = empty( $item->path_full ) ? path_join( ABSPATH, $item->path_fragment ) : $item->path_full;

		if ( $item->is_missing || !Services::WpFs()->isAccessibleFile( $pathFull ) ) {
			throw new ActionException( 'Diff is unavailable for missing files.' );
		}

		$coreHashes = Services::CoreFileHashes();
		if ( $coreHashes->isCoreFile( $pathFull ) ) {
			$originalFileDownload = ( new WpOrg\Wp\Files() )->getOriginalFileFromVcs( $pathFull );
		}
		else {
			$plugin = ( new WpOrg\Plugin\Files() )->findPluginFromFile( $pathFull );
			if ( !empty( $plugin ) && $plugin->isWpOrg() && $plugin->svn_uses_tags ) {
				$originalFileDownload = ( new WpOrg\Plugin\Files() )->getOriginalFileFromVcs( $pathFull );
			}
			else {
				$theme = ( new WpOrg\Theme\Files() )->findThemeFromFile( $pathFull );
				if ( !empty( $theme ) && $theme->isWpOrg() ) {
					$originalFileDownload = ( new WpOrg\Theme\Files() )->getOriginalFileFromVcs( $pathFull );
				}
			}
		}

		if ( empty( $originalFileDownload ) || !Services::WpFs()->isAccessibleFile( $originalFileDownload ) ) {
			throw new ActionException( "A File Diff can't be created as there is no official file available for us to compare with." );
		}

		$conv = new ConvertLineEndings();
		$res = ( new DiffUtil() )->getDiff(
			$conv->dosToLinux( (string)Services::WpFs()->getFileContent( $originalFileDownload ) ),
			$conv->fileDosToLinux( $pathFull )
		);

		if ( !\is_array( $res ) || empty( $res[ 'html' ] ) ) {
			throw new ActionException( 'Could not get a valid diff for this file.' );
		}

		return [
			'default_css' => \base64_decode( $res[ 'html' ][ 'css_default' ] ),
			'content'     => \base64_decode( $res[ 'html' ][ 'content' ] ),
		];
	}
}