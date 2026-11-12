<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\ItemAnalysis;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Lib\Hashes\HashVerificationResult;
use FernleafSystems\Wordpress\Plugin\Shield\Scans\Afs\MalwareAssessmentPresenter;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-type FileStatus array{label:string,tone:string}
 * @phpstan-type MalwareOutcome array{title:string,tone:string,badge:string,badge_tone:string,harmless:bool,note:string,aside:string}
 */
class Info extends BaseComponent {

	public const SLUG = 'scanitemanalysis_info';
	public const TEMPLATE = '/wpadmin_pages/insights/scans/modal/scan_item_analysis/file_info.twig';

	protected function getRenderData() :array {
		$con = self::con();
		$item = $this->getScanItem();
		try {
			$assessment = $item->is_mal
				? ( new MalwareAssessmentPresenter() )->present( $item->getMalwareRecord(), $con->caps->canScanMalwareMalai() )
				: null;
			$malware = $assessment === null ? null : $this->getMalwareOutcome( $assessment );
			$verdict = $this->getVerdict( $malware );
			$data = [
				'flags'   => [
					'can_download' => Services::WpFs()->isAccessibleFile( $item->path_full ),
				],
				'hrefs'   => [
					'file_download' => $con->plugin_urls->fileDownload( 'scan_file', [ 'rid' => $item->VO->resultitem_id ] ),
				],
				'imgs'    => [
					'svgs' => [
						'file_download' => $con->svgs->iconClass( 'download.svg' ),
					],
				],
				'vars'    => [
					'file_full_path'        => $item->path_full,
					'verdict_tone'          => $verdict[ 'tone' ],
					'verdict_title'         => $verdict[ 'title' ],
					'verdict_note'          => $verdict[ 'note' ],
					'verdict_why'           => $assessment === null ? [] : [ $assessment[ 'explanation' ], $assessment[ 'guidance' ] ],
					'file_statuses'         => $this->getFileStatuses( $malware ),
					'belongs_to'            => $this->resolveSource()->label(),
					'file_description'      => $this->getFileDescriptionLines(),
					'recommendations_lines' => $this->getFileRecommendations(),
				],
				'strings' => [
					'malai_more'           => __( 'Why, and what this means', 'wp-simple-firewall' ),
					'belongs_to_label'     => __( 'Belongs to', 'wp-simple-firewall' ),
					'file_full_path_label' => __( 'Full path', 'wp-simple-firewall' ),
					'next_steps'           => __( 'Next actions you can take', 'wp-simple-firewall' ),
					'review_changes'       => __( 'Review the changes', 'wp-simple-firewall' ),
					'view_contents'        => __( 'View file contents', 'wp-simple-firewall' ),
					'file_download'        => __( 'Download file', 'wp-simple-firewall' ),
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

	/**
	 * What a malware pattern match amounts to once assessed, for the headline and the badge: a match the assessment
	 * calls clean shouldn't read as malware, and is harmless enough for another finding to lead. A settled outcome
	 * says it all in its headline; an unsettled one has a note saying where the assessment stands. The aside is what
	 * a harmless match adds beneath another finding's headline.
	 * @param array{assessment_key:string,summary:string} $assessment
	 * @return MalwareOutcome
	 */
	private function getMalwareOutcome( array $assessment ) :array {
		switch ( $assessment[ 'assessment_key' ] ) {
			case 'malware':
				return [
					'title'      => __( 'Shield found malware in this file', 'wp-simple-firewall' ),
					'tone'       => 'danger',
					'badge'      => __( 'Malware', 'wp-simple-firewall' ),
					'badge_tone' => 'danger',
					'harmless'   => false,
					'note'       => '',
					'aside'      => '',
				];
			case 'likely_malware':
				return [
					'title'      => __( 'This file probably contains malware', 'wp-simple-firewall' ),
					'tone'       => 'danger',
					'badge'      => __( 'Malware', 'wp-simple-firewall' ),
					'badge_tone' => 'danger',
					'harmless'   => false,
					'note'       => '',
					'aside'      => '',
				];
			case 'clean':
				return [
					'title'      => __( 'This file matched a malware pattern, but it is clean', 'wp-simple-firewall' ),
					'tone'       => 'success',
					'badge'      => __( 'Pattern match: clean', 'wp-simple-firewall' ),
					'badge_tone' => 'success',
					'harmless'   => true,
					'note'       => '',
					'aside'      => __( 'It also matched a malware pattern, but it is clean.', 'wp-simple-firewall' ),
				];
			case 'likely_clean':
				return [
					'title'      => __( 'This file matched a malware pattern, but is most likely clean', 'wp-simple-firewall' ),
					'tone'       => 'warning',
					'badge'      => __( 'Pattern match: likely clean', 'wp-simple-firewall' ),
					'badge_tone' => 'secondary',
					'harmless'   => true,
					'note'       => '',
					'aside'      => __( 'It also matched a malware pattern, but is most likely clean.', 'wp-simple-firewall' ),
				];
			default:
				return [
					'title'      => __( 'This file matched a malware pattern and needs review', 'wp-simple-firewall' ),
					'tone'       => 'danger',
					'badge'      => __( 'Malware pattern match', 'wp-simple-firewall' ),
					'badge_tone' => 'danger',
					'harmless'   => false,
					'note'       => $assessment[ 'summary' ],
					'aside'      => '',
				];
		}
	}

	/**
	 * The headline follows the malware outcome, unless the match is harmless and the file has another finding,
	 * such as a modification, which then leads with the match noted beneath it.
	 * @param MalwareOutcome|null $malware
	 * @return array{title:string,tone:string,note:string}
	 */
	private function getVerdict( ?array $malware ) :array {
		if ( $malware === null ) {
			return [ 'title' => $this->getFileFindingTitle(), 'tone' => 'warning', 'note' => '' ];
		}
		return $malware[ 'harmless' ] && $this->getFileFinding() !== ''
			? [ 'title' => $this->getFileFindingTitle(), 'tone' => 'warning', 'note' => $malware[ 'aside' ] ]
			: [ 'title' => $malware[ 'title' ], 'tone' => $malware[ 'tone' ], 'note' => $malware[ 'note' ] ];
	}

	private function getFileFindingTitle() :string {
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
	 * @param MalwareOutcome|null $malware
	 * @return list<FileStatus>
	 */
	private function getFileStatuses( ?array $malware ) :array {
		$labels = [
			'unrecognised' => __( 'Unrecognised', 'wp-simple-firewall' ),
			'missing'      => __( 'Missing', 'wp-simple-firewall' ),
			'modified'     => __( 'Modified', 'wp-simple-firewall' ),
			'unidentified' => __( 'Unidentified', 'wp-simple-firewall' ),
		];

		$statuses = [];
		if ( $malware !== null ) {
			$statuses[] = [ 'label' => $malware[ 'badge' ], 'tone' => $malware[ 'badge_tone' ] ];
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
