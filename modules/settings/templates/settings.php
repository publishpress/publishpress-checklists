<?php defined('ABSPATH') || exit; ?>
<div class="wrap content_checklist-admin <?php echo $context['show_sidebar'] ? 'allex_container_with_sidebar checklists_with_sidebar container' : ''; ?>">
    <div class="allex-row">
        <div class="<?php echo $context['show_sidebar'] ? 'allex-col-3-4' : 'allex-col-1'; ?>">
            <?php if ($context['show_tabs']) : ?>
                <h2 class="nav-tab-wrapper">
                    <?php foreach ($context['modules'] as $module) : ?>
                        <?php if (!empty($context['module']['options_page']) && $context['module']->options->enabled === 'on') : 
                            $module_link = add_query_arg(
                                [
                                    'page'      => esc_attr($context['slug']),
                                    'module'    => esc_attr($context['module']->settings_slug)
                                ],
                                admin_url('admin.php')
                            );
                            ?>
                            <a
                                    href="<?php echo esc_url($module_link); ?>"
                                    class="nav-tab <?php echo ($context['settings_slug'] == $context['module']->settings_slug) ? 'nav-tab-active' : ''; ?>">

                                <?php echo esc_html($context['module']->title); ?>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </h2>
            <?php endif; ?>

            <div class="ppch-settings"><?php echo esc_html($context['module_output']); ?></div>
        </div>

        <?php if ($context['show_sidebar']) : ?>
            <div class="allex-col-1-4">
                <?php echo esc_html($context['sidebar_output']); ?>
            </div>
        <?php endif; ?>
    </div>
</div>
