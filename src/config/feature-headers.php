<?php
return
	sprintf(
	"---
slug: 'headers'
properties:
  name: '%s'
  show_feature_menu_item: true
  storage_key: 'headers' # should correspond exactly to that in the plugin.yaml
  tagline: '%s'
# Options Sections
sections:
  -
    slug: 'section_enable_plugin_feature_headers'
    primary: true
  -
    slug: 'section_security_headers'
  -
    slug: 'section_content_security_policy'
  -
    slug: 'section_non_ui'
    hidden: true

# Define Options
options:
  -
    key: 'enable_headers'
    section: 'section_enable_plugin_feature_headers'
    transferable: true
    default: 'N'
    type: 'checkbox'
    link_info: 'http://icwp.io/7c'
    link_blog: 'http://icwp.io/7c'
  -
    key: 'x_frame'
    section: 'section_security_headers'
    transferable: true
    default: 'on_sameorigin'
    type: 'select'
    value_options:
      -
        value_key: 'off'
        text: 'Off: iFrames Not Blocked'
      -
        value_key: 'on_sameorigin'
        text: 'On: Allow iFrames On The Same Domain'
      -
        value_key: 'on_deny'
        text: 'On: Block All iFrames'

    link_info: 'http://icwp.io/78'
    link_blog: 'http://icwp.io/7c'
  -
    key: 'x_xss_protect'
    section: 'section_security_headers'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: 'http://icwp.io/79'
    link_blog: 'http://icwp.io/7c'
  -
    key: 'x_content_type'
    section: 'section_security_headers'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: 'http://icwp.io/7a'
    link_blog: 'http://icwp.io/7c'
  -
    key: 'enable_x_content_security_policy'
    section: 'section_content_security_policy'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: 'http://icwp.io/7d'
    link_blog: 'http://icwp.io/7c'
  -
    key: 'xcsp_self'
    section: 'section_content_security_policy'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'xcsp_inline'
    section: 'section_content_security_policy'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'xcsp_data'
    section: 'section_content_security_policy'
    transferable: true
    default: 'Y'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'xcsp_eval'
    section: 'section_content_security_policy'
    transferable: true
    default: 'N'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'xcsp_https'
    section: 'section_content_security_policy'
    transferable: true
    default: 'N'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'xcsp_hosts'
    section: 'section_content_security_policy'
    transferable: true
    sensitive: true
    default:
      - '*'
    type: 'array'
    link_info: ''
    link_blog: ''
  -
    key: 'current_plugin_version'
    section: 'section_non_ui'
",
		_wpsf__( 'HTTP Headers' ),
		_wpsf__( 'Control HTTP Security Headers' ) //tagline
	);