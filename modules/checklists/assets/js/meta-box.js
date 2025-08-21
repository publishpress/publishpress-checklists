/**
 * @package PublishPress
 * @author PublishPress
 *
 * Copyright (C) 2018 PublishPress
 *
 * ------------------------------------------------------------------------------
 * Based on Edit Flow
 * Author: Daniel Bachhuber, Scott Bressler, Mohammad Jangda, Automattic, and
 * others
 * Copyright (c) 2009-2016 Mohammad Jangda, Daniel Bachhuber, et al.
 * ------------------------------------------------------------------------------
 *
 * This file is part of PublishPress
 *
 * PublishPress is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * PublishPress is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PublishPress.  If not, see <http://www.gnu.org/licenses/>.
 */

(function ($, window, document, counter) {
  'use strict';

  /**
   * This variable is deprecated. Use ppChecklists instead.
   * Added here just for backward compatibility with other
   * plugins.
   *
   * @deprecated 1.4.0
   */
  window.objectL10n_checklist_requirements = ppChecklists;

  /*----------  Handler  ----------*/

  /**
   * Object for handling the requirements in the post form.
   * @type {Object}
   */
  var PP_Checklists = {
    /**
     * Constant for the event validate_requirements
     * @type {String}
     */
    EVENT_VALIDATE_REQUIREMENTS: 'pp-checklists:validate_requirements',

    /**
     * Constant for the event tick. Triggered by a setInterval
     * @type {String}
     */
    EVENT_TIC: 'pp-checklists:tic',

    /**
     * Constant for the event update_requirement_state
     * @type {String}
     */
    EVENT_UPDATE_REQUIREMENT_STATE: 'pp-checklists:update_requirement_state',

    /**
     * Constant for the event toggle_custom_item
     * @type {String}
     */
    EVENT_TOGGLE_CUSTOM_ITEM: 'pp-checklists:toggle_custom_item',

    /**
     * Constant for the event tinymce_loaded
     * @type {String}
     */
    EVENT_TINYMCE_LOADED: 'pp-checklists:tinymce_loaded',

    /**
     * Constant for the interval of the tic event
     * @type {Number}
     */
    TIC_INTERVAL: 300,

    /**
     * List of interface elements
     * @type {Object}
     */
    elems: {
      original_post_status: $('#original_post_status'),
      post_status: $('#post_status'),
      publish_button: $('#publish'),
      document: $(document),
    },

    /**
     * Stores the states for the object.
     * @type {Object}
     */
    state: {
      /**
       * Flag for the publishing state
       * @type {boolean}
       */
      is_publishing: false,

      /**
       * Flag for the confirmed state
       * @type {boolean}
       */
      is_confirmed: false,

      /**
       * Flag for the should_block state
       * @type {boolean}
       */
      should_block: false,

      /**
       * Flag to say the validate method is already being executed.
       * @type {boolean}
       */
      is_validating: false,
    },

    /**
     * Initialize the object and events
     * @return {void}
     */
    init: function () {
      // Create a custom event
      this.elems.document.on(
        this.EVENT_VALIDATE_REQUIREMENTS,
        function (event) {
          this.validate_requirements(event);
        }.bind(this),
      );

      // On clicking the submit button
      this.elems.publish_button.click(
        function () {
          this.state.is_publishing = true;
        }.bind(this),
      );

      // On clicking the confirmation button in the modal window
      this.elems.document.on(
        'confirmation',
        '.remodal',
        function () {
          this.state.is_confirmed = true;

          // Trigger the publish button
          this.elems.publish_button.trigger('click');

          // For some reason, adding this again after the click is trigged solved the acf conflict issue https://github.com/publishpress/PublishPress-Checklists/issues/506
          this.state.is_confirmed = true;
        }.bind(this),
      );

      if (!this.is_gutenberg_active()) {
        // Hook to the submit button
        $('form#post').submit(
          function (event) {
            //do not trigger for preview action
            if ($('input#wp-preview').val() === 'dopreview') {
              return true;
            }

            // Reset the should_block state
            this.state.should_block = false;

            this.elems.document.trigger(this.EVENT_VALIDATE_REQUIREMENTS);

            return !this.state.should_block;
          }.bind(this),
        );
      } else {
        $(document).on(
          this.EVENT_TIC,
          function (event) {
            if (this.state.is_validating !== false) {
              return;
            }

            var isSidebarOpened = wp.data.select('core/edit-post').isPublishSidebarOpened();

            if (isSidebarOpened && !this.state.is_validating) {
              this.elems.document.trigger(this.EVENT_VALIDATE_REQUIREMENTS);
            }
          }.bind(this),
        );
      }

      // Hook to the requirement items
      $('[id^=pp-checklists-req]').on(
        this.EVENT_UPDATE_REQUIREMENT_STATE,
        function (event, state) {
          this.update_requirement_icon(state, $(event.target));
        }.bind(this),
      );

      // Add event to the custom items
      $('.pp-checklists-custom-item').click(
        function (event) {
          var target = event.target;

          if ('LI' !== target.nodeNAME) {
            target = $(target).parent('li')[0];
          }

          if (typeof target !== 'undefined') {
            this.elems.document.trigger(this.EVENT_TOGGLE_CUSTOM_ITEM, $(target));
          }
        }.bind(this),
      );

      // Add event to the button custom items
      this.elems.document.on(
        'click',
        '.pp-checklists-req .pp-checklists-check-item',
        function (event) {
          event.preventDefault();
          var target = $(event.target);
          var target_li = target.closest('li');
          var global_this = this;

          $('.pp-checklists-req').find('.request-response').html('');

          if (typeof target_li !== 'undefined') {
            target_li.find('.pp-checklists-check-item').prop('disabled', true);
            target_li.find('.spinner').addClass('is-active');

            var data = {
              action: 'pp_checklists_' + target_li.attr('data-source') + '_requirement',
              requirement: ppChecklists.requirements[target_li.attr('data-id')],
              content: PP_Checklists.get_editor_content(),
              nonce: ppChecklists.nonce,
            };

            $.post(ajaxurl, data, function (response) {
              var response_raw_content = response.content;
              var response_content = response_raw_content.replace(/\n/g, '<br>');
              if (response.yes_no == 'yes') {
                $('#pp-checklists-req-' + target_li.attr('data-id'))
                  .find('.dashicons')
                  .removeClass('dashicons-yes');
                global_this.elems.document.trigger(
                  global_this.EVENT_TOGGLE_CUSTOM_ITEM,
                  $('#pp-checklists-req-' + target_li.attr('data-id')),
                );
              } else if (response.yes_no == 'no') {
                $('#pp-checklists-req-' + target_li.attr('data-id'))
                  .find('.dashicons')
                  .addClass('dashicons-yes');
                global_this.elems.document.trigger(
                  global_this.EVENT_TOGGLE_CUSTOM_ITEM,
                  $('#pp-checklists-req-' + target_li.attr('data-id')),
                );
              }
              target_li
                .find('.request-response')
                .html(
                  '<div id="message" class="ppch-message notice is-dismissible updated published"><p>' +
                  response_content +
                  '</p><button type="button" class="notice-dismiss" onclick="this.closest(\'#message\').remove();"><span class="screen-reader-text">Dismiss this notice.</span></button></div>',
                );
              target_li.find('.pp-checklists-check-item').prop('disabled', false);
              target_li.find('.spinner').removeClass('is-active');
            }).fail(function (jqXHR, textStatus, errorThrown) {
              target_li
                .find('.request-response')
                .html(
                  '<div id="message" class="ppch-message notice is-dismissible updated published"><p>' +
                  errorThrown +
                  ' ' +
                  textStatus +
                  '</p><button type="button" class="notice-dismiss" onclick="this.closest(\'#message\').remove();"><span class="screen-reader-text">Dismiss this notice.</span></button></div>',
                );
              target_li.find('.pp-checklists-check-item').prop('disabled', false);
              target_li.find('.spinner').removeClass('is-active');
            });
          }
        }.bind(this),
      );

      // On clicking the confirmation button in the modal window
      this.elems.document.on(
        this.EVENT_TOGGLE_CUSTOM_ITEM,
        function (event, item) {
          var $item = $(item),
            $icon = $item.children('.dashicons'),
            checked = $icon.hasClass('dashicons-yes');

          $icon.removeClass('dashicons-no');

          if (checked) {
            $icon.removeClass('dashicons-yes');
            $item.removeClass('status-yes');
            $item.addClass('status-no');
            $item.find('.ppch_item_requirement').val('no');
            wp.hooks.doAction('pp-checklists.requirements-updated', $item);
          } else {
            $icon.addClass('dashicons-yes');
            $item.addClass('status-yes');
            $item.removeClass('status-no');
            $item.find('.ppch_item_requirement').val('yes');
            wp.hooks.doAction('pp-checklists.requirements-updated', $item);
          }

          $item.children('input[type="hidden"]').val($item.hasClass('status-yes') ? 'yes' : 'no');
        }.bind(this),
      );

      // Start the tic event
      setInterval(
        function () {
          $(document).trigger(this.EVENT_TIC);
        }.bind(this),
        this.TIC_INTERVAL,
      );
    },

    /**
     * Check if the current post is already published
     * @return {Boolean} True if published.
     */
    is_published: function () {
      return 'publish' === this.elems.original_post_status.val();
    },

    /**
     * Check if the current post status is pending
     * @return {Boolean} True if pending.
     */
    is_pending: function () {
      return 'pending' === this.elems.original_post_status.val();
    },

    /**
     * Check if the current post status is draft
     * @return {Boolean} True if draft.
     */
    is_draft: function () {
      return (
        'draft' === this.elems.original_post_status.val() || 'auto-draft' === this.elems.original_post_status.val()
      );
    },

    /**
     * Validates the requirements and show the warning, blocking or not the
     * submission, according to the config. Returns false if the submission
     * should be blocked.
     *
     * @param  {[type]} event
     * @return {Boolean}
     */
    validate_requirements: function (event) {
      this.state.is_validating = true;
      this.state.should_block = false;

      // Bypass all checks because the confirmation button was clicked.
      if (this.state.is_confirmed) {
        this.state.is_confirmed = false;
        this.state.is_validating = false;

        return;
      }

      var uncheckedItems = {
        block: [],
        warning: [],
      };

      /**
       * Check the element of the requirement, to see if it is marked
       * as incomplete.
       *
       * @param  {Object} $req The DOM element
       * @param  {Array}  list The list to inject the requirement, if incomplete
       * @return {void}
       */
      var checkRequirement = function ($reqElem, list) {
        if ($reqElem.hasClass('status-no')) {
          // Check if the requirement is not ok
          var $uncheckedRequirements = $reqElem.find('.status-label');

          if ($uncheckedRequirements.length > 0) {
            list.push($uncheckedRequirements.html().trim());
          }
        }
      }.bind(this);

      var checkRequirementAction = function (actionType) {
        var $elems = $('.pp-checklists-req.metabox-req.pp-checklists-' + actionType);

        for (var i = 0; i < $elems.length; i++) {
          checkRequirement($($elems[i]), uncheckedItems[actionType]);
        }
      }.bind(this);

      // Check if any of the requirements is set to trigger warnings
      checkRequirementAction('warning');
      checkRequirementAction('block');

      if (this.is_gutenberg_active()) {
        this.state.is_publishing = wp.data.select('core/edit-post').isPublishSidebarOpened();
      }

      var originalPostStatus = this.elems.original_post_status.val(),
        isPublishingThePost = this.state.is_publishing,
        isUpdatingPublishedPost = this.getCurrentPostStatus() === 'publish' && originalPostStatus === 'publish';

      if (isPublishingThePost || isUpdatingPublishedPost) {
        var showBlockMessage = uncheckedItems.block.length > 0,
          showWarning = uncheckedItems.warning.length > 0,
          gutenbergLockName = 'pp-checklists';

        if (showWarning || showBlockMessage) {
          this.state.should_block = true;

          var message = '';

          if (showBlockMessage) {
            if (PP_Checklists.is_gutenberg_active()) {
              wp.data.dispatch('core/editor').lockPostSaving(gutenbergLockName);
              wp.hooks.doAction('pp-checklists.update-failed-requirements', uncheckedItems);
            } else {
              if (isUpdatingPublishedPost) {
                message = ppChecklists.msg_missed_required_updating;
              } else {
                message = ppChecklists.msg_missed_required_publishing;
              }

              message +=
                '<div class="pp-checklists-modal-list"><ul><li>' +
                uncheckedItems.block.join('</li><li>') +
                '</li></ul></div>';

              if (uncheckedItems.warning.length > 0) {
                if (isUpdatingPublishedPost) {
                  message += ppChecklists.msg_missed_important_updating;
                } else {
                  message += ppChecklists.msg_missed_important_publishing;
                }

                message +=
                  '<div class="pp-checklists-modal-list"><ul><li>' +
                  uncheckedItems.warning.join('</li><li>') +
                  '</li></ul></div>';
              }

              // Display the alert
              $('#pp-checklists-modal-alert-content').html(message);
              $('[data-remodal-id=pp-checklists-modal-alert]').remodal().open();
            }
          } else if (showWarning) {
            if (PP_Checklists.is_gutenberg_active()) {
              wp.data.dispatch('core/editor').unlockPostSaving(gutenbergLockName);
              wp.hooks.doAction('pp-checklists.update-failed-requirements', uncheckedItems);
            } else {
              // Only display a warning
              if (isUpdatingPublishedPost) {
                message = ppChecklists.msg_missed_optional_updating;
              } else {
                message = ppChecklists.msg_missed_optional_publishing;
              }

              message +=
                '<div class="pp-checklists-modal-list"><ul><li>' +
                uncheckedItems.warning.join('</li><li>') +
                '</li></ul></div>';

              if (uncheckedItems.block.length > 0) {
                message +=
                  ppChecklists.msg_missed_required +
                  '<div class="pp-checklists-modal-list"><ul><li>' +
                  uncheckedItems.block.join('</li><li>') +
                  '</li></ul></div>';
              }

              // Display the confirm
              $('#pp-checklists-modal-confirm-content').html(message);
              $('[data-remodal-id=pp-checklists-modal-confirm]').remodal().open();
            }
          }
        } else {
          if (PP_Checklists.is_gutenberg_active()) {
            wp.data.dispatch('core/editor').unlockPostSaving(gutenbergLockName);
            wp.hooks.doAction('pp-checklists.update-failed-requirements', uncheckedItems);
          }

          this.state.is_publishing = false;
          this.state.is_validating = false;

          return;
        }
      } else {
        // we only need the failed counts to be triggered for panel validation
        wp.hooks.doAction('pp-checklists.update-failed-requirements', uncheckedItems);
      }

      this.state.is_publishing = false;
      this.state.is_validating = false;
    },

    getCurrentPostStatus: function () {
      if (PP_Checklists.is_gutenberg_active()) {
        return wp.data.select('core/editor').getEditedPostAttribute('status');
      } else {
        return this.elems.post_status.val();
      }
    },

    /**
     * Updates the icon in the requirement checklist according to the
     * current state.
     *
     * @param  {Boolean} is_completed
     * @param  {Object}  $element
     * @return {void}
     */
    update_requirement_icon: function (is_completed, $element) {
      var $icon_element = $element.find('.dashicons');
      if (is_completed) {
        // Ok
        $icon_element.removeClass('dashicons-no');
        $icon_element.addClass('dashicons-yes');
        $icon_element.parent().removeClass('status-no');
        $icon_element.parent().addClass('status-yes');
        $element.find('.ppch_item_requirement').val('yes');
        wp.hooks.doAction('pp-checklists.requirements-updated', $element);
      } else {
        // Not ok
        $icon_element.removeClass('dashicons-yes');
        $icon_element.addClass('dashicons-no');
        $icon_element.parent().removeClass('status-yes');
        $icon_element.parent().addClass('status-no');
        $element.find('.ppch_item_requirement').val('no');
        wp.hooks.doAction('pp-checklists.requirements-updated', $element);
      }
    },

    /**
     * Check if the value is valid, based on the min and max values.
     * It makes a smarty check based on the following:
     *
     *  - Both same value = exact
     *  - Min not empty, max empty or < min = only min
     *  - Min not empty, max not empty and > min = both min and max
     *  - Min empty, max not empty and > min = only max
     *
     * @param  {Float} count
     * @param  {Float} min_value
     * @param  {Float} max_value
     *
     * @return {Bool}
     */
    check_valid_quantity: function (count, min_value, max_value) {
      var is_valid = false;

      // Both same value = exact
      if (min_value === max_value) {
        is_valid = count === min_value;
      }

      // Min not empty, max empty or < min = only min
      if (min_value > 0 && max_value < min_value) {
        is_valid = count >= min_value;
      }

      // Min not empty, max not empty and > min = both min and max
      if (min_value > 0 && max_value > min_value) {
        is_valid = count >= min_value && count <= max_value;
      }

      // Min empty, max not empty and > min = only max
      if (min_value === 0 && max_value > min_value) {
        is_valid = count <= max_value;
      }

      return is_valid;
    },

    /**
     * Check for internal link from content and return result as array
     *
     *  - remove image inside tags so we don't count them as link
     *  - remove element inside <a href></a> to avoid double counting for one link in case of <a href="Link">Link</a>
     *  - check for every valid link and return array
     *  - loop array and return only valid internal links excluding other images url
     *
     * @param  {String} content
     * @param  {Array} links
     * @param  {String} website
     *
     * @return {Array}
     */
    extract_internal_links: function (content, links = [], website = window.location.host) {
      var link;
      if (content) {
        //remove image inside tags so we don't count them as link
        content = content.replace(/<img[^>]*>/g, '');

        //remove element inside <a href></a> to avoid double counting for one link in case of <a href="Link">Link</a>
        // Add spaces around each link to ensure they're separated
        content = content.replace(/<a [^>]*href="([^"']*).*?<\/a>/g, ' $1 ');

        //check for every valid link and return array
        content = content.match(/(https?:\/\/(?:www\.|(?!www))[^\s\.]+\.[^\s]{2,}|www\.[^\s]+\.[^\s]{2,})/gi);

        //loop array and return only valid internal links excluding other images url
        if (content) {
          for (link of content) {
            //skip if link is image
            if (link.match(/\.(jpeg|jpg|gif|png|svg)$/)) continue;
            //skip if link has different host than current website
            if (link.indexOf(website) < 0) continue;
            //add valid link to array
            links.push(link);
          }
        }
      }

      return links;
    },

    /**
     * Check for external link from content and return result as array
     *
     *  - remove image inside tags so we don't count them as link
     *  - remove element inside <a href></a> to avoid double counting for one link in case of <a href="Link">Link</a>
     *  - check for every valid link and return array
     *  - loop array and return only valid external links excluding other images url
     *
     * @param  {String} content
     * @param  {Array} links
     * @param  {String} website
     *
     * @return {Array}
     */
    extract_external_links: function (content, links = [], website = window.location.host) {
      var link,
        match,
        regex = /<a.*?href=["\']([^"\']+)["\'].*?\>(.*?)\<\/a\>/gi;
      if (content) {
        //check for external link and return array excluding other images url
        while ((match = regex.exec(content)) !== null) {
          link = match[1];

          //skip if link is image
          if (link.match(/\.(jpeg|jpg|gif|png|svg)$/)) continue;
          //skip if link point to the current website host
          if (link.indexOf(website) > 0) continue;
          //add valid link to array
          links.push(link);
        }
      }

      return links;
    },

    /**
     * Check for images without alt text from content and return result as array
     *
     * @param  {String} content
     * @param  {Array} missing_alt
     *
     * @return {Array}
     */
    missing_alt_images: function (content, missing_alt = []) {
      var alt,
        regex = /<img[^>]*>/g;

      if (content) {
        var imgTags = content.match(regex) || [];
        imgTags.forEach(function (imgTag) {
          alt = imgTag.match(/alt="([^"]*)"/);

          if (!alt || !alt[1].replace(/\s/g, '').length) {
            missing_alt.push(imgTag);
          }
        });
      }

      return missing_alt;
    },

    get_image_alt_lengths: function (content) {
      var lengths = [];
      var regex = /<img[^>]+alt=(['"])(.*?)\1[^>]*>/gi;
      var match;

      while ((match = regex.exec(content)) !== null) {
        lengths.push(match[2].trim().length);
      }

      return lengths;
    },

    extract_links_from_content: function (content) {
      let linksIterator = content.matchAll(/(?:<a[^>]+href=['"])([^'"]+)(?:['"][^>]*>)/gi);

      let linkResult = linksIterator.next();
      let linksList = [];

      while (!linkResult.done) {
        linksList.push(linkResult.value[1]);

        linkResult = linksIterator.next();
      }

      return linksList;
    },

    is_valid_link: function (link) {
      if (link.startsWith('#')) {
        return true;
      }

      const linkWithoutFragment = link.split('#')[0];

      return linkWithoutFragment.match(
        /^(?:(#[-a-zA-Z0-9@:%._\+~#=]{0,256})|https?:\/\/(?:www\.)?[-a-zA-Z0-9@:%._\+~#=]{1,256}\.[a-zA-Z0-9()]{1,6}\b(?:[-a-zA-Z0-9()@;:%_\+.~#?&\/\/=!*'(),]*)|tel:\+?[0-9\-]+|mailto:[a-z0-9\-_\.]+@[a-z0-9\-_\.]+?[a-z0-9@\.\?=\s\%,\-&_;*]+)$/i,
      );
    },

    /**
     * Check for links without http(s)
     *
     * @param  {String} content
     * @param  {Array} invalid_links
     *
     * @return {Array}
     */
    validate_links_format: function (content, invalid_links = []) {
      if (!content) {
        return [];
      }

      // Extract links from the href attribute.
      let linksList = PP_Checklists.extract_links_from_content(content);

      for (let i = 0; i < linksList.length; i++) {
        if (!PP_Checklists.is_valid_link(linksList[i])) {
          invalid_links.push(linksList[i]);
        }
      }

      return invalid_links;
    },

    /**
     * Returns true if the Gutenberg editor is active on the page.
     *
     * @returns {boolean}
     */
    is_gutenberg_active: function () {
      let gutenbergActive = false;
      if (
        typeof wp.data !== 'undefined' &&
        typeof wp.data.select('core') !== 'undefined' &&
        typeof wp.data.select('core/edit-post') !== 'undefined' &&
        typeof wp.data.select('core/editor') !== 'undefined'
      ) {
        gutenbergActive = true;
      }

      return gutenbergActive;
    },

    /**
     * Returns editor content.
     *
     * @returns {boolean}
     */
    get_editor_content: function () {
      let data = '';

      try {
        // Gutenberg
        data = PP_Checklists.getEditor().getEditedPostAttribute('content');
      } catch (error) {
        try {
          // TinyMCE
          let ed = tinyMCE.activeEditor;
          if ('mce_fullscreen' == ed.id) {
            tinyMCE.get('content').setContent(
              ed.getContent({
                format: 'raw',
              }),
              {
                format: 'raw',
              },
            );
          }
          tinyMCE.get('content').save();
          data = jQuery('#content').val();
        } catch (error) {
          try {
            // Quick Tags
            data = jQuery('#content').val();
          } catch (error) {}
        }
      }

      // Trim data
      data = data.replace(/^\s+/, '').replace(/\s+$/, '');

      return data;
    },

    /**
     * Add a style tag.
     *
     * @param id
     * @param css
     */
    add_style_tag: function (id, css) {
      var $head = $('head');

      if ($head.find('#' + id).length === 0) {
        var $style = $('<style>');

        $style.attr('type', 'text/css');
        $style.text(css);
        $style.attr('id', id);
        $head.append($style);
      }
    },

    /**
     *
     * @param id
     */
    remove_style_tag: function (id) {
      $('#' + id).remove();
    },

    /**
     * Return the editor
     *
     * @returns {object}
     */
    getEditor: function () {
      return wp.data.select('core/editor');
    },

    /**
     * This function checks whether a post has a featured image or not.
     *
     * - For Gutenberg, it checks the featured_media attribute of the post.
     * - For the Classic Editor, it checks the set-post-thumbnail element.
     * @returns {boolean}
     */
    hasFeaturedImage: function () {
      var has_image = false;

      if (PP_Checklists.is_gutenberg_active()) {
        has_image = PP_Checklists.getEditor().getEditedPostAttribute('featured_media') > 0;
      } else {
        has_image = $('#postimagediv').find('#set-post-thumbnail').find('img').length > 0;
      }

      return has_image;
    },
  };

  // Exposes and initialize the object
  window.PP_Checklists = PP_Checklists;

  if (typeof rankMath !== 'undefined' && typeof YoastSEO !== 'undefined') {
    setTimeout(function () {
      PP_Checklists.init();
    }, 4000);
  } else if (typeof rankMath !== 'undefined') {
    setTimeout(function () {
      PP_Checklists.init();
    }, 3000);
  } else if (typeof YoastSEO !== 'undefined') {
    setTimeout(function () {
      PP_Checklists.init();
    }, 3000);
  } else {
    PP_Checklists.init();
  }

  /*----------  Warning icon in submit button  ----------*/
  // Need this validation for status filter in Pro to work
  if (ppChecklists.show_warning_icon_submit) {
    $(document).on(PP_Checklists.EVENT_TIC, function (event) {
      var has_unchecked = $('#pp-checklists-req-box').children('.status-no');
      if (has_unchecked.length > 0) {
        $('body').addClass('ppch-show-publishing-warning-icon');
      } else {
        $('body').removeClass('ppch-show-publishing-warning-icon');
      }
    });
  }

  /*----------  Disable publish button  ----------*/
  // Disable first save button until requirements are meet when "Include pre-publish checklist" is disabled
  // @TODO Figure out how to get the status of "Include pre-publish checklist" and add it to the if() below
  if (ppChecklists.disable_publish_button) {
    $(window).on('load', function () {
      if (
        PP_Checklists.is_gutenberg_active() &&
        ((PP_Checklists.is_published() !== true && PP_Checklists.is_pending() !== true) ||
          !ppChecklists.disable_published_block_feature)
      ) {
        $(document).on(PP_Checklists.EVENT_TIC, function (event) {
          var has_unchecked_block = $('#pp-checklists-req-box').children('.status-no.pp-checklists-block');
          if (has_unchecked_block.length > 0) {
            wp.data.dispatch('core/editor').lockPostSaving('ppcPublishButton');
          } else {
            wp.data.dispatch('core/editor').unlockPostSaving('ppcPublishButton');
          }
        });
      }
    });
  }

  /*----------  Featured Image  ----------*/

  if ($('#pp-checklists-req-featured_image').length > 0) {
    $(document).on(PP_Checklists.EVENT_TIC, function (event) {
      var has_image = PP_Checklists.hasFeaturedImage();

      $('#pp-checklists-req-featured_image').trigger(PP_Checklists.EVENT_UPDATE_REQUIREMENT_STATE, has_image);
    });
  }

  /*----------  Featured Image Alt  ----------*/
  // Check if the featured image is set or not
  if ($('#pp-checklists-req-featured_image_alt').length > 0) {
    let loaded = false,
      meta_id = 0,
      meta_alt = '';
    let featured_image_alt = {};
    const updateFeaturedImageAlt = (id, alt) => {
      meta_id = Number(id);
      meta_alt = alt;
      featured_image_alt = { [meta_id]: meta_alt };
      loaded = true;
    };
    if (PP_Checklists.is_gutenberg_active()) {
      wp.data.subscribe(function () {
        if (loaded) return;
        const mediaId = PP_Checklists.getEditor().getEditedPostAttribute('featured_media');
        if (mediaId) {
          const dataMedia = wp.data.select('core').getMedia(mediaId);
          if (typeof dataMedia === 'object' && dataMedia) {
            updateFeaturedImageAlt(mediaId, dataMedia.alt_text);
          }
        }
      });
    } else {
      updateFeaturedImageAlt(
        $('#_thumbnail_id').val(),
        $('#postimagediv').find('#set-post-thumbnail').find('img').attr('alt'),
      );
    }
    $(document).on(PP_Checklists.EVENT_TIC, function (event) {
      if (!loaded) return;
      let has_alt = true,
        has_image = PP_Checklists.hasFeaturedImage();
      if (has_image) {
        has_alt = Boolean(featured_image_alt[meta_id]);
      }

      if ($('#attachment-details-alt-text').length > 0) {
        const callableFunc = function () {
          const current_alt = $('#attachment-details-alt-text').val();
          const previous_alt = featured_image_alt[meta_id] ?? '';
          if (current_alt !== previous_alt) {
            featured_image_alt[meta_id] = current_alt;
          }
        };
        $('#attachment-details-alt-text')
          .ready(function () {
            $('.attachments-wrapper li').each(function () {
              if ($(this).attr('aria-checked') === 'true') {
                const selected_id = Number($(this).attr('data-id'));
                // Only update if this is the featured image
                if (selected_id === meta_id) {
                  callableFunc();
                }
              }
            });
          })
          .on('change', function () {
            // Only update alt text if we're editing the featured image
            const selected_id = Number($('.attachments-wrapper li[aria-checked="true"]').attr('data-id'));
            if (selected_id === meta_id) {
              callableFunc();
            }
          });
      }
      $('#pp-checklists-req-featured_image_alt').trigger(PP_Checklists.EVENT_UPDATE_REQUIREMENT_STATE, has_alt);
    });
  }

  /*----------  Featured Image Caption  ----------*/
  // Check if the featured image is set or not
  if ($('#pp-checklists-req-featured_image_caption').length > 0) {
    let loaded = false,
      meta_id = 0,
      meta_caption = '';
    let featured_image_caption = {};
    const updateFeaturedImageCaption = (id, caption) => {
      meta_id = Number(id);
      meta_caption = caption;
      featured_image_caption = { [meta_id]: meta_caption };
      loaded = true;
    };
    if (PP_Checklists.is_gutenberg_active()) {
      wp.data.subscribe(function () {
        if (loaded) return;
        const mediaId = PP_Checklists.getEditor().getEditedPostAttribute('featured_media');
        if (mediaId) {
          const dataMedia = wp.data.select('core').getMedia(mediaId);
          if (typeof dataMedia === 'object' && dataMedia) {
            let actualCaption = '';
            if (dataMedia.caption) {
              if (typeof dataMedia.caption === 'object' && dataMedia.caption.raw !== undefined) {
                actualCaption = dataMedia.caption.raw;
              } else if (typeof dataMedia.caption === 'string') {
                actualCaption = dataMedia.caption;
              }
            }
            updateFeaturedImageCaption(mediaId, actualCaption);
          }
        }
      });
    } else {
      updateFeaturedImageCaption(
        $('#_thumbnail_id').val(),
        $('#postimagediv').find('#set-post-thumbnail').find('img').attr('alt'),
      );
    }
    $(document).on(PP_Checklists.EVENT_TIC, function (event) {
      if (!loaded) return;
      let has_caption = true,
        has_image = PP_Checklists.hasFeaturedImage();
      if (has_image) {
        has_caption = Boolean(featured_image_caption[meta_id]);
      }

      if ($('#attachment-details-caption').length > 0) {
        const callableFunc = function () {
          const current_caption = $('#attachment-details-caption').val();
          const previous_caption = featured_image_caption[meta_id] ?? '';
          if (current_caption !== previous_caption) {
            featured_image_caption[meta_id] = current_caption;
          }
        };
        $('#attachment-details-caption')
          .ready(function () {
            $('.attachments-wrapper li').each(function () {
              if ($(this).attr('aria-checked') === 'true') {
                meta_id = Number($(this).attr('data-id'));
                callableFunc();
              }
            });
          })
          .on('change', callableFunc);
      }
      $('#pp-checklists-req-featured_image_caption').trigger(PP_Checklists.EVENT_UPDATE_REQUIREMENT_STATE, has_caption);
    });
  }

  /*---------- Tags Number  ----------*/

  if ($('#pp-checklists-req-tags_count').length > 0) {
    $(document).on(PP_Checklists.EVENT_TIC, function (event) {
      var count = 0,
        min_value = parseInt(ppChecklists.requirements.tags_count.value[0]),
        max_value = parseInt(ppChecklists.requirements.tags_count.value[1]);

      /**
       * For Gutenberg
       */
      if (PP_Checklists.is_gutenberg_active()) {
        // @todo: why does Multiple Authors "Remove author from new posts" setting cause this to return null?
        var obj = PP_Checklists.getEditor().getEditedPostAttribute('tags');
      } else {
        /**
         * For the Classic Editor
         */
        var obj = $('#post_tag.tagsdiv ul.tagchecklist').children('li');
      }

      if (typeof obj !== 'undefined') {
        count = obj.length;

        $('#pp-checklists-req-tags_count').trigger(
          PP_Checklists.EVENT_UPDATE_REQUIREMENT_STATE,
          PP_Checklists.check_valid_quantity(count, min_value, max_value),
        );
      }
    });
  }

  /*----------  Required Tags  ----------*/

  if ($('#pp-checklists-req-required_tags').length > 0) {
    $(document).on(PP_Checklists.EVENT_TIC, function (event) {
      let obj = PP_Checklists.is_gutenberg_active()
        ? PP_Checklists.getEditor().getEditedPostAttribute('tags')
        : $('.tagchecklist li')
          .map((_, el) =>
            $(el)
              .contents()
              .filter((_, node) => node.nodeType === 3)
              .text()
              .trim(),
          )
          .get();

      if (typeof obj !== 'undefined') {
        let { value: required_tags, label } = ppChecklists.requirements.required_tags;
        let required_tags_reached =
          required_tags.length > 0
            ? required_tags.filter((value) => {
              if (PP_Checklists.is_gutenberg_active()) return !obj.includes(Number(value.split('__')[0]));
              return !obj.includes(value.split('__')[1]);
            })
            : [];
        let has_required_tags = required_tags_reached.length > 0;

        const el = $('#pp-checklists-req-required_tags');
        const labelEl = el.find('.status-label');
        const current_label_text = label.replace(/:.*/, '');
        const required_tags_str = required_tags_reached.map((el) => el.split('__')[1]).join(', ');
        const final_label_text =
          required_tags_str.length > 0 ? `${current_label_text}: ${required_tags_str} ` : `${current_label_text} `;

        el.trigger(PP_Checklists.EVENT_UPDATE_REQUIREMENT_STATE, !has_required_tags);
        // Need to update the text node directly because the element has a span inside
        labelEl
          .contents()
          .filter(function () {
            return this.nodeType === 3; // Node type 3 is a text node
          })
          .first()
          .each(function () {
            // Modify the text node
            this.nodeValue = final_label_text;
          });
      }
    });
  }

  /*----------  Prohibited Tags  ----------*/

  if ($('#pp-checklists-req-prohibited_tags').length > 0) {
    $(document).on(PP_Checklists.EVENT_TIC, function (event) {
      let obj = PP_Checklists.is_gutenberg_active()
        ? PP_Checklists.getEditor().getEditedPostAttribute('tags')
        : $('.tagchecklist li')
          .map((_, el) =>
            $(el)
              .contents()
              .filter((_, node) => node.nodeType === 3)
              .text()
              .trim(),
          )
          .get();

      if (typeof obj !== 'undefined') {
        let { value: prohibited_tags, label } = ppChecklists.requirements.prohibited_tags;
        let prohibited_tags_reached =
          prohibited_tags.length > 0
            ? prohibited_tags.filter((value) => {
              if (PP_Checklists.is_gutenberg_active()) return obj.includes(Number(value.split('__')[0]));
              return obj.includes(value.split('__')[1]);
            })
            : [];
        let has_prohibited_tags = prohibited_tags_reached.length > 0;

        const el = $('#pp-checklists-req-prohibited_tags');
        const labelEl = el.find('.status-label');
        const current_label_text = label.replace(/:.*/, '');
        const prohibited_tags_str = prohibited_tags_reached.map((el) => el.split('__')[1]).join(', ');
        const final_label_text =
          prohibited_tags_str.length > 0 ? `${current_label_text}: ${prohibited_tags_str} ` : `${current_label_text} `;

        ppChecklists = {
          ...ppChecklists,
          requirements: {
            ...ppChecklists.requirements,
            prohibited_tags: {
              ...ppChecklists.requirements.prohibited_tags,
              label: final_label_text,
            },
          },
        };
        el.trigger(PP_Checklists.EVENT_UPDATE_REQUIREMENT_STATE, !has_prohibited_tags);
        // Need to update the text node directly because the element has a span inside
        labelEl
          .contents()
          .filter(function () {
            return this.nodeType === 3; // Node type 3 is a text node
          })
          .first()
          .each(function () {
            // Modify the text node
            this.nodeValue = final_label_text;
          });
      }
    });
  }

  /*----------  Categories Number  ----------*/

  if ($('#pp-checklists-req-categories_count').length > 0) {
    $(document).on(PP_Checklists.EVENT_TIC, function (event) {
      var count = 0,
        min_value = parseInt(ppChecklists.requirements.categories_count.value[0]),
        max_value = parseInt(ppChecklists.requirements.categories_count.value[1]);

      if (PP_Checklists.is_gutenberg_active()) {
        // @todo: why does Multiple Authors "Remove author from new posts" setting cause this to return null?
        var obj = PP_Checklists.getEditor().getEditedPostAttribute('categories');
      } else {
        var obj = $('#categorychecklist input:checked:not(.rank-math-make-primary)');
      }

      if (typeof obj !== 'undefined') {
        count = obj.length;

        $('#pp-checklists-req-categories_count').trigger(
          PP_Checklists.EVENT_UPDATE_REQUIREMENT_STATE,
          PP_Checklists.check_valid_quantity(count, min_value, max_value),
        );
      }
    });
  }

  /*----------  Required Categories  ----------*/

  if ($('#pp-checklists-req-required_categories').length > 0) {
    $(document).on(PP_Checklists.EVENT_TIC, function (event) {
      let obj = PP_Checklists.is_gutenberg_active()
        ? PP_Checklists.getEditor().getEditedPostAttribute('categories')
        : $('#categorychecklist input:checked')
          .map((_, chkEl) => Number($(chkEl).val()))
          .get();

      if (typeof obj !== 'undefined') {
        let { value: required_categories, label } = ppChecklists.requirements.required_categories;
        let required_categories_reached =
          required_categories.length > 0
            ? required_categories.filter((value) => !obj.includes(Number(value.split('__')[0])))
            : [];
        let has_required_categories = required_categories_reached.length > 0;

        const el = $('#pp-checklists-req-required_categories');
        const labelEl = el.find('.status-label');
        const current_label_text = label.replace(/:.*/, '');
        const required_categories_str = required_categories_reached.map((el) => el.split('__')[1]).join(', ');
        const final_label_text =
          required_categories_str.length > 0
            ? `${current_label_text}: ${required_categories_str} `
            : `${current_label_text} `;

        el.trigger(PP_Checklists.EVENT_UPDATE_REQUIREMENT_STATE, !has_required_categories);
        // Need to update the text node directly because the element has a span inside
        labelEl
          .contents()
          .filter(function () {
            return this.nodeType === 3; // Node type 3 is a text node
          })
          .first()
          .each(function () {
            // Modify the text node
            this.nodeValue = final_label_text;
          });
      }
    });
  }

  /*----------  Prohibited Categories  ----------*/

  if ($('#pp-checklists-req-prohibited_categories').length > 0) {
    $(document).on(PP_Checklists.EVENT_TIC, function (event) {
      let obj = PP_Checklists.is_gutenberg_active()
        ? PP_Checklists.getEditor().getEditedPostAttribute('categories')
        : $('#categorychecklist input:checked')
          .map((_, chkEl) => Number($(chkEl).val()))
          .get();

      if (typeof obj !== 'undefined') {
        let { value: prohibited_categories, label } = ppChecklists.requirements.prohibited_categories;
        let prohibited_categories_reached =
          prohibited_categories.length > 0
            ? prohibited_categories.filter((value) => obj.includes(Number(value.split('__')[0])))
            : [];
        let has_prohibited_categories = prohibited_categories_reached.length > 0;

        const el = $('#pp-checklists-req-prohibited_categories');
        const labelEl = el.find('.status-label');
        const current_label_text = label.replace(/:.*/, '');
        const prohibited_categories_str = prohibited_categories_reached.map((el) => el.split('__')[1]).join(', ');
        const final_label_text =
          prohibited_categories_str.length > 0
            ? `${current_label_text}: ${prohibited_categories_str} `
            : `${current_label_text} `;

        ppChecklists = {
          ...ppChecklists,
          requirements: {
            ...ppChecklists.requirements,
            prohibited_categories: {
              ...ppChecklists.requirements.prohibited_categories,
              label: final_label_text,
            },
          },
        };
        el.trigger(PP_Checklists.EVENT_UPDATE_REQUIREMENT_STATE, !has_prohibited_categories);
        // Need to update the text node directly because the element has a span inside
        labelEl
          .contents()
          .filter(function () {
            return this.nodeType === 3; // Node type 3 is a text node
          })
          .first()
          .each(function () {
            // Modify the text node
            this.nodeValue = final_label_text;
          });
      }
    });
  }

  /*----------  Hierarchical Taxonomies Number  ----------*/

  if ($('[data-type^="taxonomy_counter_hierarchical_"]').length > 0) {
    $('[data-type^="taxonomy_counter_hierarchical_"]').each(function (index, elem) {
      $(document).on(PP_Checklists.EVENT_TIC, function (event) {
        var taxonomy = $(elem).data('type').replace('taxonomy_counter_hierarchical_', ''),
          taxonomy_rest_base = $(elem).data('extra'),
          count = 0,
          min_value = parseInt(ppChecklists.requirements[taxonomy + '_count'].value[0]),
          max_value = parseInt(ppChecklists.requirements[taxonomy + '_count'].value[1]);
        var obj = '';
        if (PP_Checklists.is_gutenberg_active()) {
          if (taxonomy_rest_base && taxonomy_rest_base !== '' && taxonomy_rest_base !== 'false') {
            obj = PP_Checklists.getEditor().getEditedPostAttribute(taxonomy_rest_base);
          } else {
            obj = PP_Checklists.getEditor().getEditedPostAttribute(taxonomy);
          }
        } else {
          obj = $('#' + taxonomy + 'checklist input:checked');
        }

        if (typeof obj !== 'undefined') {
          count = obj.length;

          $('#pp-checklists-req-' + taxonomy + '_count').trigger(
            PP_Checklists.EVENT_UPDATE_REQUIREMENT_STATE,
            PP_Checklists.check_valid_quantity(count, min_value, max_value),
          );
        }
      });
    });
  }

  /*----------  Non-hierarchical Taxonomies Number  ----------*/

  if ($('[data-type^="taxonomy_counter_non_hierarchical_"]').length > 0) {
    $('[data-type^="taxonomy_counter_non_hierarchical_"]').each(function (index, elem) {
      $(document).on(PP_Checklists.EVENT_TIC, function (event) {
        var taxonomy = $(elem).data('type').replace('taxonomy_counter_non_hierarchical_', ''),
          count = 0,
          min_value = parseInt(ppChecklists.requirements[taxonomy + '_count'].value[0]),
          max_value = parseInt(ppChecklists.requirements[taxonomy + '_count'].value[1]),
          obj = null;

        if (PP_Checklists.is_gutenberg_active()) {
          obj = PP_Checklists.getEditor().getEditedPostAttribute(taxonomy);
        } else {
          obj = $('#' + taxonomy + ' .tagchecklist').children('li');
        }

        if (typeof obj !== 'undefined') {
          count = obj.length;

          $('#pp-checklists-req-' + taxonomy + '_count').trigger(
            PP_Checklists.EVENT_UPDATE_REQUIREMENT_STATE,
            PP_Checklists.check_valid_quantity(count, min_value, max_value),
          );
        }
      });
    });
  }

  /*----------  Filled in Excerpt  ----------*/

  if ($('#pp-checklists-req-filled_excerpt').length > 0) {
    $(document).on(PP_Checklists.EVENT_TIC, function (event) {
      var count = 0,
        min_value = parseInt(ppChecklists.requirements.filled_excerpt.value[0]),
        max_value = parseInt(ppChecklists.requirements.filled_excerpt.value[1]);

      if (PP_Checklists.is_gutenberg_active()) {
        // @todo: why does Multiple Authors "Remove author from new posts" setting cause this to return null?
        var obj = PP_Checklists.getEditor().getEditedPostAttribute('excerpt');
      } else {
        if ($('#excerpt').length === 0) {
          return;
        }

        var obj = $('#excerpt').val();
      }

      if (typeof obj !== 'undefined') {
        count = obj.length;

        $('#pp-checklists-req-filled_excerpt').trigger(
          PP_Checklists.EVENT_UPDATE_REQUIREMENT_STATE,
          PP_Checklists.check_valid_quantity(count, min_value, max_value),
        );
      }
    });
  }

  /*----------  Title Count  ----------*/

  if ($('#pp-checklists-req-title_count').length > 0) {
    $(document).on(PP_Checklists.EVENT_TIC, function (event) {
      var count = 0,
        obj = null,
        min_value = parseInt(ppChecklists.requirements.title_count.value[0]),
        max_value = parseInt(ppChecklists.requirements.title_count.value[1]);

      if (PP_Checklists.is_gutenberg_active()) {
        // @todo: why does Multiple Authors "Remove author from new posts" setting cause this to return null?
        obj = wp.htmlEntities.decodeEntities(PP_Checklists.getEditor().getEditedPostAttribute('title'));
      } else {
        if ($('#title').length === 0) {
          return;
        }

        obj = $('#title').val();
      }

      if (typeof obj !== 'undefined') {
        count = obj.length;

        $('#pp-checklists-req-title_count').trigger(
          PP_Checklists.EVENT_UPDATE_REQUIREMENT_STATE,
          PP_Checklists.check_valid_quantity(count, min_value, max_value),
        );
      }
    });
  }

  /*----------  Word Count ----------*/
  var lastCount = 0;
  if (PP_Checklists.is_gutenberg_active()) {
    /**
     * For Gutenberg
     */
    if ($('#pp-checklists-req-words_count').length > 0) {
      wp.data.subscribe(function () {
        // @todo: why does Multiple Authors "Remove author from new posts" setting cause this to return null?
        var content = PP_Checklists.getEditor().getEditedPostAttribute('content');

        if (typeof content == 'undefined') {
          return;
        }

        var count = wp.utils.WordCounter.prototype.count(content);

        if (lastCount == count) {
          return;
        }

        var min = parseInt(ppChecklists.requirements.words_count.value[0]),
          max = parseInt(ppChecklists.requirements.words_count.value[1]);

        $('#pp-checklists-req-words_count').trigger(
          PP_Checklists.EVENT_UPDATE_REQUIREMENT_STATE,
          PP_Checklists.check_valid_quantity(count, min, max),
        );

        lastCount = count;
      });
    }
  } else {
    /**
     * For the Classic Editor
     */
    var $content = $('#content');
    var lastCount = 0;
    var editor;

    /**
     * Get the words count from TinyMCE and update the status of the requirement
     */
    function update() {
      var text, count;

      if (typeof ppChecklists.requirements.words_count === 'undefined') {
        return;
      }

      if (typeof editor == 'undefined' || !editor || editor.isHidden()) {
        // For the text tab.
        text = $content.val();
      } else {
        // For the editor tab.
        text = editor.getContent({ format: 'raw' });
      }

      count = counter.count(text);

      if (lastCount === count) {
        return;
      }

      var min = parseInt(ppChecklists.requirements.words_count.value[0]),
        max = parseInt(ppChecklists.requirements.words_count.value[1]);

      $('#pp-checklists-req-words_count').trigger(
        PP_Checklists.EVENT_UPDATE_REQUIREMENT_STATE,
        PP_Checklists.check_valid_quantity(count, min, max),
      );

      lastCount = count;
    }

    // For the editor.
    $(document).on(PP_Checklists.EVENT_TINYMCE_LOADED, function (event, tinymce) {
      editor = tinymce.editors['content'];

      if (typeof editor !== 'undefined') {
        editor.onInit.add(function () {
          /**
           * Bind the words count update triggers.
           *
           * When a node change in the main TinyMCE editor has been triggered.
           * When a key has been released in the plain text content editor.
           */

          if (editor.id !== 'content') {
            return;
          }

          editor.on('nodechange keyup', _.debounce(update, 500));
        });
      }
    });

    $content.on('input keyup', _.debounce(update, 500));
    update();
  }

  /*----------  Internal Links ----------*/
  var lastInternalCount = 0;
  if (PP_Checklists.is_gutenberg_active()) {
    /**
     * For Gutenberg
     */
    if ($('#pp-checklists-req-internal_links').length > 0) {
      wp.data.subscribe(function () {
        setTimeout(function() {
          var content = PP_Checklists.getEditor().getEditedPostAttribute('content');

          if (typeof content == 'undefined') {
            return;
          }

          var count = PP_Checklists.extract_internal_links(content).length;

          if (lastInternalCount == count) {
            return;
          }

          var min = parseInt(ppChecklists.requirements.internal_links.value[0]),
            max = parseInt(ppChecklists.requirements.internal_links.value[1]);

          $('#pp-checklists-req-internal_links').trigger(
            PP_Checklists.EVENT_UPDATE_REQUIREMENT_STATE,
            PP_Checklists.check_valid_quantity(count, min, max),
          );

          lastInternalCount = count;
        }, 100);
      });
    }
  } else {
    /**
     * For the Classic Editor
     */
    var $content = $('#content');
    var lastInternalCount = 0;
    var editor;

    /**
     * Get the words count from TinyMCE and update the status of the requirement
     */
    function update() {
      var text, count;

      if (typeof ppChecklists.requirements.internal_links === 'undefined') {
        return;
      }

      if (typeof editor == 'undefined' || !editor || editor.isHidden()) {
        // For the text tab.
        text = $content.val();
      } else {
        // For the editor tab.
        text = editor.getContent({ format: 'raw' });
      }

      count = PP_Checklists.extract_internal_links(text).length;

      console.log('[PublishPress Checklists] Internal Links Count:', count);

      if (lastInternalCount === count) {
        return;
      }

      var min = parseInt(ppChecklists.requirements.internal_links.value[0]),
        max = parseInt(ppChecklists.requirements.internal_links.value[1]);

      var validQuantity = PP_Checklists.check_valid_quantity(count, min, max);
      console.log('[PublishPress Checklists] Valid Quantity Check:', { count, min, max, result: validQuantity });

      $('#pp-checklists-req-internal_links').trigger(
        PP_Checklists.EVENT_UPDATE_REQUIREMENT_STATE,
        validQuantity
      );

      lastInternalCount = count;
    }

    // For the editor.
    $(document).on(PP_Checklists.EVENT_TINYMCE_LOADED, function (event, tinymce) {
      editor = tinymce.editors['content'];

      if (typeof editor !== 'undefined') {
        editor.onInit.add(function () {
          /**
           * Bind the words count update triggers.
           *
           * When a node change in the main TinyMCE editor has been triggered.
           * When a key has been released in the plain text content editor.
           */

          if (editor.id !== 'content') {
            return;
          }

          editor.on('nodechange keyup', _.debounce(update, 500));
        });
      }
    });

    $content.on('input keyup', _.debounce(update, 500));
    update();
  }

  /*----------  External Links ----------*/
  var lastExternalCount = 0;
  if (PP_Checklists.is_gutenberg_active()) {
    /**
     * For Gutenberg
     */
    if ($('#pp-checklists-req-external_links').length > 0) {
      wp.data.subscribe(function () {
        setTimeout(function() {
          var content = PP_Checklists.getEditor().getEditedPostAttribute('content');

          if (typeof content == 'undefined') {
            return;
          }

          var count = PP_Checklists.extract_external_links(content).length;

          if (lastExternalCount == count) {
            return;
          }

          var min = parseInt(ppChecklists.requirements.external_links.value[0]),
            max = parseInt(ppChecklists.requirements.external_links.value[1]);

          $('#pp-checklists-req-external_links').trigger(
            PP_Checklists.EVENT_UPDATE_REQUIREMENT_STATE,
            PP_Checklists.check_valid_quantity(count, min, max),
          );

          lastExternalCount = count;
        }, 150);
      });
    }
  } else {
    /**
     * For the Classic Editor
     */
    var $content = $('#content');
    var lastExternalCount = 0;
    var editor;

    /**
     * Get the words count from TinyMCE and update the status of the requirement
     */
    function update() {
      var text, count;

      if (typeof ppChecklists.requirements.external_links === 'undefined') {
        return;
      }

      if (typeof editor == 'undefined' || !editor || editor.isHidden()) {
        // For the text tab.
        text = $content.val();
      } else {
        // For the editor tab.
        text = editor.getContent({ format: 'raw' });
      }

      count = PP_Checklists.extract_external_links(text).length;

      if (lastExternalCount === count) {
        return;
      }

      var min = parseInt(ppChecklists.requirements.external_links.value[0]),
        max = parseInt(ppChecklists.requirements.external_links.value[1]);

      $('#pp-checklists-req-external_links').trigger(
        PP_Checklists.EVENT_UPDATE_REQUIREMENT_STATE,
        PP_Checklists.check_valid_quantity(count, min, max),
      );

      lastExternalCount = count;
    }

    // For the editor.
    $(document).on(PP_Checklists.EVENT_TINYMCE_LOADED, function (event, tinymce) {
      editor = tinymce.editors['content'];

      if (typeof editor !== 'undefined') {
        editor.onInit.add(function () {
          /**
           * Bind the words count update triggers.
           *
           * When a node change in the main TinyMCE editor has been triggered.
           * When a key has been released in the plain text content editor.
           */

          if (editor.id !== 'content') {
            return;
          }

          editor.on('nodechange keyup', _.debounce(update, 500));
        });
      }
    });

    $content.on('input keyup', _.debounce(update, 500));
    update();
  }

  /*----------  Image alt ----------*/
  if (PP_Checklists.is_gutenberg_active()) {
    /**
     * For Gutenberg
     */
    if ($('#pp-checklists-req-image_alt').length > 0) {
      wp.data.subscribe(function () {
        var content = PP_Checklists.getEditor().getEditedPostAttribute('content');

        if (typeof content == 'undefined') {
          return;
        }

        // Get missing alt images
        var missingAltImages = PP_Checklists.missing_alt_images(content);
        var no_missing_alt = missingAltImages.length === 0;


        // Update block warnings if we're in the block editor
        if (wp.data.select('core/block-editor')) {
          const blocks = wp.data.select('core/block-editor').getBlocks();
          const imageBlocks = blocks.filter(block => block.name === 'core/image');

          imageBlocks.forEach(block => {
            // Check if this block's HTML matches any of the missing alt images
            const hasWarning = missingAltImages.some(html =>
              html.includes(block.attributes.id) || html.includes(block.attributes.url)
            );

            // Set warning attribute on the list view item
            const listViewElement = document.querySelector(
              `.block-editor-list-view-leaf[data-block="${block.clientId}"]`
            );
            if (listViewElement) {
              listViewElement.setAttribute('data-warning', hasWarning);
            }
          });
        }

        $('#pp-checklists-req-image_alt').trigger(PP_Checklists.EVENT_UPDATE_REQUIREMENT_STATE, no_missing_alt);
      });
    }
  } else {
    /**
     * For the Classic Editor
     */
    var $content = $('#content');
    var editor;

    /**
     * Get the words count from TinyMCE and update the status of the requirement
     */
    function update() {
      var text,
        count,
        no_missing_alt = false;
      if (typeof ppChecklists.requirements.image_alt === 'undefined') {
        return;
      }

      if (typeof editor == 'undefined' || !editor || editor.isHidden()) {
        // For the text tab.
        text = $content.val();
      } else {
        // For the editor tab.
        text = editor.getContent({ format: 'raw' });
      }

      var count = PP_Checklists.missing_alt_images(text).length;

      if (count == 0) {
        no_missing_alt = true;
      }

      $('#pp-checklists-req-image_alt').trigger(PP_Checklists.EVENT_UPDATE_REQUIREMENT_STATE, no_missing_alt);
    }

    // For the editor.
    $(document).on(PP_Checklists.EVENT_TINYMCE_LOADED, function (event, tinymce) {
      editor = tinymce.editors['content'];

      if (typeof editor !== 'undefined') {
        editor.onInit.add(function () {
          /**
           * Bind the words count update triggers.
           *
           * When a node change in the main TinyMCE editor has been triggered.
           * When a key has been released in the plain text content editor.
           */

          if (editor.id !== 'content') {
            return;
          }

          editor.on('nodechange keyup', _.debounce(update, 500));
        });
      }
    });

    $content.on('input keyup change', _.debounce(update, 500));
    update();
  }

  /*----------  Validate Links ----------*/
  if (PP_Checklists.is_gutenberg_active()) {
    /**
     * For Gutenberg
     */
    if ($('#pp-checklists-req-validate_links').length > 0) {
      wp.data.subscribe(function () {
        var content = PP_Checklists.getEditor().getEditedPostAttribute('content');

        if (typeof content == 'undefined') {
          return;
        }

        // Get invalid links
        var invalidLinks = PP_Checklists.validate_links_format(content);
        var no_invalid_link = invalidLinks.length === 0;

        // Update block warnings if we're in the block editor
        if (wp.data.select('core/block-editor')) {
          const blocks = wp.data.select('core/block-editor').getBlocks();

          // Check all blocks that might contain links
          blocks.forEach(block => {
            // Get block content/HTML
            const blockContent = block.attributes.content || '';

            // Check if this block contains any invalid links
            const hasWarning = invalidLinks.some(invalidLink =>
              blockContent.includes(invalidLink)
            );

            // Set warning attribute on the list view item
            const listViewElement = document.querySelector(
              `.block-editor-list-view-leaf[data-block="${block.clientId}"]`
            );
            if (listViewElement) {
              listViewElement.setAttribute('data-warning', hasWarning);
            }
          });
        }

        $('#pp-checklists-req-validate_links').trigger(PP_Checklists.EVENT_UPDATE_REQUIREMENT_STATE, no_invalid_link);
      });
    }
  } else {
    /**
     * For the Classic Editor
     */
    var $content = $('#content');
    var editor;

    /**
     * Get the words count from TinyMCE and update the status of the requirement
     */
    function update() {
      var text,
        count,
        no_invalid_link = false;
      if (typeof ppChecklists.requirements.validate_links === 'undefined') {
        return;
      }

      if (typeof editor == 'undefined' || !editor || editor.isHidden()) {
        // For the text tab.
        text = $content.val();
      } else {
        // For the editor tab.
        text = editor.getContent({ format: 'raw' });
      }

      var count = PP_Checklists.validate_links_format(text).length;

      if (count == 0) {
        no_invalid_link = true;
      }

      $('#pp-checklists-req-validate_links').trigger(PP_Checklists.EVENT_UPDATE_REQUIREMENT_STATE, no_invalid_link);
    }

    // For the editor.
    $(document).on(PP_Checklists.EVENT_TINYMCE_LOADED, function (event, tinymce) {
      editor = tinymce.editors['content'];

      if (typeof editor !== 'undefined') {
        editor.onInit.add(function () {
          /**
           * Bind the words count update triggers.
           *
           * When a node change in the main TinyMCE editor has been triggered.
           * When a key has been released in the plain text content editor.
           */

          if (editor.id !== 'content') {
            return;
          }

          editor.on('nodechange keyup', _.debounce(update, 500));
        });
      }
    });

    $content.on('input keyup change', _.debounce(update, 500));
    update();
  }

  /*----------  Image Alt Count ----------*/
  if (PP_Checklists.is_gutenberg_active()) {
    /**
     * For Gutenberg
     */
    if ($('#pp-checklists-req-image_alt_count').length > 0) {
      wp.data.subscribe(function () {
        var content = PP_Checklists.getEditor().getEditedPostAttribute('content');

        if (typeof content == 'undefined') {
          return;
        }

        var altLengths = PP_Checklists.get_image_alt_lengths(content);
        var min = parseInt(ppChecklists.requirements.image_alt_count.value[0]);
        var max = parseInt(ppChecklists.requirements.image_alt_count.value[1]);

        // Check if we have access to block editor
        if (wp.data.select('core/block-editor')) {
          const blocks = wp.data.select('core/block-editor').getBlocks();
          const imageBlocks = blocks.filter(block => block.name === 'core/image');

          imageBlocks.forEach(block => {
            // Check if this block's alt text length is within limits
            const altLength = block.attributes.alt ? block.attributes.alt.length : 0;
            const hasWarning = !PP_Checklists.check_valid_quantity(altLength, min, max);

            // Set warning attribute on the list view item
            const listViewElement = document.querySelector(
              `.block-editor-list-view-leaf[data-block="${block.clientId}"]`
            );
            if (listViewElement) {
              listViewElement.setAttribute('data-warning', hasWarning);
            }
          });
        }

        var isValid = altLengths.every(function (length) {
          return PP_Checklists.check_valid_quantity(length, min, max);
        });

        $('#pp-checklists-req-image_alt_count').trigger(PP_Checklists.EVENT_UPDATE_REQUIREMENT_STATE, isValid);
      });
    }
  } else {
    /**
     * For the Classic Editor
     */
    var $content = $('#content');
    var editor;

    function update() {
      var text;
      if (typeof ppChecklists.requirements.image_alt_count === 'undefined') {
        return;
      }

      if (typeof editor == 'undefined' || !editor || editor.isHidden()) {
        // For the text tab.
        text = $content.val();
      } else {
        // For the editor tab.
        text = editor.getContent({ format: 'raw' });
      }

      var altLengths = PP_Checklists.get_image_alt_lengths(text);
      var min = parseInt(ppChecklists.requirements.image_alt_count.value[0]);
      var max = parseInt(ppChecklists.requirements.image_alt_count.value[1]);

      var isValid = altLengths.every(function (length) {
        return PP_Checklists.check_valid_quantity(length, min, max);
      });

      $('#pp-checklists-req-image_alt_count').trigger(PP_Checklists.EVENT_UPDATE_REQUIREMENT_STATE, isValid);
    }

    // For the editor.
    $(document).on(PP_Checklists.EVENT_TINYMCE_LOADED, function (event, tinymce) {
      editor = tinymce.editors['content'];

      if (typeof editor !== 'undefined') {
        editor.onInit.add(function () {
          if (editor.id !== 'content') {
            return;
          }

          editor.on('nodechange keyup', _.debounce(update, 500));
        });
      }
    });

    $content.on('input keyup change', _.debounce(update, 500));
    update();
  }



  /**
   *
   * @type {Object}
   * @deprecated
   */
  window.PP_Content_Checklist = PP_Checklists;
})(jQuery, window, document, new wp.utils.WordCounter());
