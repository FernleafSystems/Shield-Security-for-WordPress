{
  "slug": "license",
  "properties": {
    "slug": "license",
    "name": "Shield Pro",
    "show_feature_menu_item": true,
    "storage_key": "license",
    "show_central": true,
    "is_premium": false,
    "access_restricted": true
  },
  "sections": [
    {
      "slug": "section_license_options",
      "title": "License Options",
      "primary": true
    },
    {
      "slug": "section_non_ui",
      "hidden": true
    }
  ],
  "options": [
    {
      "key": "license_key",
      "section": "section_license_options",
      "default": "",
      "type": "text",
      "link_info": "",
      "link_blog": "",
      "name": "License Key",
      "summary": "License Key",
      "description": "License Key."
    },
    {
      "key": "license_activated_at",
      "transferable": false,
      "default": 0,
      "section": "section_non_ui"
    },
    {
      "key": "license_deactivated_at",
      "transferable": false,
      "default": 0,
      "section": "section_non_ui"
    },
    {
      "key": "license_last_checked_at",
      "transferable": false,
      "default": 0,
      "section": "section_non_ui"
    },
    {
      "key": "license_expires_at",
      "transferable": false,
      "default": 0,
      "section": "section_non_ui"
    },
    {
      "key": "license_official_status",
      "transferable": false,
      "default": '',
      "section": "section_non_ui"
    }
  ],
  "definitions": {
    "license_store_url": "https://www.asdf.com",
    "license_item_name": "Shield Pro",
    "license_item_id": "Shield Pro",
    "license_auto_deactivate_days": 3,
    "license_key_length": 32,
    "license_key_type": "alphanumeric"
  }
}