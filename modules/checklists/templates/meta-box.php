<?php
use PublishPress\Checklists\Core\Utils\ElementorUtils;

defined('ABSPATH') || exit;

$show_required_legend = false;
$show_rule_headings = !empty($context['show_rule_headings']);
$rule_heading_shown = [
    'block' => false,
    'warning' => false,
];
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
                        <?php if ($show_rule_headings && isset($req['rule']) && isset($rule_heading_shown[$req['rule']]) && !$rule_heading_shown[$req['rule']]) : ?>
                            <?php if ($req['rule'] === 'block') : ?>
                                <li class="pp-checklists-group-heading">
                                    <?php echo esc_html($context['lang']['required']); ?>
                                </li>
                                <?php $rule_heading_shown['block'] = true; ?>
                            <?php elseif ($req['rule'] === 'warning') : ?>
                                <li class="pp-checklists-group-heading">
                                    <?php echo esc_html($context['lang']['recommended']); ?>
                                </li>
                                <?php $rule_heading_shown['warning'] = true; ?>
                            <?php endif; ?>
                        <?php endif; ?>
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
                            // Get custom icon settings.
                            $legacyPlugin = \PublishPress\Checklists\Core\Factory::getLegacyPlugin();
                            $settings_options = isset($legacyPlugin->settings->module->options) ? $legacyPlugin->settings->module->options : null;

                            // Use the same icons for both Required and Recommended, including custom items.
                            if ($req['status']) {
                                $icon_class = (!empty($settings_options->complete_icon) && trim($settings_options->complete_icon) !== '') ? trim($settings_options->complete_icon) : 'dashicons-yes';
                            } else {
                                $icon_class = (!empty($settings_options->incomplete_icon) && trim($settings_options->incomplete_icon) !== '') ? trim($settings_options->incomplete_icon) : 'dashicons-no';
                            }
                            ?>
                            <div class="status-icon dashicons <?php echo esc_attr($icon_class); ?>" aria-hidden="true"></div>
                            <div class="status-label" id="pp-checklists-req-label-<?php echo esc_attr($key); ?>"
                                <?php if (!empty($req['is_custom'])) : ?>
                                    role="checkbox" tabindex="0" aria-checked="<?php echo $req['status'] ? 'true' : 'false'; ?>"
                                <?php endif; ?>>
                                <span class="screen-reader-text ppch-status-text"><?php echo esc_html($req['status'] ? $context['lang']['completed'] : $context['lang']['not_completed']); ?>:</span>
                                <?php if (!empty($req['is_custom'])) : ?>
                                    <?php // Custom task titles can be multi-line; only this span gets white-space: pre-line. ?>
                                    <span class="req-label"><?php echo wp_kses_post($req['label']); ?></span>
                                <?php else : ?>
                                    <?php echo wp_kses_post($req['label']); ?>
                                <?php endif; ?>
                                <?php if ($req['rule'] === 'block') : ?>
                                    <span class="required" aria-hidden="true">*</span>
                                    <span class="screen-reader-text">(<?php echo esc_html($context['lang']['required']); ?>)</span>
                                    <?php $show_required_legend = true; ?>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($req['require_button'])) : ?>
                                <button type="button" class="button button-secondary pp-checklists-check-item"
                                        aria-describedby="pp-checklists-req-label-<?php echo esc_attr($key); ?>">
                                    <?php echo esc_html($context['lang']['check']); ?>
                                    <span class="spinner"></span>
                                </button>
                                <div class="request-response" role="status"></div>
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
                <em aria-hidden="true">(<span class="required">*</span>) <?php echo esc_html($context['lang']['required']); ?></em>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php # Modal Windows; ?>
<div class="remodal" data-remodal-id="pp-checklists-modal-alert"
     role="alertdialog" aria-modal="true"
     aria-label="<?php echo esc_attr($context['lang']['modal_label']); ?>"
     aria-describedby="pp-checklists-modal-alert-content"
     data-remodal-options="hashTracking: false, closeOnOutsideClick: false, modifier: pp-checklists-modal">
    <div id="pp-checklists-modal-alert-content"></div>
    <br>
    <button type="button" data-remodal-action="cancel" class="remodal-cancel"><?php echo esc_html($context['lang']['ok']); ?></button>
</div>

<div class="remodal" data-remodal-id="pp-checklists-modal-confirm"
     role="alertdialog" aria-modal="true"
     aria-label="<?php echo esc_attr($context['lang']['modal_label']); ?>"
     aria-describedby="pp-checklists-modal-confirm-content"
     data-remodal-options="hashTracking: false, closeOnOutsideClick: false, modifier: pp-checklists-modal">
    <div id="pp-checklists-modal-confirm-content"></div>
    <br>
    <button type="button" data-remodal-action="cancel" class="remodal-cancel"><?php echo esc_html($context['lang']['no']); ?></button>
    <button type="button" data-remodal-action="confirm"
            class="remodal-confirm"><?php echo esc_html($context['lang']['yes']); ?></button>
</div>
