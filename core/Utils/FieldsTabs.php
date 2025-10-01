<?php
/**
 * @package     PublishPress\Checklists
 * @author      PublishPress <help@publishpress.com>
 * @copyright   copyright (C) 2019 PublishPress. All rights reserved.
 * @license     GPLv2 or later
 * @since       1.0.0
 */

namespace PublishPress\Checklists\Core\Utils;

use PublishPress\Checklists\Core\Legacy\Util;

class FieldsTabs {
    private static $instance = null;
    private $fields_tabs;

    private function __construct() {
        $this->fields_tabs = array(
            "title" => array(
                "label" => __('Title', 'publishpress-checklists'),
                "icon" => "dashicons dashicons-edit"
            ),
            "content" => array(
                "label" => __('Content', 'publishpress-checklists'),
                "icon" => "dashicons dashicons-welcome-write-blog"
            ),
            "publish_date_time" => array(
                "label" => __('Publish Date / Time', 'publishpress-checklists'),
                "icon" => "dashicons dashicons-calendar-alt"
            ),
            "approval" => array(
                "label" => __('Approval', 'publishpress-checklists'),
                "icon" => "dashicons dashicons-yes"
            ),
            "images" => array(
                "label" => __('Images', 'publishpress-checklists'),
                "icon" => "dashicons dashicons-format-image"
            ),
            "audio_video" => array(
                "label" => __('Audio / Video', 'publishpress-checklists'),
                "icon" => "dashicons dashicons-admin-media"
            ),
            "featured_image" => array(
                "label" => __('Featured Image', 'publishpress-checklists'),
                "icon" => "dashicons dashicons-cover-image"
            ),
            "links" => array(
                "label" => __('Links', 'publishpress-checklists'),
                "icon" => "dashicons dashicons-admin-links"
            ),
            "permalinks" => array(
                "label" => __('Permalink', 'publishpress-checklists'),
                "icon" => "dashicons dashicons-editor-unlink"
            ),
            "categories" => array(
                "label" => __('Categories', 'publishpress-checklists'),
                "icon" => "dashicons dashicons-category"
            ),
            "tags" => array(
                "label" => __('Tags', 'publishpress-checklists'),
                "icon" => "dashicons dashicons-tag"
            ),
            "taxonomies" => array(
                "label" => __('Taxonomies', 'publishpress-checklists'),
                "icon" => "dashicons dashicons-list-view"
            ),
            "accessibility" => array(
                "label" => __('Accessibility', 'publishpress-checklists'),
                "icon" => "dashicons dashicons-universal-access"
            ),
            "custom" => array(
                "label" => __('Custom', 'publishpress-checklists'),
                "icon" => "dashicons dashicons-admin-generic"
            )
        );
        
        $custom_plugins_tabs = $this->initialize_custom_plugins_tabs();

        //add custom plugins tabs
        $this->fields_tabs = $this->insertTabsBeforeKey($this->fields_tabs, $custom_plugins_tabs, 'custom');
        
        add_filter('publishpress_checklists_filter_field_tabs', [$this, 'filterFieldTabs'], 10, 2);

    }

