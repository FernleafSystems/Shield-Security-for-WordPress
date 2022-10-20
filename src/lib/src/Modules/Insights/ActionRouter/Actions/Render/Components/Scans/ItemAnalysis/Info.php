<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Scans\ItemAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Wp\Repo;

class Info extends Base {

	const SLUG = 'scanitemanalysis_info';
	const TEMPLATE = '/wpadmin_pages/insights/scans/modal/scan_item_analysis/file_info.twig';

	protected function getRenderData() :array {
		$WP = Services::WpGeneral();
		$item = $this->getScanItem();
		$isCore = Services::CoreFileHashes()->isCoreFile( $item->path_fragment );
		try {
			$data = [
				'flags'   => [
					'is_core_file' => true,
				],
				'hrefs'   => [
					'file_vcs' => $isCore ?
						( new Repo() )->getVcsUrlForFileAndVersion( $item->path_fragment, $WP->getVersion(), false ) : ''
				],
				'vars'    => [
					'path_fragment'    => $item->path_fragment,
					'file_description' => $this->getFileDescriptionLines()
				],
				'strings' => [
					'file_status'      => sprintf( '%s: %s',
						__( 'File Status', 'wp-simple-firewall' ),
						$this->getFileStatus()
					),
					'file_full_path'   => sprintf( '%s: <code>%s</code>',
						__( 'Full Path To File', 'wp-simple-firewall' ),
						$item->path_full
					),
					'file_description' => __( 'Description', 'wp-simple-firewall' ),
					'view_file_vcs'    => __( 'View Original File Contents', 'wp-simple-firewall' ),
				],
			];
		}
		catch ( \Exception $e ) {
			throw new ActionException( $e->getMessage() );
		}

		return Services::DataManipulation()->mergeArraysRecursive( parent::getRenderData(), $data );
	}

	/**
	 * @throws ActionException
	 */
	private function getFileDescriptionLines() :array {
		$item = $this->getScanItem();

		$description = [];
		if ( $item->is_in_core ) {
			if ( $item->is_unrecognised ) {
				$description[] = sprintf(
					__( "This file is located in a core WordPress directory isn't a recognised core file for WordPress %s.", 'wp-simple-firewall' ),
					Services::WpGeneral()->getVersion()
				);
			}
			else {
				$description[] = sprintf(
					__( 'This is a recognised core file for WordPress %s.', 'wp-simple-firewall' ),
					Services::WpGeneral()->getVersion()
				);
			}
		}
		elseif ( $item->is_in_plugin ) {
			if ( $item->is_unrecognised ) {
				$description[] = __( "It's located inside a WordPress plugin directory, but it's not recognised as an official file for that plugin version.", 'wp-simple-firewall' );
			}
			else {
				$description[] = __( "It's located in a WordPress plugin directory, and is a recognised as a valid file for that plugin version.", 'wp-simple-firewall' );
			}
		}
		elseif ( $item->is_in_theme ) {
			if ( $item->is_unrecognised ) {
				$description[] = __( "It's located in a WordPress theme directory, but it's not recognised as an official file for that theme version.", 'wp-simple-firewall' );
			}
			else {
				$description[] = __( "It's located in a WordPress theme directory, and is a recognised as a valid file for that theme version.", 'wp-simple-firewall' );
			}
		}
		if ( $item->is_checksumfail ) {
			$description[] = __( 'File contents have been modified when compared against the official release for that version.', 'wp-simple-firewall' );
		}
		if ( $item->is_mal ) {
			$description[] = __( 'Contents could potentially contain malicious PHP malware.', 'wp-simple-firewall' );
			$description[] = sprintf( __( 'The false positive score of this file is %s.', 'wp-simple-firewall' ),
				sprintf( '<code>%s</code>', $item->mal_fp_confidence ) );
			$description[] = __( "The lower the score the less we know about the file or the more likely it contains malicious code.", 'wp-simple-firewall' );
		}

		return $description;
	}

	/**
	 * @throws ActionException
	 */
	private function getFileStatus() :string {
		$item = $this->getScanItem();

		$status = [];
		if ( $item->is_mal ) {
			$status[] = __( 'Potential Malware', 'wp-simple-firewall' );
		}

		if ( $item->is_unrecognised ) {
			$status[] = __( 'Unrecognised', 'wp-simple-firewall' );
		}
		elseif ( $item->is_missing ) {
			$status[] = __( 'Missing', 'wp-simple-firewall' );
		}
		elseif ( $item->is_checksumfail ) {
			$status[] = __( 'Modified', 'wp-simple-firewall' );
		}
		else {
			$status[] = __( 'Unknown', 'wp-simple-firewall' );
		}

		return implode( ' / ', $status );
	}
}