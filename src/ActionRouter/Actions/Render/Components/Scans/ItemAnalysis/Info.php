<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ItemAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\HashVerificationResult;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\MalwareAssessmentPresenter;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-type FileStatus array{label:string,tone:string}
 */
class Info extends BaseComponent {

	public const SLUG = 'scanitemanalysis_info';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/modal/scan_item_analysis/file_info.twig';

	protected function getRenderData() :array {
		$item = $this->getScanItem();
		try {
			$data = [
				'flags'   => [
					'show_malai_status' => $item->is_mal
				],
				'vars'    => [
					'file_full_path'        => $item->path_full,
					'verdict_tone'          => $item->is_mal ? 'danger' : 'warning',
					'verdict_title'         => $this->getVerdictTitle(),
					'file_statuses'         => $this->getFileStatuses(),
					'belongs_to'            => $this->resolveSource()->label(),
					'file_description'      => $this->getFileDescriptionLines(),
					'recommendations_lines' => $this->getFileRecommendations(),
					'malware_assessment'    => ( new MalwareAssessmentPresenter() )->present(
						$item->is_mal ? $item->getMalwareRecord() : null,
						$item->is_mal && self::con()->caps->canScanMalwareMalai()
					),
				],
				'strings' => [
					'heading_malai_status' => __( 'Malware assessment', 'wp-simple-firewall' ),
					'malai_more'           => __( 'Why, and what this means', 'wp-simple-firewall' ),
					'belongs_to_label'     => __( 'Belongs to', 'wp-simple-firewall' ),
					'file_full_path_label' => __( 'Full path', 'wp-simple-firewall' ),
					'next_steps'           => __( 'What you can do', 'wp-simple-firewall' ),
					'review_changes'       => __( 'Review the changes', 'wp-simple-firewall' ),
					'view_contents'        => __( 'View file contents', 'wp-simple-firewall' ),
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
		$comparisonBasis = (string)$item->comparison_basis;
		$isPublishedReference = $comparisonBasis === HashVerificationResult::COMPARISON_BASIS_PUBLISHED_REFERENCE;
		$isLocalBaseline = $comparisonBasis === HashVerificationResult::COMPARISON_BASIS_LOCAL_BASELINE;
		$isMalwareOnly = $item->is_mal && !$item->hasNonMalwareFinding();

		$description = [];

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
			if ( $isMalwareOnly ) {
				$description[] = __( "It's located inside a WordPress plugin directory.", 'wp-simple-firewall' );
			}
			elseif ( $item->is_unrecognised ) {
				if ( $isPublishedReference ) {
					$description[] = __( "It's located inside a WordPress plugin directory, but it's not recognised as an official file for that plugin version.", 'wp-simple-firewall' );
				}
				elseif ( $isLocalBaseline ) {
					$description[] = __( "It's located inside a WordPress plugin directory, but it appeared since Shield created the baseline.", 'wp-simple-firewall' );
				}
				else {
					$description[] = __( "It's located inside a WordPress plugin directory, but it isn't recognised for that plugin version.", 'wp-simple-firewall' );
				}
			}
			elseif ( $item->is_checksumfail && !$isPublishedReference ) {
				$description[] = __( "It's located inside a WordPress plugin directory.", 'wp-simple-firewall' );
			}
			else {
				$description[] = __( "It's located in a WordPress plugin directory, and is a recognised as a valid file for that plugin version.", 'wp-simple-firewall' );
			}
		}
		elseif ( $item->is_in_theme ) {
			if ( $isMalwareOnly ) {
				$description[] = __( "It's located inside a WordPress theme directory.", 'wp-simple-firewall' );
			}
			elseif ( $item->is_unrecognised ) {
				if ( $isPublishedReference ) {
					$description[] = __( "It's located in a WordPress theme directory, but it's not recognised as an official file for that theme version.", 'wp-simple-firewall' );
				}
				elseif ( $isLocalBaseline ) {
					$description[] = __( "It's located in a WordPress theme directory, but it appeared since Shield created the baseline.", 'wp-simple-firewall' );
				}
				else {
					$description[] = __( "It's located in a WordPress theme directory, but it isn't recognised for that theme version.", 'wp-simple-firewall' );
				}
			}
			elseif ( $item->is_checksumfail && !$isPublishedReference ) {
				$description[] = __( "It's located inside a WordPress theme directory.", 'wp-simple-firewall' );
			}
			else {
				$description[] = __( "It's located in a WordPress theme directory, and is a recognised as a valid file for that theme version.", 'wp-simple-firewall' );
			}
		}

		if ( $item->is_checksumfail ) {
			if ( $item->is_in_core || $isPublishedReference ) {
				$description[] = __( 'When the current file contents were compared against the official release, changes were detected.', 'wp-simple-firewall' );
			}
			elseif ( $isLocalBaseline ) {
				$description[] = __( 'This file has changed since Shield created the baseline.', 'wp-simple-firewall' );
			}
			else {
				$description[] = __( 'Changes were detected in this file.', 'wp-simple-firewall' );
			}
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

	protected function resolveSource() :OriginalFileSource {
		return OriginalFileSource::forItem( $this->getScanItem() );
	}

	private function getVerdictTitle() :string {
		if ( $this->getScanItem()->is_mal ) {
			return __( 'Shield found a malware pattern in this file', 'wp-simple-firewall' );
		}
		switch ( $this->getFileFinding() ) {
			case 'unrecognised':
				return __( "This file isn't recognised", 'wp-simple-firewall' );
			case 'missing':
				return __( 'This file is missing', 'wp-simple-firewall' );
			case 'modified':
				return __( 'This file has been modified', 'wp-simple-firewall' );
			case 'unidentified':
				return __( "This file isn't a known WordPress file", 'wp-simple-firewall' );
			default:
				return __( 'This file needs review', 'wp-simple-firewall' );
		}
	}

	/**
	 * @return list<FileStatus>
	 */
	private function getFileStatuses() :array {
		$labels = [
			'unrecognised' => __( 'Unrecognised', 'wp-simple-firewall' ),
			'missing'      => __( 'Missing', 'wp-simple-firewall' ),
			'modified'     => __( 'Modified', 'wp-simple-firewall' ),
			'unidentified' => __( 'Unidentified', 'wp-simple-firewall' ),
		];

		$statuses = [];
		if ( $this->getScanItem()->is_mal ) {
			$statuses[] = [ 'label' => __( 'Malware pattern detected', 'wp-simple-firewall' ), 'tone' => 'danger' ];
		}
		$finding = $this->getFileFinding();
		if ( $finding !== '' ) {
			$statuses[] = [ 'label' => $labels[ $finding ], 'tone' => 'warning' ];
		}
		elseif ( empty( $statuses ) ) {
			$statuses[] = [ 'label' => __( 'Unknown', 'wp-simple-firewall' ), 'tone' => 'secondary' ];
		}
		return $statuses;
	}

	/**
	 * The file finding that leads the Overview, in precedence order.
	 * @return 'unrecognised'|'missing'|'modified'|'unidentified'|''
	 */
	private function getFileFinding() :string {
		$item = $this->getScanItem();
		if ( $item->is_unrecognised ) {
			return 'unrecognised';
		}
		if ( $item->is_missing ) {
			return 'missing';
		}
		if ( $item->is_checksumfail ) {
			return 'modified';
		}
		return $item->is_unidentified ? 'unidentified' : '';
	}
}
