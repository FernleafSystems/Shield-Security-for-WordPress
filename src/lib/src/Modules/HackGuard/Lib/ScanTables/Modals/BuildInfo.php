<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\ScanTables\Modals;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Scans;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\{
	Wp
};

class BuildInfo {

	use ModConsumer;

	/**
	 * @param Scans\Base\FileResultItem $item
	 * @return string
	 * @throws \Exception
	 */
	public function run( $item ) :string {
		/** @var ModCon $mod */
		$mod = $this->getMod();
		$WP = Services::WpGeneral();

		$isCoreFile = Services::CoreFileHashes()->isCoreFile( $item->path_fragment );

		return $this->getMod()->renderTemplate(
			'/wpadmin_pages/insights/scans/modal/scan_item_view/item_info.twig',
			[
				'flags'   => [
					'is_core_file' => true,
				],
				'hrefs'   => [
					'file_vcs' => $isCoreFile ?
						( new Wp\Repo() )
							->getVcsUrlForFileAndVersion( $item->path_fragment, $WP->getVersion(), false )
						: ''
				],
				'vars'    => [
					'path_fragment' => $item->path_fragment,
				],
				'strings' => [
					'file_status'       => sprintf( '%s: %s',
						__( 'Current File Status', 'wp-simple-firewall' ),
						$this->getItemFileStatus( $item )
					),
					'file_full_path'    => sprintf( '%s: <code>%s</code>',
						__( 'Full Path To File', 'wp-simple-firewall' ),
						$item->path_full
					),
					'this_is_core_file' => sprintf(
						__( 'This is a Core WordPress file for your version (%s) of WordPress.', 'wp-simple-firewall' ),
						$WP->getVersion()
					),
					'view_file_vcs'     => __( 'View Original File Contents', 'wp-simple-firewall' ),
				],
			]
		);
	}

	/**
	 * @param Scans\Base\ResultItem $item
	 * @return string
	 */
	private function getItemFileStatus( $item ) :string {
		if ( $item->is_unrecognised ) {
			$status = __( 'Unrecognised', 'wp-simple-firewall' );
		}
		elseif ( $item->is_mal ) {
			$status = __( 'Potential Malware', 'wp-simple-firewall' );
		}
		elseif ( $item->is_missing ) {
			$status = __( 'Missing', 'wp-simple-firewall' );
		}
		elseif ( $item->is_checksumfail ) {
			$status = __( 'Modified', 'wp-simple-firewall' );
		}
		else {
			$status = __( 'Unknown', 'wp-simple-firewall' );
		}
		return $status;
	}
}