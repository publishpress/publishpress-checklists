<?php
$show_required_legend = false;
use PublishPress\Checklists\Core\Utils\ElementorUtils;
?>

<div class="pp-checklists">
    <input type="hidden" name="<?php echo esc_attr($context['metadata_taxonomy']); ?>_nonce"
           value="<?php echo esc_attr($context['nonce']); ?>"/>

    <?php if (ElementorUtils::isElementorEnabled()) : ?>
        <p><em><?php echo esc_html__('Checklists tasks are not available in Elementor editors', 'publishpress-checklists'); ?></em></p>
    <?php else : ?>
        <div id="<?php echo esc_attr($context['metadata_taxonomy']); ?>-meta-box">
            <ul id="pp-checklists-req-box">
                <?php if (empty($context['requirements'])) : ?>
                    <p>
                        <?php
                        $message = sprintf(
                            esc_html($context['lang']['empty_checklist_message']),
                            '<a href="' . esc_url($context['configure_link']) . '">',
                            '</a>'
                        );
                        ?>
                        <em><?php echo $message;// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></em>
                    </p>
                <?php else : ?>
                    <?php foreach ($context['requirements'] as $key => $req) : ?>
                        <li
                                id="pp-checklists-req-<?php echo esc_attr($key); ?>"
                                class="pp-checklists-req metabox-req pp-checklists-<?php echo esc_attr($req['rule']); ?> status-<?php echo $req['status'] ? 'yes' : 'no'; ?> <?php echo $req['is_custom'] ? 'pp-checklists-custom-item' : ''; ?>"
                                data-id="<?php echo esc_attr($key); ?>"
                                data-type="<?php echo esc_attr($req['type']); ?>"
                                data-source="<?php echo !empty($req['source']) ? esc_attr($req['source']) : ''; ?>"
                                data-extra="<?php echo isset($req['extra']) ? esc_attr($req['extra']) : ''; ?>">

                            <?php if (!empty($req['is_custom']) || !empty($req['require_button'])) : ?>
                                <input type="hidden" name="_PPCH_custom_item[<?php echo esc_attr($req['id']); ?>]"
                                       value="<?php echo $req['status'] ? 'yes' : 'no'; ?>"/>
                            <?php endif; ?>

                            <?php
                            if ($req['is_custom']) :
                                $icon_class = $req['status'] ? 'dashicons-yes' : '';
                            else:
                                // Get custom icon settings
                                $legacyPlugin = \PublishPress\Checklists\Core\Factory::getLegacyPlugin();
                                $settings_options = isset($legacyPlugin->settings->module->options) ? $legacyPlugin->settings->module->options : null;
                                
                                // Use the same icons for both Required and Recommended
                                if ($req['status']) {
                                    $icon_class = isset($settings_options->complete_icon) ? $settings_options->complete_icon : 'dashicons-yes';
                                } else {
                                    $icon_class = isset($settings_options->incomplete_icon) ? $settings_options->incomplete_icon : 'dashicons-no';
                                }
                            endif;
                            ?>
                            <div class="status-icon dashicons <?php echo esc_attr($icon_class); ?>"></div>
                            <div class="status-label">
                                <?php echo wp_kses_post($req['label']); ?>
                                <?php if ($req['rule'] === 'block') : ?>
                                    <span class="required">*</span>
                                    <?php $show_required_legend = true; ?>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($req['require_button'])) : ?>
                                <button type="button" class="button button-secondary pp-checklists-check-item">
                                    <?php echo esc_html($context['lang']['check']); ?>
                                    <span class="spinner"></span>
                                </button>
                                <div class="request-response"></div>
                            <?php endif; ?>

                            <input type="hidden"
                                   name="ppch_item_<?php echo esc_attr($key); ?>" 
                                   class="ppch_item_requirement"
                                   id="ppch_item_<?php echo esc_attr($key); ?>"
                                   value="<?php echo $req['status'] ? 'yes' : 'no'; ?>"/>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>

            <?php if ($show_required_legend) : ?>
                <em>(*) <?php echo esc_html($context['lang']['required']); ?></em>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php # Modal Windows; ?>
<div class="remodal" data-remodal-id="pp-checklists-modal-alert"
     data-remodal-options="hashTracking: false, closeOnOutsideClick: false">
    <div id="pp-checklists-modal-alert-content"></div>
    <br>
    <button data-remodal-action="cancel" class="remodal-cancel"><?php echo esc_html($context['lang']['ok']); ?></button>
</div>

<div class="remodal" data-remodal-id="pp-checklists-modal-confirm"
     data-remodal-options="hashTracking: false, closeOnOutsideClick: false">
    <div id="pp-checklists-modal-confirm-content"></div>
    <br>
    <button data-remodal-action="cancel" class="remodal-cancel"><?php echo esc_html($context['lang']['no']); ?></button>
    <button data-remodal-action="confirm"
            class="remodal-confirm"><?php echo esc_html($context['lang']['yes']); ?></button>
</div>
