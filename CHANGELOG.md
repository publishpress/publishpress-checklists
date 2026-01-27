The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

[2.26.0] - 27 January, 2026

- Added: Add promo for taxonomy filter #1074
- Changed: Extra descriptions on taxonomy filter setting section #1076
- Fixed: Reduce task column on Checklists screen #1070
- Fixed: Changed term setting to the bottom on post type tab #1073

[2.25.0] - 22 January, 2026

- Added: Allow users to rename tasks #1025
- Added: Change notice style to default WordPress style #1046
- Added: Add checklists filter #997
- Changed: Update PublishPress review library #1060
- Fixed: Missing alt text warning in Gutenberg #1056

[2.24.1] - 17 December, 2025

- Fixed: Remove double language file in translation #1045

[2.24.0] - 16 December, 2025

- Added: Allow user to change icon and color on checklists item #1033
- Added: New translation system using PublishPress Translator library #1029
- Fixed: Small typo on OpenAI item #1030
- Fixed: String translation on global-checklists.php by @DAnn2012
- Fixed: Show translated role names in permissions.php by @DAnn2012
- Lang: ES FR IT translation updated #1024
- Lang: Brazil translation #1027

[2.23.0] - 2 October, 2025

- Added: Add option to delete data when uninstall the plugin #1012
- Fixed: Featured Image Alt Text is not recognized #992
- Fixed: Checklists requirement notice banner is missing #998
- Fixed: HyperLinkValidator does not accept TLD more than 6 character #1016
- Updated: Remove Yellow color in Checklists column screen #996
- Updated: Text update for General Settings #994
- Updated: Put the domain name on translation string (credit @DAnn2012)
- Lang: Translate fields tabs #1008
- Lang: Brazil Translation #991

[2.22.0] - 31 July, 2025

- Added: Small indicator for active tasks #920
- Lang: Brazil Translation #964
- Added: Keep recent tab after saving #927
- Fixed: Remove unnecessary post type #959
- Added: Improve error notice on saving #926
- Added: Add notice on save changes #928
- Fixed: Keep tabs on adding custom task #973
- Updated: Small setting improvement #947
- Updated: Update PublishPress banner library #972
- Updated: Capital letter for consistency #930
- Fixed: Double display of error message #945

[2.21.0] - 8 July, 2025

- Added: New Checklists in Plugin Link #935
- Added: Option to disable "Who can ignore the task" #901
- Added: New tab on setting #900
- Fixed: Category dropdown does not handle empty options #933
- Fixed: Undefined property stdClass::$ID on several checklists #932 ##942 #941 #940 #934 #938 #913 #921 #914 #923 #922 #919
- Fixed: Responsive issue on Pro overlay #936
- Fixed: Pro badge position in Safari #937
- Fixed: Category dropdown does not handle empty options #933

[2.20.0] - 5 June, 2025

- Updated: Remove branding feature #888
- Updated: Promo for Checklists Pro in Checklists screen #881
- Added: Setting links on Plugins screen. #902
- Added: Status Filter Promo in Setting. #906
- Fixed: Allow 0 on max in link checklists. #892

[2.19.0] - 20 May, 2025

- Updated: Checklists panel and metabox will be hidden if there are no checklists #854
- Updated: Promo Banner for PublishPress Checklists Pro #863
- Updated: Remove "Configure" link inside the metabox #860
- Added: Promo for Pro Checklists #856
- Added: New checklists item "Featured Image has Caption" #870

[2.18.0] - 26 March, 2025

- Added: Support for Yoast Focus Keyword, meta description #555
- Added: Allow custom SVG icons in Tabs #849
- Added: API for adding values programmatically #635
- Fixed: Rankmath Analyzer stopped working when Checklists initializes #846

[2.17.0] - 10 December, 2024

- Fixed: Remove space at top of the Checklists screen #834
- Added: New tab for Yoast #815
- Added: Disable Checklists on Elementor Page Builder #504