    /**
     * Initializes the custom plugins array based on activated plugins.
     *
     * @return array The array of custom plugin configurations.
     */
    private function initialize_custom_plugins_tabs()
    {
        $custom_plugins = array();
        if (Util::isWooCommerceActivated()) {
            $custom_plugins["woocommerce"] = array(
                "label" => esc_html__('WooCommerce', 'publishpress-checklists'),
                "icon" => "pp-checklists-tab-custom-icon",
                "svg" => '<svg version="1.1" xmlns="[http://www.w3.org/2000/svg"](http://www.w3.org/2000/svg") width="16" height="16" viewBox="0 0 85.9 47.6"><path fill="#655997" d="M77.4,0.1c-4.3,0-7.1,1.4-9.6,6.1L56.4,27.7V8.6c0-5.7-2.7-8.5-7.7-8.5s-7.1,1.7-9.6,6.5L28.3,27.7V8.8c0-6.1-2.5-8.7-8.6-8.7H7.3C2.6,0.1,0,2.3,0,6.3s2.5,6.4,7.1,6.4h5.1v24.1c0,6.8,4.6,10.8,11.2,10.8S33,45,36.3,38.9l7.2-13.5v11.4c0,6.7,4.4,10.8,11.1,10.8s9.2-2.3,13-8.7l16.6-28c3.6-6.1,1.1-10.8-6.9-10.8C77.3,0.1,77.3,0.1,77.4,0.1z"/></svg>',
            );
        }
        if (Util::isRankMathActivated()) {
            $custom_plugins["rank_math"] = array(
                "label" => esc_html__('Rank Math SEO', 'publishpress-checklists'),
                "icon" => "pp-checklists-tab-custom-icon",
                "svg" => '<svg viewBox="0 0 462.03 462.03" xmlns="[http://www.w3.org/2000/svg"](http://www.w3.org/2000/svg") width="16"><g fill="#655997"><path d="m462 234.84-76.17 3.43 13.43 21-127 81.18-126-52.93-146.26 60.97 10.14 24.34 136.1-56.71 128.57 54 138.69-88.61 13.43 21z"/><path d="m54.1 312.78 92.18-38.41 4.49 1.89v-54.58H54.1zm210.9-223.57v235.05l7.26 3 89.43-57.05v-181zm-105.44 190.79 96.67 40.62v-165.19h-96.67z"/></g></svg>',
            );
        }
        if (Util::isAllInOneSeoActivated()) {
            $custom_plugins["all_in_one_seo"] = array(
                "label" => esc_html__('All in One SEO', 'publishpress-checklists'),
                "icon" => "pp-checklists-tab-custom-icon",
                "svg" => '<svg xmlns="[http://www.w3.org/2000/svg"](http://www.w3.org/2000/svg") viewBox="0 0 20 20" width="16" height="16" fill="#655997" class="aioseo-gear"><path fill-rule="evenodd" clip-rule="evenodd" d="M9.98542 19.9708C15.5002 19.9708 19.9708 15.5002 19.9708 9.98542C19.9708 4.47063 15.5002 0 9.98542 0C4.47063 0 0 4.47063 0 9.98542C0 15.5002 4.47063 19.9708 9.98542 19.9708ZM8.39541 3.65464C8.26016 3.4485 8.0096 3.35211 7.77985 3.43327C7.51816 3.52572 7.26218 3.63445 7.01349 3.7588C6.79519 3.86796 6.68566 4.11731 6.73372 4.36049L6.90493 5.22694C6.949 5.44996 6.858 5.6763 6.68522 5.82009C6.41216 6.04734 6.16007 6.30426 5.93421 6.58864C5.79383 6.76539 5.57233 6.85907 5.35361 6.81489L4.50424 6.6433C4.26564 6.5951 4.02157 6.70788 3.91544 6.93121C3.85549 7.05738 3.79889 7.1862 3.74583 7.31758C3.69276 7.44896 3.64397 7.58105 3.59938 7.71369C3.52048 7.94847 3.61579 8.20398 3.81839 8.34133L4.53958 8.83027C4.72529 8.95617 4.81778 9.1819 4.79534 9.40826C4.75925 9.77244 4.76072 10.136 4.79756 10.4936C4.82087 10.7198 4.72915 10.9459 4.54388 11.0724L3.82408 11.5642C3.62205 11.7022 3.52759 11.9579 3.60713 12.1923C3.69774 12.4593 3.8043 12.7205 3.92615 12.9743C4.03313 13.1971 4.27749 13.3088 4.51581 13.2598L5.36495 13.0851C5.5835 13.0401 5.80533 13.133 5.94623 13.3093C6.16893 13.5879 6.42071 13.8451 6.6994 14.0756C6.87261 14.2188 6.96442 14.4448 6.92112 14.668L6.75296 15.5348C6.70572 15.7782 6.81625 16.0273 7.03511 16.1356C7.15876 16.1967 7.285 16.2545 7.41375 16.3086C7.54251 16.3628 7.67196 16.4126 7.80195 16.4581C8.18224 16.5912 8.71449 16.1147 9.108 15.7625C9.30205 15.5888 9.42174 15.343 9.42301 15.0798C9.42301 15.0784 9.42302 15.077 9.42302 15.0756L9.42301 13.6263C9.42301 13.6109 9.4236 13.5957 9.42476 13.5806C8.26248 13.2971 7.39838 12.2301 7.39838 10.9572V9.41823C7.39838 9.30125 7.49131 9.20642 7.60596 9.20642H8.32584V7.6922C8.32584 7.48312 8.49193 7.31364 8.69683 7.31364C8.90171 7.31364 9.06781 7.48312 9.06781 7.6922V9.20642H11.0155V7.6922C11.0155 7.48312 11.1816 7.31364 11.3865 7.31364C11.5914 7.31364 11.7575 7.48312 11.7575 7.6922V9.20642H12.4773C12.592 9.20642 12.6849 9.30125 12.6849 9.41823V10.9572C12.6849 12.2704 11.7653 13.3643 10.5474 13.6051C10.5477 13.6121 10.5478 13.6192 10.5478 13.6263L10.5478 15.0694C10.5478 15.3377 10.6711 15.5879 10.871 15.7622C11.2715 16.1115 11.8129 16.5837 12.191 16.4502C12.4527 16.3577 12.7086 16.249 12.9573 16.1246C13.1756 16.0155 13.2852 15.7661 13.2371 15.5229L13.0659 14.6565C13.0218 14.4334 13.1128 14.2071 13.2856 14.0633C13.5587 13.8361 13.8107 13.5792 14.0366 13.2948C14.177 13.118 14.3985 13.0244 14.6172 13.0685L15.4666 13.2401C15.7052 13.2883 15.9493 13.1756 16.0554 12.9522C16.1153 12.8261 16.1719 12.6972 16.225 12.5659C16.2781 12.4345 16.3269 12.3024 16.3714 12.1698C16.4503 11.935 16.355 11.6795 16.1524 11.5421L15.4312 11.0532C15.2455 10.9273 15.153 10.7015 15.1755 10.4752C15.2116 10.111 15.2101 9.74744 15.1733 9.38986C15.1499 9.16361 15.2417 8.93757 15.4269 8.811L16.1467 8.31927C16.3488 8.18126 16.4432 7.92558 16.3637 7.69115C16.2731 7.42411 16.1665 7.16292 16.0447 6.90915C15.9377 6.68638 15.6933 6.57462 15.455 6.62366L14.6059 6.79837C14.3873 6.84334 14.1655 6.75048 14.0246 6.57418C13.8019 6.29554 13.5501 6.03832 13.2714 5.80784C13.0982 5.6646 13.0064 5.43858 13.0497 5.2154L13.2179 4.34868C13.2651 4.10521 13.1546 3.85616 12.9357 3.74787C12.8121 3.68669 12.6858 3.62895 12.5571 3.5748C12.4283 3.52065 12.2989 3.47086 12.1689 3.42537C11.9388 3.34485 11.6884 3.44211 11.5538 3.64884L11.0746 4.38475C10.9513 4.57425 10.73 4.66862 10.5082 4.64573C10.1513 4.6089 9.79502 4.61039 9.44459 4.64799C9.22286 4.67177 9.00134 4.57818 8.87731 4.38913L8.39541 3.65464Z" fill="#655997"/></svg>',
            );
        }
        if (Util::isYoastSeoActivated()) {
            $custom_plugins["yoastseo"] = array(
                "label" => esc_html__('Yoast SEO', 'publishpress-checklists'),
                "icon" => "pp-checklists-tab-custom-icon",
                "svg" => '<svg role="img" aria-hidden="true" focusable="false" xmlns="[http://www.w3.org/2000/svg"](http://www.w3.org/2000/svg") viewBox="0 0 466 500"><g fill="#655997"><path d="M80.13 444.1a73.98 73.98 0 0 1-7.17-1.86c-2.32-.73-4.63-1.58-6.88-2.53-1.11-.47-2.22-.98-3.32-1.51-1.63-.79-3.34-1.71-5.22-2.81-1.64-.96-2.97-1.79-4.19-2.62a65.68 65.68 0 0 1-2.94-2.1 79.203 79.203 0 0 1-5.56-4.59c-3.32-3.02-6.42-6.41-9.22-10.05-1-1.31-1.84-2.47-2.57-3.55-1.35-2-2.62-4.08-3.77-6.18a74.774 74.774 0 0 1-9.08-35.67V155.75c0-12.43 3.14-24.76 9.08-35.67 1.15-2.11 2.42-4.19 3.77-6.18a75.902 75.902 0 0 1 26.47-24.06 74.378 74.378 0 0 1 35.66-9.08h185.93l7.22-20.06H95.19C42.78 60.69.13 103.34.13 155.75v214.88c0 52.42 42.64 95.06 95.06 95.06h12.41v-20.06H95.19c-5.07 0-10.13-.52-15.06-1.53ZM404.01 66.68l-1.55-.58-7.02 18.83 1.54.58c3.29 1.24 6.49 2.7 9.5 4.34a75.902 75.902 0 0 1 26.47 24.06c1.35 1.99 2.62 4.07 3.77 6.18a74.803 74.803 0 0 1 9.08 35.67v289.88H256.06l-.48.83c-3.36 5.88-6.86 11.48-10.41 16.65l-1.77 2.59h222.46V155.75c0-39.44-24.86-75.24-61.86-89.07Z"/></g><path fill="#655997" d="M332.89 0 226.81 294.64l-52.14-163.3h-63.75l79.68 204.68c7.4 19 7.39 39.91 0 58.89-7.72 19.81-21.48 43.45-59.57 50.45l-1.71.31V500l2.17-.08c31.83-1.25 56.51-11.75 77.69-33.03 21.54-21.65 40.01-55.32 58.13-105.93L400.91 2.82 401.96 0h-69.07Z"/></svg>'
            );
        }

        if (Util::isACFActivated()) {
            $custom_plugins["advanced-custom-fields"] = array(
                "label" => esc_html__('ACF', 'publishpress-checklists'),
                "icon" => "dashicons dashicons-welcome-widgets-menus",
            );
        }

        return $custom_plugins; // Make sure to return the array!
    }

