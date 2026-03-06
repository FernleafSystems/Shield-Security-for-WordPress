<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\InvestigateAssetLookupOptionsBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\IpLookupSearch;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\UserManagement\Lib\InvestigateUserLookupBuilder;
use FernleafSystems\Wordpress\Services\Services;

class InvestigateLookupSelect extends BaseAction {

	public const SLUG = 'investigate_lookup_select';
	private const RESULT_LIMIT = 20;

	protected function getDefaults() :array {
		return [
			'subject' => '',
			'search'  => '',
		];
	}

	protected function exec() {
		$subject = sanitize_key( (string)$this->action_data[ 'subject' ] );
		$search = \strtolower( \trim( sanitize_text_field( (string)$this->action_data[ 'search' ] ) ) );
		$minimumLength = [
							 'user'   => 1,
							 'ip'     => 3,
							 'plugin' => 2,
							 'theme'  => 2,
						 ][ $subject ] ?? 2;

		$this->response()->setPayload( [
			'results' => \strlen( $search ) < $minimumLength ? [] : $this->searchBySubject( $subject, $search ),
		] )->setPayloadSuccess( true );
	}

	/**
	 * @return array<int,array{id:string,text:string}>
	 */
	private function searchBySubject( string $subject, string $search ) :array {
		switch ( $subject ) {
			case 'user':
				return $this->searchUsers( $search );
			case 'ip':
				return $this->searchIps( $search );
			case 'plugin':
				return $this->searchPlugins( $search );
			case 'theme':
				return $this->searchThemes( $search );
			default:
				return [];
		}
	}

	/**
	 * @return array<int,array{id:string,text:string}>
	 */
	private function searchUsers( string $search ) :array {
		return ( new InvestigateUserLookupBuilder() )->searchResults( $search, self::RESULT_LIMIT );
	}

	/**
	 * @return array<int,array{id:string,text:string}>
	 */
	private function searchIps( string $search ) :array {
		return \array_map(
			static fn( string $ip ) :array => [
				'id'   => $ip,
				'text' => $ip,
			],
			( new IpLookupSearch() )->findMatchingIps( $search, self::RESULT_LIMIT )
		);
	}

	/**
	 * @return array<int,array{id:string,text:string}>
	 */
	private function searchPlugins( string $search ) :array {
		return $this->searchAssets(
			Services::WpPlugins()->getPluginsAsVo(),
			'file',
			$search
		);
	}

	/**
	 * @return array<int,array{id:string,text:string}>
	 */
	private function searchThemes( string $search ) :array {
		return $this->searchAssets(
			Services::WpThemes()->getThemesAsVo(),
			'stylesheet',
			$search
		);
	}

	/**
	 * @param object[] $assets
	 * @return array<int,array{id:string,text:string}>
	 */
	private function searchAssets( array $assets, string $valueField, string $search ) :array {
		return \array_map(
			static fn( array $option ) :array => [
				'id'   => $option[ 'value' ],
				'text' => $option[ 'label' ],
			],
			( new InvestigateAssetLookupOptionsBuilder() )->build(
				$assets,
				$valueField,
				$search,
				self::RESULT_LIMIT
			)
		);
	}
}
