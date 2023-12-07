import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { Forms } from "../../util/Forms";
import { ShieldOverlay } from "../ui/ShieldOverlay";

export class RuleBuilder extends BaseAutoExecComponent {

	init() {
		this.containerID = 'RuleBuilderContainer';
		this.rule_builder_container = document.getElementById( this.containerID ) || false;
		super.init();
	}

	canRun() {
		return this.rule_builder_container;
	}

	run() {
		this.renderBuilder();

		const baseSelector = '#' + this.containerID + ' form';

		shieldEventsHandler_Main.add_Change( baseSelector, ( form ) => {
			this.renderBuilder( {}, form );
		} );

		shieldEventsHandler_Main.add_Click( baseSelector + ' button.add-condition', ( button ) => {
			this.renderBuilder( {
				builder_action: 'add_condition',
			}, button.closest( 'form' ) );
		} );

		shieldEventsHandler_Main.add_Click( baseSelector + ' button.add-response', ( button ) => {
			this.renderBuilder( {
				builder_action: 'add_response',
			}, button.closest( 'form' ) );
		} );

		shieldEventsHandler_Main.add_Click( baseSelector + ' button.create-rule', ( button ) => {
			this.renderBuilder( {
				builder_action: 'create_rule',
			}, button.closest( 'form' ) );
		} );

		shieldEventsHandler_Main.add_Click( baseSelector + ' button.delete-condition', ( button ) => {
			this.renderBuilder( {
				builder_action: 'delete_condition',
				builder_action_vars: button.dataset,
			}, button.closest( 'form' ) );
		} );

		shieldEventsHandler_Main.add_Click( baseSelector + ' button.delete-response', ( button ) => {
			this.renderBuilder( {
				builder_action: 'delete_response',
				builder_action_vars: button.dataset,
			}, button.closest( 'form' ) );
		} );

		shieldEventsHandler_Main.add_Click( baseSelector + ' button.reset', ( button ) => {
			this.renderBuilder( {
				builder_action: 'reset'
			}, button.closest( 'form' ) );
		} );
	}

	/**
	 * Renders the builder with the given parameters and rule form.
	 *
	 * @param {Object} params - The parameters for rendering the builder.
	 * @param {HTMLFormElement} ruleForm - The rule form element.
	 * @return {void}
	 */
	renderBuilder( params = {}, ruleForm = null ) {

		ShieldOverlay.Show( this.containerID );

		if ( ruleForm !== null ) {

			/**
			 * We always want to send checkbox settings for processing, so if it's unchecked, we set it to checked
			 * and supply the negative value.
			 */
			ruleForm.querySelectorAll( 'input[type=checkbox]' ).forEach( ( checkbox ) => {
				if ( !checkbox.checked ) {
					checkbox.value = 'N';
					checkbox.checked = true;
				}
				else {
					checkbox.value = 'Y';
				}
			} );

			params[ 'rule_form' ] = Forms.Serialize( ruleForm );
		}

		if ( !( 'builder_action' in params ) ) {
			params.builder_action = 'update';
		}

		( new AjaxService() )
		.bg( ObjectOps.Merge( this._base_data.ajax.render_rule_builder, params ) )
		.then( ( respJSON ) => {
			this.rule_builder_container.innerHTML = respJSON.data.html;
		} )
		.finally( () => {
			ShieldOverlay.Hide();
		} );
	}
}