    public function filterFieldTabs($postTypes, $allPostTypes)
    {
        foreach ($postTypes as $key => $postType) {
            if ($key !== 'product') {
                $postTypes[$key] = array_filter($allPostTypes, function ($_, $key) {
                    return !in_array($key, ['woocommerce']);
                }, ARRAY_FILTER_USE_BOTH);
            }
        }

        return $postTypes;
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new FieldsTabs();
        }
        return self::$instance;
    }

    public function getFieldsTabs() {
        return $this->fields_tabs;
    }

    private function insertTabsBeforeKey($array, $tabsToInsert, $beforeKey = null) {
        // If a single tab is passed, convert to array
        if (isset($tabsToInsert['label']) && isset($tabsToInsert['icon'])) {
            // $tabsToInsert is a single tab, not an array of tabs
            $tabsToInsert = [uniqid('tab_') => $tabsToInsert]; // or pass the key as a 4th param
        }

        $newArray = array();
        foreach ($array as $key => $value) {
            if ($beforeKey !== null && $key === $beforeKey) {
                foreach ($tabsToInsert as $insertKey => $insertValue) {
                    $newArray[$insertKey] = $insertValue;
                }
            }
            $newArray[$key] = $value;
        }
        if ($beforeKey === null || !array_key_exists($beforeKey, $array)) {
            foreach ($tabsToInsert as $insertKey => $insertValue) {
                $newArray[$insertKey] = $insertValue;
            }
        }
        return $newArray;
    }

    public function addTab($key, $label, $icon, $svg = null, $beforeKey = null) {
        $new_tab = array(
            "label" => $label,
            "icon" => $icon,
            "svg" => $svg
        );

        $this->fields_tabs = $this->insertTabsBeforeKey(
            $this->fields_tabs,
            [$key => $new_tab], // wrap in array
            $beforeKey
        );
    }

    public function removeTab($key) {
        if (isset($this->fields_tabs[$key])) {
            unset($this->fields_tabs[$key]);
        }
    }
}