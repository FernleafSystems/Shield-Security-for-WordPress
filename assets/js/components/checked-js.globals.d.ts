declare const ajaxurl: string;

type AccessibleDialogConfig = {
	title?: string;
	message?: string;
	label?: string;
	value?: string;
	confirmLabel?: string;
	cancelLabel?: string;
	danger?: boolean;
	launcher?: HTMLElement|null;
	showTitle?: boolean;
	validate?: ( value: string ) => true|string|boolean;
};

type AccessibleDialogService = {
	confirm( config?: AccessibleDialogConfig ) :Promise<boolean>;
	message( config?: AccessibleDialogConfig ) :Promise<void>;
	prompt( config?: AccessibleDialogConfig ) :Promise<string|null>;
	resolveConfirmLabel( launcher?: HTMLElement|null ) :string;
	resolveLauncher( event?: Event|null, node?: any ) :HTMLElement|null;
};

declare const shieldServices: {
	notification() :{
		showMessage( message: string, success?: boolean ) :void;
	};
	dialog() :AccessibleDialogService;
	container_ShieldPage?() :HTMLElement|false;
};

declare const shieldStrings: {
	string( key: string ) :string;
};

declare const shieldAppMain: {
	components?: Record<string, any>;
}|undefined;

declare const shieldEventsHandler_Main: {
	add_Click( selector: string, callback: ( targetEl: Element, evt: Event ) => void, suppress?: boolean ) :void;
	add_Change( selector: string, callback: ( targetEl: Element, evt: Event ) => void, suppress?: boolean ) :void;
	add_Mouseover( selector: string, callback: ( targetEl: Element, evt: Event ) => void, suppress?: boolean ) :void;
	add_Submit( selector: string, callback: ( targetEl: Element, evt: Event ) => void, suppress?: boolean ) :void;
	addHandler( eventName: string, selector: string, callback: ( targetEl: Element, evt: Event ) => void, suppress?: boolean ) :void;
};

declare module "*.scss";

type ShieldPluginOnboardingStepData = {
	selector: string;
	title: string;
	intro: string;
	position: string;
	required: boolean;
};

type ShieldPluginOnboardingVideoModalData = {
	is_enabled: boolean;
	embed_url: string;
	modal_title: string;
	video_title: string;
	body_copy: string;
	skip_label: string;
};

type ShieldPluginOnboardingTourData = {
	key: string;
	is_available: boolean;
	steps: ShieldPluginOnboardingStepData[];
	options: Record<string, any>;
	video_modal: ShieldPluginOnboardingVideoModalData;
};

type ShieldPluginOnboardingBaseData = {
	ajax: {
		finished: Record<string, any>;
	};
	vars: {
		tour: ShieldPluginOnboardingTourData;
	};
};

type ShieldPluginOnboardingGlobal = {
	comps?: {
		plugin_onboarding?: ShieldPluginOnboardingBaseData;
	};
};

type ShieldSilentCaptchaBaseData = {
	ajax: {
		silentcaptcha: Record<string, any> & {
			ajaxurl: string;
		};
	};
};

type ShieldSilentCaptchaGlobal = {
	comps?: {
		silentcaptcha?: ShieldSilentCaptchaBaseData;
	};
};

interface Window {
	shieldAppMain?: {
		components?: Record<string, any>;
	};
	shield_vars_main?: {
		comps?: Record<string, any>;
	};
	shield_vars_plugin_onboarding?: ShieldPluginOnboardingGlobal;
	shield_vars_silentcaptcha?: ShieldSilentCaptchaGlobal;
	Vimeo?: {
		Player: new ( iframe: HTMLIFrameElement ) => {
			destroy?: () => Promise<void>;
			unload?: () => Promise<void>;
		};
	};
}

interface JQuery<TElement = HTMLElement> {
	select2( ...args: any[] ) :JQuery<TElement>;
}

interface JQueryStatic {
	fn: {
		select2?: ( ...args: any[] ) => any;
		[key: string]: any;
	};
}
