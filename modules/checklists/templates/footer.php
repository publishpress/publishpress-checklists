<br style="clear: both;">
<footer>
    <div class="pp-rating">
        <?php
        $plugin_name_markup  = '<strong>' . esc_html($context['plugin_name']) . '</strong>';
        $rating_stars_markup = '<span class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span><span
                class="dashicons dashicons-star-filled"></span><span
                class="dashicons dashicons-star-filled"></span><span class="dashicons dashicons-star-filled"></span>';
        ?>

        <a
                href="<?php echo esc_url("//wordpress.org/support/plugin/{$context['plugin_slug']}/reviews/#new-post"); ?>"
                target="_blank"
                rel="noopener noreferrer">
            <?php echo sprintf($context['rating_message'], $plugin_name_markup, $rating_stars_markup);// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </a>
    </div>
    <hr>
    <nav>
        <ul>
            <li>
                <a href="//publishpress.com/checklists" target="_blank" rel="noopener noreferrer"
                   title="<?php esc_attr_e('About PublishPress Checklists', 'publishpress-checklists');?>"><?php echo esc_html__(
                        'About',
                        'publishpress-checklists'
                    ); ?></a>
            </li>
            <li>
                <a href="//publishpress.com/knowledge-base/checklist-introduction/" target="_blank" rel="noopener noreferrer"
                   title="<?php esc_attr_e('PublishPress Checklists Documentation', 'publishpress-checklists');?>"><?php echo esc_html__(
                        'Documentation',
                        'publishpress-checklists'
                    ); ?></a>
            </li>
            <li>
                <a href="//publishpress.com/publishpress-support/" target="_blank" rel="noopener noreferrer"
                   title="<?php esc_attr_e('Contact the PublishPress team', 'publishpress-checklists');?>"><?php echo esc_html__('Contact', 'publishpress-checklists'); ?></a>
            </li>
            
        </ul>
    </nav>
    <div class="pp-pressshack-logo">
        <a href="//publishpress.com" target="_blank" rel="noopener noreferrer">
            <img src="<?php echo esc_url($context['plugin_url'].'modules/checklists/assets/img/publishpress-logo.png'); ?>">
        </a>
    </div>
</footer>
</div> <?php # .publishpress-checklists-admin.pressshack-admin-wrapper.wrap
