<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ItemAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\MalwareStatus;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\Processing\RetrieveMalwareMalaiStatus;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\WpOrg\Wp\Repo;

class Info extends Base {

	public const SLUG = 'scanitemanalysis_info';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/modal/scan_item_analysis/file_info.twig';

	protected function getRenderData() :array {
		$WP = Services::WpGeneral();
		$item = $this->getScanItem();
		$isCore = Services::CoreFileHashes()->isCoreFile( $item->path_fragment );
		try {
			$data = [
				'flags'   => [
					'show_malai_status' => $item->is_mal
				],
				'hrefs'   => [
					'file_vcs' => $isCore ?
						( new Repo() )->getVcsUrlForFileAndVersion( $item->path_fragment, $WP->getVersion(), false ) : ''
				],
				'vars'    => [
					'path_fragment'    => $item->path_fragment,
					'file_description' => $this->getFileDescriptionLines(),
				],
				'strings' => [
					'info'                  => __( 'Info' ),
					'heading_malai_status'  => sprintf( __( 'Malware status report from %s' ), 'MAL{ai} ' ),
					'malware_status_of'     => __( 'Malware status of this file is currently', 'wp-simple-firewall' ),
					'malai_status'          => $this->getMalaiStatus(),
					'malai_status_notes'    => [
						__( "[Known] means that the code has been reviewed and is known and confirmed to be either clean or malware." ),
						__( "[False Positive] means the code looks like malware, but it's actually clean." ),
						__( "[Predicted] means the clean/malware status has been assessed by the MAL{ai} engine, but hasn't been manually reviewed (yet)." ),
					],
					'file_status'           => sprintf( '%s: %s',
						__( 'File Status', 'wp-simple-firewall' ),
						$this->getFileStatus()
					),
					'file_full_path'        => sprintf( '%s: <code>%s</code>',
						__( 'Full Path To File', 'wp-simple-firewall' ),
						$item->path_full
					),
					'note'                  => __( 'Note', 'wp-simple-firewall' ),
					'file_description'      => __( 'Description', 'wp-simple-firewall' ),
					'recommendations'       => __( 'Recommendations', 'wp-simple-firewall' ),
					'recommendations_lines' => $this->getFileRecommendations(),
					'view_file_vcs'         => __( 'View Original File Contents', 'wp-simple-firewall' ),
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
	private function getMalaiStatus() :string {
		$item = $this->getScanItem();
		if ( $item->is_mal && !empty( $item->getMalwareRecord() ) ) {
			$status = ( new MalwareStatus() )->nameFromStatusLabel(
				( new RetrieveMalwareMalaiStatus() )->single( $item->getMalwareRecord() )
			);
		}
		else {
			$status = '';
		}

		return $status;
	}

	/**
	 * @throws ActionException
	 */
	private function getFileDescriptionLines() :array {
		$item = $this->getScanItem();

		$description = [];

		if ( $item->is_mal ) {
			$record = $item->getMalwareRecord();
			if ( $record->malai_status === MalwareStatus::STATUS_MALWARE ) {
				$description[] = sprintf( '<span class="text-danger">%s</span>',
					__( "This file contains malicious code - it's malware!", 'wp-simple-firewall' ).
					' '.__( 'Please take remedial action as soon as possible.', 'wp-simple-firewall' )
				);
			}
			elseif ( $record->malai_status === MalwareStatus::STATUS_CLEAN ) {
				$description[] = sprintf( '<span class="text-success">%s</span>',
					__( 'This file is confirmed to be clean and free from malware.', 'wp-simple-firewall' )
				);
			}
			elseif ( $record->malai_status === MalwareStatus::STATUS_FP ) {
				$description[] = sprintf( '<span class="text-success">%s</span>',
					__( "This file is confirmed a malware false positive - it contains code that looks like malware, but it is clean.", 'wp-simple-firewall' )
				);
			}
			else {
				$description[] = sprintf( '<span class="text-warning">%s</span>',
					__( 'This file is triggers the malware scanner but the status is not confirmed.', 'wp-simple-firewall' )
					.' '.__( 'Please take a moment to review the contents of the file as it may contain malicious code.', 'wp-simple-firewall' ) );
			}
		}

		if ( $item->is_in_core ) {
			if ( $item->is_unrecognised ) {
				$description[] = sprintf(
					__( "This file is located in a core WordPress directory isn't a recognised core file for WordPress %s.", 'wp-simple-firewall' ),
					Services::WpGeneral()->getVersion()
				);
			}
			elseif ( $item->is_unidentified ) {

				if ( $item->is_in_wproot ) {
					$description[] = __( "This file is located at the root of the WordPress installation directory.", 'wp-simple-firewall' );
					$description[] = sprintf(
						__( "This file isn't a known core file for WordPress %s.", 'wp-simple-firewall' ),
						Services::WpGeneral()->getVersion()
					);
				}
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
			$description[] = __( 'When the current file contents were compared against the official release, changes were detected.', 'wp-simple-firewall' );
		}

		return $description;
	}

	/**
	 * @throws ActionException
	 */
	private function getFileRecommendations() :array {
		$item = $this->getScanItem();

		$recs = [];

		if ( $item->is_unidentified && $item->is_in_wproot ) {
			$recs[] = __( "Check and verify the contents of this file to ensure it's not malicious.", 'wp-simple-firewall' );
			$recs[] = __( "We recommend the WP root directory stays clean by keeping only official WordPress core files there.", 'wp-simple-firewall' );
		}

		return $recs;
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
		elseif ( $item->is_unidentified ) {
			$status[] = __( 'Unidentified', 'wp-simple-firewall' );
		}
		elseif ( empty( $status ) ) {
			$status[] = __( 'Unknown', 'wp-simple-firewall' );
		}

		return \implode( ' / ', $status );
	}
}