[2.16.0] - 20 November, 2024

- Fixed: Links with ! are marked as invalid #261
- Fixed: Internal link does not update real time #824
- Fixed: Wrong result openAI checklists #823
- Added: "List" view to highlight blocks with issues? #783
- Added: New tab for Featured Image #814
- Added: New tab for Permalinks #812
- Added: New tab for Approval #813
- Updated: Update the readme #820

[2.15.0] - 21 October, 2024

- Fixed: Conflict with Rank Math SEO plugin #791
- Fixed: Promo banner update #779
- Fixed: Anchor link not passing the valid link requirement #781
- Fixed: Required and prohibited categories showing deleted terms #786
- Fixed: OpenAI checklist button not working on the second tab #782
- Fixed: Alt text for featured image not working on the first try #743
- Fixed: Plugin description update #780
- Fixed: Custom arrow position issue #778
- Fixed: Reorganized the images checklists #801
- Fixed: Updated Yoast SEO rule #809
- Fixed: Updated required and prohibited categories #808
- Fixed: Missing translation updates #780
- Updated: Translations for checklist items
- Updated: Translations for image-related checklist items
- Updated: Translations "Featured image" updated to "Featured image is added"
- Updated: Translations "Alt text for featured images" updated to "Featured image has Alt text"
- Updated: Translations "Alt text for all images" updated to "All images have Alt text"
- Updated: Translations "Readability" updated to "Yoast Readability"
- Updated: Translations "SEO" updated to "Yoast SEO"
- Updated: Changelog and translation files
- Updated: Composer dependencies

[2.14.0] - 25 September, 2024

- Added: Taxonomies tab #747
- Added: Minimum number of characters for alt text #616
- Added: ACF (Advanced Custom Fields) integration support #639
- Added: Option to specify the number of images in a post #729
- Fixed: Renamed field group to be compatible with ACF #774
- Fixed: Hide checklist from ACF fields #770
- Fixed: Tiny text change for new alt text #766
- Fixed: Undefined disable_publish_button issue
- Fixed: Failed save rule
- Fixed: PHP 8.2 deprecated message #752
- Fixed: Character count issue #740
- Updated: Composer dependencies
- Updated: Bumped Webpack from 5.91.0 to 5.94.0

[2.13.0] - 28 August, 2024

- Added: Class FieldsTabs to support modifying tabs from the Pro version #739
- Added: Option to disable the Publish button #728
- Fixed: Missing variable gutenbergLockName for locking post saving #738
- Fixed: URL requirement broken by ellipsis, colon, and other characters #301
- Fixed: Checklists design fails on smaller screens #730
- Fixed: Code scanning alerts and minor UI issues #737
- Updated: Composer dependencies
- Updated: PHPLint workflow to use the dev-workspace
- Chore: Added absolute path to dev-workspace

[2.12.0] - 31 July, 2024

- Added: Tabs for different types of requirements #672
- Added: Required category & tag for new rule #492
- Added: Prohibited category & tag for new rule #491
- Fixed: External link missing from checklists #710
- Fixed: String not translated in Italian #638
- Fixed: Checklists could be bypassed if scheduled #666
- Fixed: Redirect to checklists screen on new activation #669
- Updated: Composer dependencies
- Updated: Position for feature image alt
- Updated: Move banner to lib/vendor
- Updated: Full-width CSS for better layout
- Implemented: Cache mechanism for improved performance

[2.11.1] - 18 July, 2024

- Fixed: Fixed compatibility with wordPress 6.6 #697
- Fixed: Quick edit settings are now disabled by default #689

[2.11.0] - 15 July, 2024

- Added: Added a sidebar feature #562
- Added: Added an option to disable quick edit #665
- Added: Added post type validation #403
- Fixed: Issue with menu being empty on first install #552
- Fixed: Default text color for OpenAI prompts #580
- Update: Updated ES, FR, and IT translations #652

[2.10.4] - 04 April, 2024

