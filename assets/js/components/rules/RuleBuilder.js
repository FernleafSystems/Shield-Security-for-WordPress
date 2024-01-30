import { BaseAutoExecComponent } from "../BaseAutoExecComponent";
import { AjaxService } from "../services/AjaxService";
import { ObjectOps } from "../../util/ObjectOps";
import { Forms } from "../../util/Forms";
import { PageQueryParam } from "../../util/PageQueryParam";

export class RuleBuilder extends BaseAutoExecComponent {

	init() {
		this.containerID = 'RuleBuilderContainer';
		this.edit_rule_id = PageQueryParam.Retrieve( 'edit_rule_id' );
		this.rule_builder_container = document.getElementById( this.containerID ) || false;
		super.init();
	}

	canRun() {
		return this.rule_builder_container;
	}

	run() {
		this.renderBuilder();

		const formSelector = this.getFormSelector();

		shieldEventsHandler_Main.add_Change( formSelector, ( form ) => {
			this.action( {
				builder_action: 'update',
			}, form );
		} );

		shieldEventsHandler_Main.add_Click( formSelector + ' button.add-condition', ( button ) => {
			this.action( {
				builder_action: 'add_condition',
			}, button.closest( 'form' ) );
		} );
		shieldEventsHandler_Main.add_Click( formSelector + ' button.delete-condition', ( button ) => {
			this.action( {
				builder_action: 'delete_condition',
				builder_action_vars: button.dataset,
			}, button.closest( 'form' ) );
		} );

		shieldEventsHandler_Main.add_Click( formSelector + ' button.add-response', ( button ) => {
			this.action( {
				builder_action: 'add_response',
			}, button.closest( 'form' ) );
		} );

		shieldEventsHandler_Main.add_Click( formSelector + ' button.delete-response', ( button ) => {
			this.action( {
				builder_action: 'delete_response',
				builder_action_vars: button.dataset,
			}, button.closest( 'form' ) );
		} );

		shieldEventsHandler_Main.add_Click( formSelector + ' button.create-rule', ( button ) => {
			this.action( {
				builder_action: 'create_rule',
			}, button.closest( 'form' ) );
		} );

		shieldEventsHandler_Main.add_Click( formSelector + ' button.reset', ( button ) => {
			this.action( {
				builder_action: 'reset',
			}, button.closest( 'form' ) );
		} );
	}

	getFormSelector() {
		return '#' + this.containerID + ' form';
	}

	/**
	 * Renders the builder with the given parameters and rule form.
	 *
	 * @param {Object} params - The parameters for rendering the builder.
	 * @param {HTMLFormElement} ruleForm - The rule form element.
	 * @return {void}
	 */
	action( params = {}, ruleForm = null ) {

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

		let editRuleId = -1;

		( new AjaxService() )
		.send( ObjectOps.Merge( this._base_data.ajax.rule_builder_action, params ) )
		.finally( () => this.renderBuilder( editRuleId ) );
	}

	renderBuilder() {
		( new AjaxService() )
		.send(
			ObjectOps.Merge( this._base_data.ajax.render_rule_builder, {
				edit_rule_id: this.edit_rule_id
			} )
		)
		.then( ( respJSON ) => {
			this.rule_builder_container.innerHTML = respJSON.data.html;

			const inputRuleID = document.querySelector( this.getFormSelector() + ' input[name=edit_rule_id]' );
			if ( inputRuleID ) {
				this.edit_rule_id = inputRuleID.value;
			}
		} )
		.finally( () => this.postRender() );
	}

	postRender() {
	}
}