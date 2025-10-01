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

(function ($, objectL10n_checklists_global_checklist) {
  'use strict';

  $(function () {
    // Initialize tab persistence
    initializeTabPersistence();
    
    // Initialize count indicators
    update_count_indicators();
    
    // Auto-hide success notice after 5 seconds
    if ($('.checklists-save-notice').length > 0) {
      setTimeout(function() {
        $('.checklists-save-notice').fadeOut(500);
      }, 2500);
    }

    $('#pp-checklists-requirements tbody').sortable({ items: ' > tr' });
    
    // Update count indicators when requirement rule dropdowns change
    $(document).on('change', 'select[name*="_rule"]', function() {
      update_count_indicators();
    });

    // Set the event for the post type filter
    $('#pp-checklists-post-type-filter a').on('click', function (event) {
      event.preventDefault();

      // Hide all requirements except the first one (title)
      $('.pp-checklists-requirement-row:not(.ppch-title-group)').hide();

      var $target = $(event.toElement || event.target),
        post_type = $target.attr('href').substring(1);

      // Save the selected post type
      if (typeof saveToStorage === 'function') {
        saveToStorage('ppch_active_post_type', post_type);
      }

      show_post_type_requirements(post_type);
    });

    // Set the mask for settings fields
    $('.pp-checklists-number').on('keypress', function (event) {
      var key = event.keyCode || event.which;
      var allowed_keys = [
        35,
        36,
        37,
        38,
        39,
        40, // arrows
        8,
        9,
        46,
        27,
        13, // backspace, tab, delete, esc, enter
        48,
        49,
        50,
        51,
        52,
        53,
        54,
        55,
        56,
        57, // 0-9
      ];

      // Ignore any key different than number
      if (allowed_keys.indexOf(key) < 0) {
        event.preventDefault();

        return false;
      }

      return true;
    });

    $('.pp-checklists-float').on('keypress', function (event) {
      var key = event.keyCode || event.which;
      var allowed_keys = [
        35,
        36,
        37,
        38,
        39,
        40, // arrows
        44,
        46, // decimal separators
        8,
        9,
        46,
        27,
        13, // backspace, tab, delete, esc, enter
        48,
        49,
        50,
        51,
        52,
        53,
        54,
        55,
        56,
        57, // 0-9
      ];

      // Ignore any key different than number
      if (allowed_keys.indexOf(key) < 0) {
        event.preventDefault();

        return false;
      }

      return true;
    });

    /**
     * Show the requirements for the specific post type and hide all the
     * others.
     *
     * @param  {string} post_type
     */
    function show_post_type_requirements(post_type) {
      // Mark the filter as selected
      $('#pp-checklists-post-type-filter li.nav-tab-active').removeClass('nav-tab-active');
      $('#pp-checklists-post-type-filter li.post-type-' + post_type).addClass('nav-tab-active');

      $('.pp-checklists-tabs-list li a').removeClass('active');

      //remove active class from all tabs
      $('.pp-checklists-tabs a').removeClass('active');

      $('.pp-checklists-tabs ul').removeClass('active');
      $('.pp-checklists-tabs ul#list-' + post_type).addClass('active');

      //add active class to title tab
      $('.pp-checklists-tabs li:first-child a').addClass('active');

      $('.pp-checklists-tab-body').hide();
      $('#pp-checklists-tab-body-' + post_type).show();

      const current_data_tab =
        $('#list-' + post_type)
          .find('li a.active')
          .attr('data-tab') || 'title';
      // Hide the requirements which are not for the current post type
      $('#pp-checklists-requirements tr.pp-checklists-requirement-row').hide();
      // Display the correct requirements
      $(
        '#pp-checklists-requirements tr.ppch-' + current_data_tab + '-group[data-post-type="' + post_type + '"]',
      ).show();
    }

    /**
     * Returns the current post type, selected by the filter.
     *
     * @return string
     */
    function get_current_post_type() {
      var post_type = $('#pp-checklists-post-type-filter li.nav-tab-active a').attr('href').substring(1);

      if (post_type === '' || post_type === false || post_type === null || typeof post_type === undefined) {
        post_type = objectL10n_checklists_global_checklist.first_post_type;
      }

      return post_type;
    }

    /**
     * Update count indicators for all tabs based on current requirement settings
     */
    function update_count_indicators() {
      $('.pp-checklists-tabs-list').each(function() {
        var $tabsList = $(this);
        var postType = $tabsList.attr('id').replace('list-', '');
        
        $tabsList.find('li a').each(function() {
          var $tabLink = $(this);
          var tabGroup = $tabLink.attr('data-tab');
          var count = 0;
          
          // Count enabled requirements for this tab group and post type
          $('#pp-checklists-requirements tr.ppch-' + tabGroup + '-group[data-post-type="' + postType + '"]').each(function() {
            var $row = $(this);
            var $select = $row.find('select[name*="_rule"]');
            if ($select.length && $select.val() !== 'off') {
              count++;
            }
          });
          
          // Update or remove indicator
          var $indicator = $tabLink.find('.pp-checklists-count-indicator');
          if (count > 0) {
            if ($indicator.length) {
              $indicator.text(count);
            } else {
              $tabLink.find('.item').after('<span class="pp-checklists-count-indicator">' + count + '</span>');
            }
          } else {
            $indicator.remove();
          }
        });
      });
    }

    /**
     * Method to remove custom item from the requirements list, identified
     * by the temporary ID/
     *
     * @param  {string} id
     * @param  {string} type
     */
    function remove_row(id, type) {
      // Add a special hidden input to flag the delete action
      var $input = $('<input type="hidden" />')
        .attr('name', 'publishpress_checklists_checklists_options[' + type + '_items_remove][]')
        .val(id)
        .appendTo($('#pp-checklists-requirements'));

      $('tr[data-id="' + id + '"]').remove();
          }

    /**
     * Callback for events where we want to trigger
     * a remove row action
     *
     * @param  {Event} event
     */
    function callback_remove_row(event) {
      var $target = $(event.target);

      remove_row($target.data('id'), $target.data('type'));
    }

    /**
     * Create a row inside the requirements table
     *
     * @param  {string} title
     * @param  {string} action
     *
     * @return {Element}
     */
    function create_row(id, title, action, post_type, type) {
      var $table = $('#pp-checklists-requirements'),
        $tr = $('<tr>'),
        $td = null,
        $titleField = type == 'openai' ? $('<textarea>') : $('<input type="text" />'),
        $idField = $('<input type="hidden" />'),
        $actionField = $('<select>'),
        $canIgnoreField = $('<select>'),
        $optionsField = $('<select>'),
        $option,
        $a,
        $icon,
        $suggestionItem = $('<div class="pp-custom-suggestion">'),
        $suggestionsObject = objectL10n_checklists_global_checklist[type + '_suggestions'],
        rule;

      $table.find('tbody#pp-checklists-tab-body-' + post_type).append($tr);
      $table.find('tr.ppch-custom-group #empty-custom-rule').hide();

      $tr
        .addClass('pp-checklists-requirement-row')
        .attr('data-id', id)
        .attr('data-type', type)
        .attr('data-post-type', post_type);

      $td = $('<td>').appendTo($tr);

      // ID field
      $idField
        .attr('name', 'publishpress_checklists_checklists_options[' + type + '_items][]')
        .val(id)
        .appendTo($td);

      // Title cell
      $titleField
        .attr('name', 'publishpress_checklists_checklists_options[' + id + '_title][' + post_type + ']')
        .val(title)
        .addClass('pp-checklists-custom-item-title')
        .focus()
        .attr('data-id', id)
        .attr('placeholder', objectL10n_checklists_global_checklist[type + '_enter_name'])
        .appendTo($td);

      // Suggestion
      if (typeof $suggestionsObject !== 'undefined') {
        $suggestionItem.append(
          '<span class="suggestion-title">' + objectL10n_checklists_global_checklist.suggestion_title + ':</span> ',
        );
        for (var key in $suggestionsObject) {
          if ($suggestionsObject.hasOwnProperty(key)) {
            $suggestionItem.append(
              '<span>&#x2022; <a href="javascript:void(0);" class="' +
                key +
                '" data-prompt="' +
                $suggestionsObject[key].prompt +
                '">' +
                $suggestionsObject[key].label +
                '</a></span> ',
            );
          }
        }
        $suggestionItem.appendTo($td);
      }

      // Action cell
      $td = $('<td>').appendTo($tr);
      $actionField
        .attr('name', 'publishpress_checklists_checklists_options[' + id + '_rule][' + post_type + ']')
        .attr('data-id', id)
        .appendTo($td);

      $.each(objectL10n_checklists_global_checklist.rules, function (value, label) {
        $option = $('<option>').attr('value', value).text(label).appendTo($actionField);
      });

      // can_ignore cell
      $td = $('<td>').appendTo($tr);
      $canIgnoreField
        .attr('class', 'pp-checklists-can-ignore')
        .attr('name', 'publishpress_checklists_checklists_options[' + id + '_can_ignore][' + post_type + '][]')
        .attr('multiple', 'multiple')
        .appendTo($td);

      $option = $('<option value=""></option>').appendTo($canIgnoreField);
      $.each(objectL10n_checklists_global_checklist.roles, function (value, label) {
        $option = $('<option>').attr('value', value).text(label).appendTo($canIgnoreField);
      });

      // Options cell
      $td = $('<td>').addClass('pp-checklists-task-params').appendTo($tr);

      if (type !== 'openai') {
        $optionsField
          .attr('id', '' + post_type + '-checklists-' + id + '_editable_by')
          .attr('name', 'publishpress_checklists_checklists_options[' + id + '_editable_by][' + post_type + '][]')
          .attr('multiple', 'multiple')
          .appendTo($td);

        $option = $('<option value=""></option>').appendTo($optionsField);
        $.each(objectL10n_checklists_global_checklist.roles, function (value, label) {
          $option = $('<option>').attr('value', value).text(label).appendTo($optionsField);
        });

        var $label = $('<p>')
          .addClass('pp-checklists-editable-by-description')
          .text(objectL10n_checklists_global_checklist.editable_by);
        $optionsField.after($label);
      }

      $a = $('<a>')
        .attr('href', 'javascript:void(0);')
        .addClass('pp-checklists-remove-custom-item')
        .attr('title', objectL10n_checklists_global_checklist.remove)
        .attr('data-id', id)
        .attr('data-type', type)
        .appendTo($td);
      $icon = $('<span>').addClass('dashicons dashicons-no').attr('data-id', id).attr('data-type', type).appendTo($a);

      // Re-initialize select 2
      $('#pp-checklists-global select').select2({
        language: {
          noResults: function () {
            return objectL10n_checklists_global_checklist.noResults;
          },
          searching: function () {
            return objectL10n_checklists_global_checklist.searching;
          }
        },
      });

      $a.on('click', function(event) {
        callback_remove_row(event);
        // Update count indicators after removing item
        setTimeout(update_count_indicators, 100);
      });
      
      // Update count indicators after adding new item
      setTimeout(update_count_indicators, 100);
    }

    /*----------  Custom items  ----------*/
    $('#pp-checklists-add-button').on('click', function (event) {
      $('.ppch-custom-group').show();
      
      // Switch to Custom tab before adding the item
      var current_post_type = get_current_post_type();
      var $customTabLink = $('.pp-checklists-tabs ul#list-' + current_post_type + ' a[data-tab="custom"]');
      if ($customTabLink.length) {
        $customTabLink.click();
      }

      var newId = uidGen(15);

      create_row(newId, '', '', current_post_type, 'custom');
    });

    // Hide all requirements except the first one (title)
    $('.pp-checklists-requirement-row:not(.ppch-title-group)').hide();

    /**
     * Requirements tab switch
     */
    $(document).on('click', '.pp-checklists-tabs a', function (event) {
      event.preventDefault();

      var clicked_tab = $(this).attr('data-tab');
      var current_post_type = get_current_post_type();

      //remove active class from all tabs
      $('.pp-checklists-tabs a').removeClass('active');

      //add active class to current tab
      $(this).addClass('active');

      // Save the selected inner tab for the current post type
      if (typeof saveToStorage === 'function') {
        saveToStorage('ppch_active_inner_tab_' + current_post_type, clicked_tab);
      }

      // hide all tabs contents
      $('.pp-checklists-requirement-row').hide();

      // Show the current tab contents that also have the matching data-post-type attribute
      $('.ppch-' + clicked_tab + '-group[data-post-type="' + current_post_type + '"]').show();
    });

    /*----------  OpenAI items  ----------*/
    $('#pp-checklists-openai-promt-button').on('click', function (event) {
      $('.ppch-custom-group').show();
      
      // Switch to Custom tab before adding the item
      var current_post_type = get_current_post_type();
      var $customTabLink = $('.pp-checklists-tabs ul#list-' + current_post_type + ' a[data-tab="custom"]');
      if ($customTabLink.length) {
        $customTabLink.click();
      }

      var newId = uidGen(15);

      create_row(newId, '', '', current_post_type, 'openai');
    });
    $(document).on('click', '.pp-custom-suggestion a', function (event) {
      event.preventDefault();
      $(this).closest('td').find('.pp-checklists-custom-item-title').val($(this).data('prompt'));
    });

    $('.pp-checklists-remove-custom-item').on('click', callback_remove_row);

    /*----------  Form validation  ----------*/
    $('#pp-checklists-global').submit(function () {
      var submit_form = true,
        submit_error = '',
        required_rules = objectL10n_checklists_global_checklist.required_rules,
        required_rules_notice = objectL10n_checklists_global_checklist.submit_error,
        custom_task_error_displayed = false;

      //remove previous notice and inline validation errors
      $('.checklists-save-notice').remove();
      $('.field-validation-error').remove();
      $('.has-validation-error').removeClass('has-validation-error');

      //select all row
      $('.pp-checklists-requirement-row').each(function () {
        var requirement_id = $(this).attr('data-id');
        var row_requirement_title = $(this).find('td:first-child').text();
        var requirement_rule = $(this)
          .find('#post-checklists-' + requirement_id + '_rule option:selected')
          .val();
        var min_field = $(this).find('#post-checklists-' + requirement_id + '_min');
        var max_field = $(this).find('#post-checklists-' + requirement_id + '_max');
        var time_field = $(this).find('input[type="time"]');

        //check if selected rule require validation and option is Base_counter
        if ($.inArray(requirement_rule, required_rules) !== -1 && (min_field.length > 0 || max_field.length > 0)) {
          //void submit and add to error if none of min and max field is set
          if (min_field.val().trim() === '' && max_field.val().trim() === '') {
            submit_form = false;
            var field_title = $('<strong>').text(`${row_requirement_title}`);
            submit_error += $('<div class="checklists-save-notice"></div>')
              .append(
                $('<div class="alert alert-danger alert-dismissible"></div>')
                  .append('<a href="javascript:void(0);" class="close">×</a>')
                  .append(field_title)
                  .append(document.createTextNode(required_rules_notice)),
              )
              .html();
            
            // Add inline field validation notice
            var $row = $(this);
            $row.find('.field-validation-error').remove();
            
            $row.addClass('has-validation-error');
          }
        }

        // validation for exact time requirement: block if empty time on required rule
        if ($.inArray(requirement_rule, required_rules) !== -1 && time_field.length > 0) {
          if (!time_field.val()) {
            submit_form = false;
            var field_title = $('<strong>').text(`${row_requirement_title}`);
            submit_error += $('<div class="checklists-save-notice"></div>')
              .append(
                $('<div class="alert alert-danger alert-dismissible"></div>')
                  .append('<a href="javascript:void(0);" class="close">×</a>')
                  .append(field_title)
                  .append(document.createTextNode(required_rules_notice)),
              )
              .html();
            
            // Add inline field validation notice
            var $row = $(this);
            $row.find('.field-validation-error').remove();
            
            $row.addClass('has-validation-error');
          }
        }
      });

      // Only check visible custom task title fields that are part of existing rows
      $('.pp-checklists-custom-item-title').filter(':visible').each(function () {
        var $this = $(this);
        var $row = $this.closest('tr');
        // Only validate if the row is visible and not marked for removal
        if ($row.is(':visible') && !$row.hasClass('removed') && $this.val().trim() === '' && !custom_task_error_displayed) {
          submit_form = false;
          submit_error += $('<div class="checklists-save-notice"></div>')
            .append(
              $('<div class="alert alert-danger alert-dismissible"></div>')
                .append('<a href="javascript:void(0);" class="close">×</a>')
                .append(document.createTextNode(objectL10n_checklists_global_checklist.custom_item_error)),
            )
            .html();
          custom_task_error_displayed = true;
        }
      });

      if (!submit_form) {
        var submit_error_el = $('<div class="checklists-save-notice"></div>').append(submit_error);
        
        // Add notice at the top of the form for better visibility
        $('#pp-checklists-global').prepend(submit_error_el.clone());
        
        
        
        // Scroll to top to show the notice
        $('html, body').animate({
          scrollTop: $('#pp-checklists-global').offset().top - 50
        }, 500);
      }
      
      // Handle empty multiselects - ensure they submit an empty value
      // This fixes the issue where deselecting all options doesn't clear the saved values
      $('select[multiple]').each(function() {
        if ($(this).val() === null || $(this).val().length === 0) {
          // Create a hidden input with the same name and empty value
          var hiddenInput = $('<input type="hidden">');
          hiddenInput.attr('name', $(this).attr('name'));
          hiddenInput.val('');
          $(this).after(hiddenInput);
        }
      });

      return submit_form;
    });

    // Remove current notice on dismiss
    $(document).on('click', '#pp-checklists-global .checklists-save-notice .close', function (event) {
      event.preventDefault();
      //remove all notices (both top and bottom)
      $('#pp-checklists-global .checklists-save-notice').remove();
    });

    // Remove notice on any number input changed
    $(document).on('change input paste', '.pp-checklists-number', function () {
      //remove previous notice
      $('.checklists-save-notice').remove();
      // Remove inline validation for this row
      $(this).closest('tr').removeClass('has-validation-error').find('.field-validation-error').remove();
    });
    
    // Remove inline validation when dropdown values change
    $(document).on('change', 'select[name*="_rule"]', function () {
      $(this).closest('tr').removeClass('has-validation-error').find('.field-validation-error').remove();
    });
    
    // Remove inline validation when time fields change
    $(document).on('change', 'input[type="time"]', function () {
      $(this).closest('tr').removeClass('has-validation-error').find('.field-validation-error').remove();
    });
    
    // Remove inline validation when custom task titles change
    $(document).on('input', '.pp-checklists-custom-item-title', function () {
      $(this).closest('tr').removeClass('has-validation-error').find('.field-validation-error').remove();
      // Also remove the main validation notice if all custom tasks now have titles
      var hasEmptyCustomTasks = false;
      $('.pp-checklists-custom-item-title').filter(':visible').each(function() {
        var $this = $(this);
        var $row = $this.closest('tr');
        if ($row.is(':visible') && !$row.hasClass('removed') && $this.val().trim() === '') {
          hasEmptyCustomTasks = true;
          return false; // break the loop
        }
      });
      if (!hasEmptyCustomTasks) {
        $('.checklists-save-notice').remove();
      }
    });
    
    // Remove validation notices when custom tasks are removed
    $(document).on('click', '.pp-checklists-remove-button', function() {
      setTimeout(function() {
        // Check if there are any remaining empty custom tasks after removal
        var hasEmptyCustomTasks = false;
        $('.pp-checklists-custom-item-title').filter(':visible').each(function() {
          var $this = $(this);
          var $row = $this.closest('tr');
          if ($row.is(':visible') && !$row.hasClass('removed') && $this.val().trim() === '') {
            hasEmptyCustomTasks = true;
            return false; // break the loop
          }
        });
        if (!hasEmptyCustomTasks) {
          $('.checklists-save-notice').remove();
        }
      }, 150); // Wait for the removal to complete
    });

    /**
     * Initialize tab persistence functionality
     */
    function initializeTabPersistence() {
      // Get URL parameters
      var urlParams = new URLSearchParams(window.location.search);
      var urlPostType = urlParams.get('post_type');
      var urlInnerTab = urlParams.get('inner_tab');
      
      // Determine active post type
      var activePostType = urlPostType || getFromStorage('ppch_active_post_type') || objectL10n_checklists_global_checklist.first_post_type;
      
      // Show the post type requirements
      show_post_type_requirements(activePostType);
      
      // Determine active inner tab for this post type
      var activeInnerTab = urlInnerTab || getFromStorage('ppch_active_inner_tab_' + activePostType) || 'title';
      
      // Set the active inner tab
      setTimeout(function() {
        var $innerTabLink = $('.pp-checklists-tabs ul#list-' + activePostType + ' a[data-tab="' + activeInnerTab + '"]');
        if ($innerTabLink.length) {
          $innerTabLink.click();
        }
      }, 100);
      
      // Add form submission handler to preserve tab state
      $('#pp-checklists-global').on('submit', function() {
        var currentPostType = get_current_post_type();
        var currentInnerTab = $('.pp-checklists-tabs ul#list-' + currentPostType + ' a.active').attr('data-tab') || 'title';
        
        // Save current state
        saveToStorage('ppch_active_post_type', currentPostType);
        saveToStorage('ppch_active_inner_tab_' + currentPostType, currentInnerTab);
        
        // Add hidden fields to preserve tab state in form submission
        var $form = $(this);
        $form.find('input[name="ppch_active_post_type"]').remove();
        $form.find('input[name="ppch_active_inner_tab"]').remove();
        
        $form.append('<input type="hidden" name="ppch_active_post_type" value="' + currentPostType + '">');
        $form.append('<input type="hidden" name="ppch_active_inner_tab" value="' + currentInnerTab + '">');
      });
    }

    /**
     * Check if browser supports localStorage
     */
    function browserSupportStorage() {
      try {
        return 'localStorage' in window && window['localStorage'] !== null;
      } catch (e) {
        return false;
      }
    }

    /**
     * Save data to localStorage
     */
    function saveToStorage(key, value) {
      if (browserSupportStorage()) {
        try {
          localStorage.setItem(key, value);
        } catch (e) {
          // Storage quota exceeded or other error
        }
      }
    }

    /**
     * Get data from localStorage
     */
    function getFromStorage(key) {
      if (browserSupportStorage()) {
        try {
          return localStorage.getItem(key);
        } catch (e) {
          return null;
        }
      }
      return null;
    }
  });

  function uidGen(len) {
    var text = ' ',
      charset = 'abcdefghijklmnopqrstuvwxyz';

    for (var i = 0; i < len; i++) {
      text += charset.charAt(Math.floor(Math.random() * charset.length));
    }

    return text.trim();
  }



})(jQuery, objectL10n_checklists_global_checklist);