- Fixed: WordPress 6.5 causes Checklists button to shift, #642
- Fixed: Incompatability with SEOPress, #636
- Fixed: Conflict with Yoast SEO, editor stops working, #631
- Update: Turkish Translation, #641

[2.10.3] - 24 Jan, 2024

- Fixed: Uncaught RangeError: Maximum call stack size exceeded coming from gutenberg panel, #613
- Fixed: Image alt requirement HTTP request loop, #623
- Update: New Translation ES-FR-IT Updates, #615

[2.10.2] - 15 Jan, 2024

- Fixed: Fix issues with saving while editing post, #598
- Update: Re-Enable "Show Warning Icon" settings, #605
- Update: Update OpenAI tasks "Check now" button styles, #604
- Update: Small text update for OpenAI response, #603
- Update: Update Checklists sidebar items spacing, #600
- Fixed: The "publishpress-checklists-panel" plugin encountered error and cannot be rendered, #594
- Update: CheckLists Translation Updates 11 January 2024, #597

[2.10.1] - 11 Jan, 2024

- Fixed: 2.10.0 automatically updates posts while editing, #584
- Fixed: Featured image HTTP request loop, #585
- Update: Remove Show warning icon settings, #586

[2.10.0] - 10 Jan, 2024

- Feature: Add AI features to Checklists requirements, #541
- Update: Add Checklists gutenberg panel, #567
- Update: Remove "Define tasks that must be complete before content is published.", #561
- Update: Checklists FREE v.2.9.1 Translation Updates ES-FR-IT, #554

[2.9.1] - 30 Nov, 2023

- Fixed: Checklist menu often missing on new installation, #524
- Fixed: HyperlinkValidator fails with URLs containing text fragments, #485
- Update: Block updates for posts with incomplete checklists, #303
- Fixed: Conflict with ACF custom field when creating new post, #506
- Fixed: Yoast SEO metabox error when creating new woocommerce product, #505
- Fixed: Warning: Undefined array key "HTTP_REFERER" in checklists, #411
- Update: Only disable Status in quick edit for checklists enabled post types, #536
- Fixed: Featured Image Height and Width checks fail for Authors / Contributors, #486
- Fixed: "Featured Image Size" requirement fails if you do not have the "edit_other_posts" capability, #523

[2.9.0] - 09 Aug, 2023

- Changed: Updated internal libraries to latest versions;
- Changed: Move dependencies to lib/vendor;
- Changed: Internal dependencies moved from `vendor` to `lib/vendor`;
- Changed: Updated internal libraries to the latest versions;
- Changed: Removed the `vendor-locator-checklists` library. Internal vendor is now on a fixed path, `lib/vendor`;
- Changed: Deprecated constant `PUBLISHPRESS_CHECKLISTS_VENDOR_PATH` in favor of `PPCH_LIB_VENDOR_PATH`;
- Fixed: Fix compatibility with Composer-based installations, using prefixed libraries;

[2.8.0] - 18 May, 2023

- Changed: Replaced Pimple library with a prefixed version of the library to avoid conflicts with other plugins;
- Changed: Replaced Psr/Container library with a prefixed version of the library to avoid conflicts with other plugins;
- Changed: Change min PHP version to 7.2.5. If not compatible, the plugin will not execute;
- Changed: Change min WP version to 5.5. If not compatible, the plugin will not execute;
- Changed: Updated internal libraries to latest versions;

[2.7.4] - 06 Mar, 2023

- Fixed: Image alt tag function not working in Classic Editor, #471
- Fixed: Gutenberg Editor error when using Taxonomy Categories, #407
- Fixed: Internal link checker not working in Classic Editor, #278
- Fixed: Rank Math meta field missing when checklists is enabled, #470
- Fixed: Support for Rank Math and Classic Editor, #293
- Update: Use wp_kses_post filter instead esc_html to enable allowed tags in metabox label, #478
- Fixed: Submit Lock Affecting Content Update with Yoast, #423
- Update: German translation, #262
- Update: PRO_Checklists_ES-FR-IT_TranslationUpdate_October2022, #415
- Update: TRANSLATION UPDATES French-Spansh-Italian, #406

