<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Utility\ExternalEntryGuard;
use FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\CloakedPlugins\CloakedPluginState;

/**
 * Ignores cloaked plugins either until their main file changes ("contents", the default) or wherever they are,
 * whatever they contain ("path").
 */
class CloakedPluginIgnore extends CloakedPluginIgnoreBase {

	public const SLUG = 'cloaked_plugin_ignore';

	public static function ExternalAccess() :array {
		return [
			ExternalEntryGuard::ENTRY_AJAX => ExternalEntryGuard::PROOF_NONCE,
		];
	}

	protected function applyIdentityChange( CloakedPluginState $state, string $identity, array $currentFindings ) :bool {
		return $state->ignoreIdentity(
			$identity,
			$currentFindings,
			$this->fields()->oneOf( 'ignore_mode', CloakedPluginState::IGNORE_MODES, CloakedPluginState::IGNORE_MODE_CONTENTS ),
			$this->reviewedContentsHash( $identity )
		);
	}

	/**
	 * The contents the user reviewed: a "contents_hash" beside one "identifier", or from "Ignore all", each
	 * identifier's own in "contents_hashes" and nothing else, so one plugin's review can't stand in for another's.
	 */
	private function reviewedContentsHash( string $identity ) :string {
		$hash = $this->isRequestForSeveralIdentifiers()
			? ( $this->fields()->array( 'contents_hashes', [] )[ $identity ] ?? '' )
			: $this->fields()->string( 'contents_hash', '' );
		return \is_string( $hash ) ? $hash : '';
	}

	protected function successMessage() :string {
		return __( 'Cloaked plugin ignored.', 'wp-simple-firewall' );
	}
}
