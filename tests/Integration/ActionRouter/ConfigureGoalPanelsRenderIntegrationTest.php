<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Options\OptionsFormFor;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureGoalPanels;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\ConfigureZoneTilesBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\PluginAdminRouteRenderAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

/**
 * Every Configure topic renders as its panel, and every option in it ends up on the page as a form field
 * named after the option, so it can be seen, changed and saved.
 */
class ConfigureGoalPanelsRenderIntegrationTest extends ShieldIntegrationTestCase {

	use PluginAdminRouteRenderAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsSecurityAdmin();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	public function test_every_topic_renders_a_field_for_each_of_its_options() :void {
		$visible = $this->visibleOptionKeys();
		$checked = 0;
		foreach ( ConfigureGoalPanels::goals() as $goalKey => $goal ) {
			foreach ( \array_keys( $goal[ 'topics' ] ) as $topicKey ) {
				$optionKeys = \array_values( \array_intersect( ConfigureGoalPanels::topicOptionKeys( $goalKey, $topicKey ), $visible ) );
				$ref = $goalKey.':'.$topicKey;

				$html = $this->assertRouteRenderOutputHealthy(
					$this->processActionPayloadWithAdminBypass( OptionsFormFor::SLUG, [
						'options'         => $optionKeys,
						'configure_panel' => $ref,
						'form_context'    => 'expansion',
					] ),
					'panel '.$ref
				);
				$this->assertStringContainsString( 'options_form_for--panel', $html, 'Panel layout for '.$ref );
				foreach ( $optionKeys as $optionKey ) {
					$this->assertStringContainsString( 'data-option-key="'.$optionKey.'"', $html, $optionKey.' shown in '.$ref );
					$this->assertMatchesRegularExpression(
						'#name="'.\preg_quote( $optionKey, '#' ).'(\[\])?"#',
						$html,
						$optionKey.' has a field in '.$ref
					);
					$checked++;
				}
			}
		}
		$this->assertGreaterThan( 100, $checked );
	}

	public function test_every_goal_tile_points_its_topics_at_their_panels() :void {
		$tiles = ( new ConfigureZoneTilesBuilder() )->build();
		$this->assertSame( \array_keys( ConfigureGoalPanels::goals() ), \array_column( $tiles, 'key' ) );

		$placed = [];
		foreach ( $tiles as $tile ) {
			foreach ( $tile[ 'panel' ][ 'rows' ] as $row ) {
				$data = $row[ 'config_action' ][ 'data' ];
				$this->assertSame( $tile[ 'key' ].':'.$row[ 'key' ], $data[ 'configure_panel' ] );
				$placed = \array_merge( $placed, \explode( ',', $data[ 'option_keys' ] ) );
			}
		}

		$ownPage = \array_merge( ...\array_values( ConfigureGoalPanels::ownPageOptions() ) );
		$this->assertSame( [], \array_values( \array_diff( $this->visibleOptionKeys(), $placed, $ownPage ) ) );
	}

	/**
	 * @return list<string>
	 */
	private function visibleOptionKeys() :array {
		$keys = [];
		foreach ( $this->requireController()->cfg->configuration->options as $key => $option ) {
			$section = (string)( $option[ 'section' ] ?? '' );
			if ( $section !== '' && !\in_array( $section, [ 'section_hidden', 'section_deprecated' ], true ) ) {
				$keys[] = (string)$key;
			}
		}
		return $keys;
	}
}