[2.7.3] - 05 Jul, 2022

- Fixed: Missing checklists settings menu in PHP 8.0 and Multisite, #387
- Fixed: Settings footer breaks for language other than English, #388
- Fixed: Missing translation for Min and Max, #389
- Added: Include new Free / Pro library, #377
- Fixed: Issue with Yoast SEO and post_content, #391
- Fixed: Clicking the Preview button will trigger the publishing pop-up, #378
- Fixed: Extra calls slowing down website, #385
- Fixed: Warning: Undefined array key "page", caused by "helper_settings_validate_and_save" function, #369
- Update: Most important buttons should be yellow only, #382
- Fixed: Issue with PHP 5.6, #386

[2.7.2] - 27 Apr, 2022

- Fixed: Fix Yoast SEO word count breaks in Pending Review, #345;
- Fixed: Run the WordPress VIP scans on Checklists, #354;

[2.7.1] - 20 Apr, 2022

- Fixed: Fix incorrect text in settings, #344;
- Fixed: Allow links with # as valid URL check, #352;
- Fixed: Hide task not supported for post type, #199;
- Fixed: Fix bad footer image URL on Windows for the Pro plugin, #342;
- Fixed: Only load the checklists and resources in relevant pages, #129;
- Fixed: Fix method call is provided 2 parameters, but the method signature uses 1 parameters error, #179;
- Updated: Spanish and Italian translations, #348;

[2.7.0] - 16 Feb, 2022

- Added: Added capability "manage_checklists" to access Checklists screen, #173;
- Removed: Remove the icon from the admin heading;
- Fixed: Fix tabs layout in the settings page, #317;
- Fixed: Updated the Reviews library, fixing compatibility with our other plugins;
- Fixed: Add capability check before saving global checklists options (we already had a nonce check in place), #325;
- Fixed: Improved output escaping in the admin interface, #326;
- Fixed: Improved input sanitization, #324;
- Fixed: Fixed duplicated admin menu on PHP 8, #316;

[2.6.0] - SKIPP

Skipped version, for syncing the version number with the Pro plugin.

[2.5.3] - 15 Nov, 2021

- Fixed: Can't update published posts if requirements changed;
- Added: WordPress Reviews version 1.1.12;

[2.5.2] - 11 Nov, 2021

- Fixed: Missing logo image for ask-for-review banner;
- Added: Ask-for-review banner in other admin pages;

[2.5.1] - 11 Nov, 2021

- Fixed: Skip the comply of requirements when "Include pre-publish checklist" is disabled;
- Fixed: Preferences Panel box is broken;
- Fixed: Changed ID of span where full slug is picked up from with Classic Editor;
- Fixed: Border width for buttons;
- Added: Ask for plugin review support;

[2.5.0] - 22 Apr, 2021

- Added: Added drag-and-drop support for sorting the checklists requirements, #172;
- Fixed: Fixed default position of items in the checklist;
- Changed: Added support for displaying unit text in the checklist requirements settings page;

[2.4.4] - 31 Mar, 2021

- Fixed: Fixed link validation for "tel:" and "mailto:" links, #246
- Fixed: Fixed WPBakery compatibility, #237;

[2.4.3] - 30 Mar, 2021

- Fixed: Fixed support to PHP 5.6, #240;
- Fixed: Fixed some class names to match the filename, #241;
- Fixed: Fixed some strings that were not being translated;
- Fixed: Fixed detection of the Block Editor when the Classic Editor plugin is installed and the user can select which editor to use, #239;
- Fixed: Fixed a CSS conflict with the class "warning" and some themes, #243;
- Fixed: Fixed pre-publishing panel and warning when required items are unchecked, #252;
- Added: Added Italian translation. Huge thanks to Simone Bianchelli and Angelo Giammarresi for sharing the translation files;

