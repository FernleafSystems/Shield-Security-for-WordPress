{
  "properties": {
    "slug": "sessions",
    "name": "Sessions",
    "show_feature_menu_item": false,
    "storage_key": "sessions",
    "tagline": "User Sessions",
    "auto_enabled": true,
    "show_central": false,
    "premium": false,
    "access_restricted": true
  },
  "sections": [
    {
      "slug": "section_enable_plugin_feature_sessions",
      "primary": true,
      "title": "Enable Plugin Feature: Sessions",
      "title_short": "Enable / Disable",
      "summary": [
        "Purpose - Creates and Manages User Sessions.",
        "Recommendation - Keep the Sessions feature turned on."
      ]
    },
    {
      "slug": "section_non_ui",
      "hidden": true
    }
  ],
  "options": [
    {
      "key": "enable_sessions",
      "section": "section_enable_plugin_feature_sessions",
      "default": "Y",
      "type": "checkbox",
      "link_info": "",
      "link_blog": "",
      "name": "Enable Sessions",
      "summary": "Enable (or Disable) The Sessions Feature",
      "description": "Checking/Un-Checking this option will completely turn on/off the whole Sessions feature"
    }
  ],
  "definitions": {
    "sessions_table_name": "sessions",
    "sessions_table_columns": [
      "id",
      "session_id",
      "wp_username",
      "ip",
      "browser",
      "logged_in_at",
      "last_activity_at",
      "last_activity_uri",
      "secadmin_at",
      "created_at",
      "deleted_at"
    ]
  }
}