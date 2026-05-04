<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\FullPage\Block;

/**
 * @phpstan-type BlockRecoveryIds array{
 *   launcher:string,
 *   dialog:string,
 *   title:string,
 *   body:string,
 *   status:string,
 *   confirm:string,
 *   submit:string,
 *   helper:string
 * }
 * @phpstan-type BlockRecoveryModalContract array{
 *   id:string,
 *   title_id:string,
 *   body_id:string,
 *   title:string,
 *   close_label:string
 * }
 * @phpstan-type BlockRecoveryLauncherContract array{
 *   id:string,
 *   label:string,
 *   target_selector:string
 * }
 * @phpstan-type BlockRecoveryActionContract array{
 *   page:string,
 *   action:string,
 *   is_available:bool,
 *   content:string,
 *   ids:BlockRecoveryIds,
 *   launcher:BlockRecoveryLauncherContract,
 *   modal:BlockRecoveryModalContract
 * }
 * @phpstan-type BlockRecoveryCandidate array{
 *   recovery:BlockRecoveryActionContract,
 *   content:string
 * }
 */
trait BlockRecoveryRenderContracts {

	/**
	 * @param list<BlockRecoveryCandidate> $candidates
	 * @return BlockRecoveryActionContract
	 */
	protected function buildBlockRecoveryContract( string $pageKey, array $candidates ) :array {
		foreach ( $candidates as $candidate ) {
			$content = \trim( $candidate[ 'content' ] );
			if ( $content !== '' ) {
				$recovery = $candidate[ 'recovery' ];
				$recovery[ 'content' ] = $content;
				$recovery[ 'is_available' ] = true;
				return $recovery;
			}
		}

		return $this->buildBlockRecoveryActionContract( $pageKey, 'none', '', false );
	}

	/**
	 * @phpstan-param BlockRecoveryActionContract $recovery
	 * @phpstan-return BlockRecoveryCandidate
	 */
	protected function buildBlockRecoveryCandidate( array $recovery, string $content ) :array {
		return [
			'recovery' => $recovery,
			'content'  => $content,
		];
	}

	/**
	 * @return BlockRecoveryActionContract
	 */
	protected function buildBlockRecoveryActionContract(
		string $pageKey,
		string $actionKey,
		string $content = '',
		bool $isAvailable = true
	) :array {
		$page = $this->normalizeBlockRecoveryKey( $pageKey );
		$action = $this->normalizeBlockRecoveryKey( $actionKey );
		$idBase = \sprintf( 'shield-block-%s-%s', $page, $action );
		$ids = [
			'launcher' => $idBase.'-launcher',
			'dialog'   => $idBase.'-dialog',
			'title'    => $idBase.'-title',
			'body'     => $idBase.'-body',
			'status'   => $idBase.'-status',
			'confirm'  => $idBase.'-confirm',
			'submit'   => $idBase.'-submit',
			'helper'   => $idBase.'-helper',
		];
		$strings = $this->blockRecoveryStringsForAction( $action );

		return [
			'page'         => $page,
			'action'       => $action,
			'is_available' => $isAvailable,
			'content'      => $isAvailable ? $content : '',
			'ids'          => $ids,
			'launcher'     => [
				'id'              => $ids[ 'launcher' ],
				'label'           => $strings[ 'launcher_label' ],
				'target_selector' => '#'.$ids[ 'dialog' ],
			],
			'modal'        => [
				'id'          => $ids[ 'dialog' ],
				'title_id'    => $ids[ 'title' ],
				'body_id'     => $ids[ 'body' ],
				'title'       => $strings[ 'modal_title' ],
				'close_label' => __( 'Close', 'wp-simple-firewall' ),
			],
		];
	}

	protected function normalizeBlockRecoveryKey( string $key ) :string {
		$key = sanitize_key( \str_replace( '_', '-', $key ) );
		return $key === '' ? 'none' : $key;
	}

	/**
	 * @return array{launcher_label:string,modal_title:string}
	 */
	private function blockRecoveryStringsForAction( string $action ) :array {
		if ( $action === 'email-unblock' ) {
			return [
				'launcher_label' => __( 'Unblock via Email', 'wp-simple-firewall' ),
				'modal_title'    => __( 'Unblock Your IP', 'wp-simple-firewall' ),
			];
		}

		return [
			'launcher_label' => __( 'Unblock My IP', 'wp-simple-firewall' ),
			'modal_title'    => __( 'Unblock Your IP', 'wp-simple-firewall' ),
		];
	}
}