[2.4.2] - 22 Oct, 2020

- Fixed: Remove unexistent dependencies for met-box.js, #231;

[2.4.1] - 08 Oct, 2020

- Fixed: Fix JS error Uncaught TypeError: Cannot read property 'doAction' of undefined, #224;
- Fixed: Fix broken menu item if the user doesn't have permissions to see the menu, #226;

[2.4.0] - 22 Sep, 2020

- Added: Added a new task for validating links in the content, #200;
- Added: Added a new task for checking the number of external links, #201;
- Added: Added form validation for required fields in the checklists page, #175;
- Added: Added a new task for requiring approval for specific roles, #104;
- Added: Added new field for custom tasks to select which role can check/uncheck the box, #104;
- Changed: Changed the order of tasks in the settings page, #223;
- Removed: The option "Recommended: show only in the sidebar" were removed and current settings fallback to "Recommended: show in the sidebar and before publishing", which was renamed to just: "Recommended", #195.

[2.3.2] - 20 Aug, 2020

- Fixed: Fixed warnings related to missed dependencies for scripts when the post type is not selected to use checklists, #208;

[2.3.1] - 14 Aug, 2020

- Fixed: Fixed compatibility with WP 5.5;
- Fixed: Fixed Gutenberg and Classic Editor detection, #203, #202;
- Fixed: Fixed invalid selector in jQuery, #197;
- Fixed: Fixed the publishing button that was stuck sometimes making impossible to publish a post, #191;

[2.3.0] - 06 Aug, 2020

- Added: Added new task for checking if all the images in the post has an "alt" attribute, #164;
- Fixed: Fixed the verification for custom taxonomies in the post editor page, #114;
- Fixed: Fixed style for unchecked custom tasks, #184;
- Fixed: Updated language files;
- Changed: Hide Yoast SEO tasks if Yoast's plugin is not activated, #164;
- Changed: Updated translation strings;
- Changed: Changed the algorithm of the Yoast SEO readability and SEO analysis verification, considering the selected score as the minimum score, #169;
- Changed: Change the label of the "Add custom item" button to "Add custom task", #181;

[2.2.0] - 21 Jul, 2020

- Added: Add support to Yoast SEO readability and SEO analysis pass task in the checklists - #86;
- Added: Add new task for checking the limit of chars in the excerpt test - #150;
- Added: Add new task for checking the number of internal links in the text - #52;
- Fixed: Remove not used transient for checking data migration;
- Fixed: JS error message related to missed PP_Checklists object;
- Fixed: Enqueue scripts only when required - #106;
- Fixed: Fixed translation support adding French and British English translations;
- Changed: Updated the PHP min requirement from 5.4 to 5.6;
- Changed: Updated the WordPress tested up to version to 5.4;
- Changed: Updated the label and text for some tasks;

[2.1.0] - 07 May, 2020

- Added: Add permalink validation rule for the checklists - #115;
- Added: Add option to select user roles to skip specific requirements - #131;
- Added: Add a menu link to upgrade to the Pro plan;
- Changed: Improve UI for custom items in the checklist, removing the "X" icon - #126;
- Removed: Remove the option to hide the Publish Button due to conflicts with Gutenberg;
- Fixed: Fixed the tabs for post types in the Checklists admin page. If you have too many post types the second line of tabs was overlaying the first line - #132;
- Fixed: Fixed the checklist warning popup when you are updating a published post and has unchecked required tasks in the checklist, for the classic editor - #124;
- Fixed: Fixed the list of available post types for the checklists to display any post type that has the show_ui = true. Non public post types are now recognized - #127;
- Fixed: Fixed the list of post types in the Checklists page hiding the tabs of post types that are not selected in the settings - #136;
- Fixed: Fixed the error displayed on Windows servers when the constant DIRECTORY_SEPARATOR is not defined;
- Fixed: Fixed empty checklists on fresh installs due to no post type being selected. Posts is selected by default now - #140;
- Fixed: Fix warning icon on Gutenberg moving it from the side to over the publish button - #138;

