<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Merlin\Steps;

use FernleafSystems\Wordpress\Plugin\Shield\Profiles\CompareLevelWithCurrent;
use FernleafSystems\Wordpress\Plugin\Shield\Profiles\Levels;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Response;

class ApplySecurityProfile extends Base {

	public const SLUG = 'apply_security_profile';

	public function processStepFormSubmit( array $form ) :Response {
		$resp = parent::processStepFormSubmit( $form );
		$con = self::con();

		$level = \strtolower( $form[ 'security_profile' ] ?? '' );
		if ( empty( $level ) ) {
			$resp->success = true;
			$resp->message = __( 'No profile was applied', 'wp-simple-firewall' );
		}
		else {
			try {
				$con->comps->security_profiles->applyLevel( $level );
				$resp->success = true;
				$resp->message = sprintf( __( "Profile '%s' was applied.", 'wp-simple-firewall' ), $con->comps->security_profiles->meta( $level )[ 'title' ] );
			}
			catch ( \Exception $e ) {
				error_log( $e->getMessage() );
				$resp->success = false;
				$resp->message = $resp->error = __( 'An unsupported profile was selected', 'wp-simple-firewall' );
			}
		}
		return $resp;
	}

	public function getName() :string {
		return __( 'Security Profile', 'wp-simple-firewall' );
	}

	/**
	 * Each level is shown with what it would change on this site, so choosing one is a comparison of the site as
	 * it is with the site as it would be, rather than of one level's list against another's. The level closest
	 * to the site as it is starts chosen.
	 */
	protected function getStepRenderData() :array {
		$profiles = self::con()->comps->security_profiles;

		$levels = [];
		foreach ( Levels::Enum() as $level ) {
			$comparison = ( new CompareLevelWithCurrent() )->build( $level );
			$changes = $comparison[ 'stricter' ] + $comparison[ 'looser' ];
			$title = $profiles->meta( $level )[ 'title' ];
			$levels[ $level ] = [
				'key'         => $level,
				'title'       => $title,
				'subtitle'    => $profiles->meta( $level )[ 'subtitle' ],
				'changes'     => $changes,
				'comparison'  => $comparison,
				'strings'     => [
					'changes'  => $changes === 0
						? __( 'Matches your site now', 'wp-simple-firewall' )
						/* translators: %s: number of settings */
						: sprintf( _n( '%s setting changes', '%s settings change', $changes, 'wp-simple-firewall' ), $changes ),
					/* translators: %s: name of a protection level */
					'compared' => sprintf( __( '%s compared with your site now', 'wp-simple-firewall' ), $title ),
					'apply'    => $changes === 0
						? __( 'Keep my settings as they are', 'wp-simple-firewall' )
						: sprintf(
						/* translators: %1$s: name of a protection level, %2$s: "5 changes" */
							__( 'Apply %1$s (%2$s)', 'wp-simple-firewall' ),
							$title,
							/* translators: %s: number of changes */
							sprintf( _n( '%s change', '%s changes', $changes, 'wp-simple-firewall' ), $changes )
						),
				],
			];
		}

		$fewestChanges = \min( \array_column( $levels, 'changes' ) );
		$closest = \current( \array_filter( $levels, static fn( array $level ) :bool => $level[ 'changes' ] === $fewestChanges ) )[ 'key' ];

		return [
			'strings' => [
				'step_title'   => __( 'Choose A Protection Level', 'wp-simple-firewall' ),
				'intro'        => __( 'A level sets many options at once. Nothing changes until you apply it, and you can adjust any setting later in Configure.', 'wp-simple-firewall' ),
				'levels_label' => __( 'Protection level', 'wp-simple-firewall' ),
				'closest'      => __( 'Closest to your site now', 'wp-simple-firewall' ),
				/* translators: %s: number of settings */
				'stricter'     => __( '%s stricter', 'wp-simple-firewall' ),
				/* translators: %s: number of settings */
				'looser'       => __( '%s less strict', 'wp-simple-firewall' ),
				/* translators: %s: number of settings */
				'unchanged'    => __( '%s unchanged', 'wp-simple-firewall' ),
				'only_changes' => __( 'Only show what changes', 'wp-simple-firewall' ),
				'col_setting'  => __( 'Setting', 'wp-simple-firewall' ),
				'col_now'      => __( 'Current', 'wp-simple-firewall' ),
				'col_after'    => __( 'New', 'wp-simple-firewall' ),
				// How each row's change reads, by the change the comparison gives it.
				'change_labels' => [
					'stricter'    => __( 'Stricter', 'wp-simple-firewall' ),
					'looser'      => __( 'Less strict', 'wp-simple-firewall' ),
					'same'        => __( 'No change', 'wp-simple-firewall' ),
					'unavailable' => __( 'Not on your plan', 'wp-simple-firewall' ),
				],
				'all_match'    => __( 'Your site already matches this level.', 'wp-simple-firewall' ),
			],
			'vars'    => [
				'levels'  => $levels,
				'closest' => $closest,
			],
		];
	}
}
