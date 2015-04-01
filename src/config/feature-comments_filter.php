<?php
return
	sprintf(
	"---
slug: 'comments_filter'
properties:
  name: '%s'
  show_feature_menu_item: true
  storage_key: 'commentsfilter' # should correspond exactly to that in the plugin.yaml
  use_sessions: true
# Options Sections
sections:
  -
    slug: 'section_enable_plugin_feature_spam_comments_protection_filter'
    primary: true
  -
    slug: 'section_enable_human_comment_spam_protection_filter'
  -
    slug: 'section_enable_automatic_bot_comment_spam_protection_filter'
  -
    slug: 'section_customize_messages_shown_to_user'
  -
    slug: 'section_non_ui'
    hidden: true

# Define Options and assign to section slug
options:
  -
    key: 'enable_comments_filter'
    section: 'section_enable_plugin_feature_spam_comments_protection_filter'
    default: 'N'
    type: 'checkbox'
    link_info: 'http://icwp.io/3z'
    link_blog: 'http://icwp.io/wpsf04'
  -
    key: 'enable_comments_human_spam_filter'
    section: 'section_enable_human_comment_spam_protection_filter'
    default: 'N'
    type: 'checkbox'
    link_info: 'http://icwp.io/57'
    link_blog: ''
  -
    key: 'enable_comments_human_spam_filter_items'
    section: 'section_enable_human_comment_spam_protection_filter'
    type: 'multiple_select'
    default:
      - 'author_name'
      - 'author_email'
      - 'comment_content'
      - 'url'
      - 'ip_address'
      - 'user_agent'
    value_options:
      -
        value_key: 'author_name'
        text: 'Author Name'
      -
        value_key: 'author_email'
        text: 'Author Email'
      -
        value_key: 'comment_content'
        text: 'Comment Content'
      -
        value_key: 'url'
        text: 'URL'
      -
        value_key: 'ip_address'
        text: 'IP Address'
      -
        value_key: 'user_agent'
        text: 'Browser User Agent'

    link_info: 'http://icwp.io/58'
    link_blog: ''
  -
    key: 'comments_default_action_human_spam'
    section: 'section_enable_human_comment_spam_protection_filter'
    default: 0
    type: 'select'
    value_options:
      -
        value_key: 0
        text: 'Mark As Pending Moderation'
      -
        value_key: 'spam'
        text: 'Mark As SPAM'
      -
        value_key: 'trash'
        text: 'Move To Trash'
      -
        value_key: 'reject'
        text: 'Reject And Redirect'
  -
    key: 'enable_comments_gasp_protection'
    section: 'section_enable_automatic_bot_comment_spam_protection_filter'
    default: 'Y'
    type: 'checkbox'
    link_info: 'http://icwp.io/3n'
    link_blog: 'http://icwp.io/2n'
  -
    key: 'enable_comments_gasp_protection_for_logged_in'
    section: 'section_enable_automatic_bot_comment_spam_protection_filter'
    hidden: true #since version v4.0.1 this has been removed.
    default: 'N'
    type: 'checkbox'
    link_info: ''
    link_blog: ''
  -
    key: 'comments_default_action_spam_bot'
    section: 'section_enable_automatic_bot_comment_spam_protection_filter'
    default: 'trash'
    type: 'select'
    value_options:
      -
        value_key: 0
        text: 'Mark As Pending Moderation'
      -
        value_key: 'spam'
        text: 'Mark As SPAM'
      -
        value_key: 'trash'
        text: 'Move To Trash'
      -
        value_key: 'reject'
        text: 'Reject And Redirect'

    link_info: 'http://icwp.io/3x'
    link_blog: ''
  -
    key: 'comments_cooldown_interval'
    section: 'section_enable_automatic_bot_comment_spam_protection_filter'
    default: 30
    type: 'integer'
    link_info: 'http://icwp.io/3o'
    link_blog: ''
  -
    key: 'comments_token_expire_interval'
    section: 'section_enable_automatic_bot_comment_spam_protection_filter'
    default: 600
    type: 'integer'
    link_info: 'http://icwp.io/3o'
    link_blog: ''
  -
    key: 'custom_message_checkbox'
    section: 'section_customize_messages_shown_to_user'
    default: \"I'm not a spammer\"
    type: 'text'
    link_info: 'http://icwp.io/3p'
    link_blog: ''
  -
    key: 'custom_message_alert'
    section: 'section_customize_messages_shown_to_user'
    default: \"Please check the box to confirm you're not a spammer\"
    type: 'text'
    link_info: 'http://icwp.io/3p'
    link_blog: ''
  -
    key: 'custom_message_comment_wait'
    section: 'section_customize_messages_shown_to_user'
    default: \"Please wait %s seconds before posting your comment\"
    type: 'text'
    link_info: 'http://icwp.io/3p'
    link_blog: ''
  -
    key: 'custom_message_comment_reload'
    section: 'section_customize_messages_shown_to_user'
    default: \"Please reload this page to post a comment\"
    type: 'text'
    link_info: 'http://icwp.io/3p'
    link_blog: ''
  -
    key: 'current_plugin_version'
    section: 'section_non_ui'
  -
    key: 'spambot_comments_filter_table_name'
    section: 'section_non_ui'
    value: 'spambot_comments_filter'
  -
    key: 'spambot_comments_filter_table_columns'
    section: 'section_non_ui'
    value:
      - 'id'
      - 'post_id'
      - 'unique_token'
      - 'ip'
      - 'created_at'
      - 'deleted_at'
  -
    key: 'recreate_database_table'
    section: 'section_non_ui'
    default: false
",
		_wpsf__( 'Comments Filter' )
	);