[2.0.2] - 16 Mar, 2020

- Fixed: Fix Checklist for custom hierarquical taxonomies when using Gutenberg;
- Fixed: Small improvements to the UI;
- Fixed: Fix compatibility with Rank Math fixing error in Gutenberg;
- Added: Added hooks to extend the interface for the Pro version;

[2.0.1] - 07 Feb, 2020

- Fixed: Fixed the prefix of post types in the post_type_support variable;
- Fixed: Adjusted the plugin URL for assets when working as vendor dependency;
- Fixed: Fix the suffix of the settings section from _post_types, to _general;
- Fixed: Fixed an undefined index error when the index "type" is not defined;
- Fixed: Fixed a JS error when you type in the editor and the word count requirement is set;
- Fixed: Fixed the verification for custom taxonomies on Gutenberg;
- Added: Added filters to allow using the plugin as base for the Pro plugin;

[2.0.0] - 03 Dec, 2019

- Fixed: Fixed the word counter for the Text tab in the Classic Editor;
- Changed: Renamed from "PublishPress Content Checklist" to "PublishPress Checklists";
- Changed: Refactored to be a standalone plugin, not requiring PublishPress anymore;
- Changed: Plugin name and text domain changed from "publishpress-content-checklist" to "publishpress-checklists";
- Changed: Namespace changed from "PublishPress\Addon\Content_checklist" to "PublishPress\Checklists\". The requirements' namespace changed from "PublishPress\Addon\Content_checklist\Requirement" to "PublishPress\Checklists\Core\Requirement";
- Changed: JavaScript object changed from "PP_Content_Checklist" to "PP_Checklists";
- Changed: New admin menu added with the checklists options and settings page;
- Changed: The checklists options section was removed from the settings page to an specific menu item;

[1.4.7] - 21 Jul, 2019

- Fixed: A JS error was preventing to block the post save action when displaying a popup with missed requirements on Classic Editor;

[1.4.6] - 20 Jun, 2019

- Fixed: Avoid JS white screen on Gutenberg "New Post" access by Author with Multiple Authors plugin active and "Remove author from new posts" setting enabled;
- Changed: Change minimum required version of PublishPress to 1.20.0;

[1.4.5] - 22 Feb, 2019

- Fixed: Fixed the pre-publishing check to avoid blocking save when not publishing;

[1.4.4] - 12 Feb, 2019

- Fixed: Fixed JS error that was preventing the Preview button to work properly in the classic editor;

[1.4.3] - 11 Feb, 2019

- Fixed: Fixed translation to PT-BR (thanks to Dionizio Bach);
- Fixed: Fixed bug when word-count script was not loaded;
- Fixed: Fixed JS error if an editor is not found;
- Changed: Changed the label for checklist options in the settings panel;

[1.4.2] - 30 Jan, 2019

- Fixed: Fixed the checklist for the block editor;
- Changed: Removed license key field from the settings tab;

[1.4.1] - 24 Jan, 2019

- Changed: Disable post types by default, if Gutenberg is installed;

[1.4.0] - 14 Jan, 2019

- Fixed: Fixed the TinyMCE plugin to count words to not load in the front-end when TinyMCE is initialized;
- Fixed: Fixed the assets loading to load tinymce-pp-checklists-requirements.js only in the admin;
- Fixed: Fixed conflict between custom taxonomies and tags in the checklist while counting items;
- Added: Added better support for custom post types and custom taxonomies which use WordPress default UI;
- Changed: Update POT file and fixed translations loading the text domain;
- Changed: Updated PT-BT language files;

[1.3.8] - 18 Apr, 2018

- Fixed: Fixed wrong reference to a legacy EDD library's include file;
- Fixed: Fixed PHP warning about undefined property and constant;

