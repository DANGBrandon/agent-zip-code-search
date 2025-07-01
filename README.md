# Agent Zip Code Search Plugin

This repository contains a simple WordPress plugin that provides an advanced search interface for Toolset-powered custom post types. The plugin allows searching by name, zip code radius and state. Results are displayed in a grid.

## Installation

1. Copy `advanced-search-filter-brandon.php` and `asfb-styles.css` into the `wp-content/plugins` directory of your WordPress installation.
2. Activate the **Advanced Search and Filter - Brandon** plugin from the WordPress admin.
3. Visit **Settings > Advanced Search & Filter - Brandon** to choose the post type and select which custom fields should appear in results. The available meta keys are listed with checkboxes so you can pick which values are displayed in the result grid.

## Usage

Place the following shortcodes on any page:

- `[asf_search_form]` – renders the search form.
- `[asf_search_results]` – outputs the search results grid.

The search supports fuzzy name matching across the Toolset fields `wpcf-first-name`, `wpcf-middle-name` and `wpcf-last-name`. Zip code searches use the zippopotam.us API to obtain coordinates and filter results by radius.
