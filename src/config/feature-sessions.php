{
  "properties": {
    "slug": "sessions",
    "name": "Sessions",
    "show_module_menu_item": false,
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
      "title": "Enable Module: Sessions",
      "title_short": "Disable Module",
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
      "summary": "Enable (or Disable) The Sessions module",
      "description": "Un-Checking this option will completely disable the Sessions module"
    },
    {
      "key":          "autoadd_sessions_started_at",
      "transferable": false,
      "section":      "section_non_ui"
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