[1.3.7] - 21 Feb, 2018

- Fixed: Fixed support for custom post types;

[1.3.6] - 07 Feb, 2018

- Fixed: Fixed error about class EDD_SL_Plugin_Updater being loaded twice;

[1.3.5] - 06 Feb, 2018

- Fixed: Fixed saving action for custom items on the checklist;
- Fixed: Fixed license validation and automatic update;

[1.3.4] - 26 Jan, 2018

- Changed: Changed plugin headers, fixing author and text domain;

[1.3.3] - 26 Jan, 2018

- Fixed: Fixed JS error when the checklist is empty (no requirements are selected);
- Fixed: Fixed compatibility with PHP 5.4 (we will soon require min 5.6);
- Fixed: Fixed custom requirements;
- Fixed: Fixed the requirement of tags;
- Fixed: Fixed PHP Fatal error on some PHP on the featured image requirement;
- Fixed: Fixed category count in the checklist;
- Added: Added action to load plugins' script files;
- Changed: Rebranded to PublishPress;

[1.3.2] - 31 Aug, 2017

- Fixed: Fixed EDD integration and updates;
- Changed: Removed Freemius integration;

[1.3.1] - 13 Jul, 2017

- Fixed: Fixed support for custom post types allowing to use custom items as requirements;

[1.3.0] - 12 Jul, 2017

- Fixed: Fixed the delete button for custom items in the settings. It was remocing wrong items, in an odd pattern;
- Fixed: Fixed PHP warning in the settings page about undefined index in array;
- Fixed: Fixed the menu slug in the Freemius integration;
- Added: Added support for setting specific requirements for each post type, instead of global only;
- Changed: Changed the required minimum version of PublishPress to 1.6.0;
- Changed: Improved extensibility for add-ons;

[1.2.1] - 21 Jun, 2017

- Fixed: Fixed PHP warnings after install and activate
- Fixed: Fixed PHP warnings about wrong index type
- Fixed: Fixed the license and update checker
- Added: Added pt-BR translations
- Changed: Removed English language files
- Changed: Updated Tested Up to 4.8

[1.2.0] - 06 Jun, 2017

- Fixed: Fixes the mask for numeric input fields in the settings tab on Firefox
- Fixed: Fixes the license key validation
- Fixed: Fixes the update system
- Added: Added the option to hide the Publish button if the checklist is not completed
- Added: Added the option to add custom items for the checklist
- Added: Added POT file and English PO files
- Changed: The warning icon in the publish box now appears even for published content

[1.1.2] - 23 May, 2017

- Fixed: Fixes the word count feature
- Changed: Displays empty value in the max fields when max is less than min
- Changed: Improves the min and max fields for value equal 0. Displays empty fields.

[1.1.1] - 18 May, 2017

- Fixed: Removed .DS_Store file from the package
- Fixed: Fixed the "Hello Dolly" message in the Freemius opt-in dialog
- Fixed: Increased the minimum WordPress version to 4.6
- Changed: Improved settings merging the checkbox and the action list for each requirement
- Changed: Changed order for Categories and Tags to stay together in the list
- Changed: Changed code to use correct language domain

[1.1.0] - 11 May, 2017

- Added: Added "Excerpt has text" as requirement
- Added: Added option to set "max" value for the number of categories, tags and words - now you can have min, max or an interval for each requirement.
- Changed: Improved the JavaScript code for better readbility

[1.0.1] - 03 May, 2017

- Fixed: Fixed the name of plugin's main file
- Fixed: Fixed WordPress-EDD-License-Integration library in the vendor dir

[1.0.0] - 27 Apr, 2017

- Added: Added requirement for minimum number of words
- Added: Added requirement for featured image
- Added: Added requirement for minimum number of tags
- Added: Added requirement for minimum number of categories
- Added: Added Freemius integration for feedback and contact form
- Added: Added option to display a warning icon in the publish box
- Added: Added checklist to the post form
- Added: Added option to select specific post types
