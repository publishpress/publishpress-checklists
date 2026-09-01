jQuery(document).ready(function ($) {
  // Show the description for the selected checklist task sort order.
  $('.ppch-checklist-items-sort-order').each(function () {
    var $select = $(this);
    var $descriptions = $('#' + $select.attr('id') + '_description')
      .find('.ppch-sort-order-description');

    function updateSortOrderDescription() {
      var selectedSortOrder = $select.val();

      $descriptions.prop('hidden', true).filter(function () {
        return $(this).data('sort-order') === selectedSortOrder;
      }).prop('hidden', false);
    }

    $select.on('change', updateSortOrderDescription);
    updateSortOrderDescription();
  });
});

jQuery(document).ready(function ($) {
  // Initialize color pickers
  $('.pp-checklists-color-picker').wpColorPicker();

  // Tabs

  var $tabsWrapper = $('#publishpress-checklists-settings-tabs');
  var $tabs = $tabsWrapper.find('a[role="tab"]');

  function activateTab($tab, moveFocus) {
    if (!$tab.length) {
      return;
    }

    var panel = $tab.attr('href');

    $tabs.attr('aria-selected', 'false').attr('tabindex', '-1');
    $tab.attr('aria-selected', 'true').attr('tabindex', '0');
    $tabsWrapper.children('li').removeClass('nav-tab-active');
    $tab.closest('li').addClass('nav-tab-active');

    if (browserSupportStorage()) {
      saveStorageData('ppch_settings_active_tab', panel.slice(1));
    }

    $('table[id^="ppch-"]').hide().attr('aria-hidden', 'true');
    $(panel).show().attr('aria-hidden', 'false');

    if (moveFocus) {
      $tab.focus();
    }
  }

  $tabs.on('click', function (e) {
    e.preventDefault();
    activateTab($(this), false);
  });

  $tabs.on('keydown', function (e) {
    var currentIndex = $tabs.index(this);
    var targetIndex = currentIndex;

    if (e.key === 'Enter' || e.key === ' ' || e.keyCode === 13 || e.keyCode === 32) {
      e.preventDefault();
      activateTab($(this), false);
      return;
    }

    if (e.key === 'ArrowRight' || e.key === 'ArrowDown' || e.keyCode === 39 || e.keyCode === 40) {
      targetIndex = (currentIndex + 1) % $tabs.length;
    } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp' || e.keyCode === 37 || e.keyCode === 38) {
      targetIndex = (currentIndex - 1 + $tabs.length) % $tabs.length;
    } else if (e.key === 'Home' || e.keyCode === 36) {
      targetIndex = 0;
    } else if (e.key === 'End' || e.keyCode === 35) {
      targetIndex = $tabs.length - 1;
    } else {
      return;
    }

    e.preventDefault();
    activateTab($tabs.eq(targetIndex), true);
  });

  var firstTabHref = $tabs.first().attr('href') || '';
  var ppchTab = String(firstTabHref.slice(1));

  if (typeof ppchSettings != 'undefined' && typeof ppchSettings.tab != 'undefined') {
    ppchTab = ppchSettings.tab;
    $tabs.filter('[href="#' + ppchTab + '"]').click();
  } else if (browserSupportStorage() && getStorageData('ppch_settings_active_tab')) {
    ppchTab = getStorageData('ppch_settings_active_tab');
    $tabs.filter('[href="#' + ppchTab + '"]').click();
  }

  var $hiddenFields = $('input[id^="ppch-tab-"]');

  $hiddenFields.each(function () {
    var $this = $(this);
    var $wrapper = $this.next('table');
    $wrapper.attr('id', $this.attr('id'));
    $this.remove();

    var $tab = $tabs.filter('[href="#' + $wrapper.attr('id') + '"]');
    $wrapper.attr('aria-hidden', $wrapper.attr('id') !== ppchTab ? 'true' : 'false');
    if ($tab.length) {
      $wrapper.attr('aria-labelledby', $tab.attr('id'));
    }

    if ($wrapper.attr('id') !== ppchTab) {
      $wrapper.hide();
    }
  });

  /**
   * Check if browser support local storage
   * @returns
   */
  function browserSupportStorage() {
    if (typeof Storage !== 'undefined') {
      return true;
    } else {
      return false;
    }
  }
  /**
   * Save local storage data
   * @param {*} storageName
   * @param {*} storageValue
   */
  function saveStorageData(storageName, storageValue) {
    removeStorageData(storageName);
    window.localStorage.setItem(storageName, JSON.stringify(storageValue));
  }

  /**
   * Get local storage data
   * @param {*} storageName
   * @returns
   */
  function getStorageData(storageName) {
    return JSON.parse(window.localStorage.getItem(storageName));
  }

  /**
   * Remove local storage data
   * @param {*} storageName
   */
  function removeStorageData(storageName) {
    window.localStorage.removeItem(storageName);
  }

  // Reset Custom Labels button handler
  $('#ppch-reset-custom-labels').on('click', function (e) {
    e.preventDefault();

    if (typeof ppchToolsSettings === 'undefined') {
      return;
    }

    if (!confirm(ppchToolsSettings.resetLabelsConfirm)) {
      return;
    }

    var $button = $(this);
    var loadingLabel = (typeof ppchToolsSettings !== 'undefined' && ppchToolsSettings.resetLabelsLoading)
      ? ppchToolsSettings.resetLabelsLoading
      : 'Resetting...';
    var defaultLabel = (typeof ppchToolsSettings !== 'undefined' && ppchToolsSettings.resetLabelsButton)
      ? ppchToolsSettings.resetLabelsButton
      : 'Reset All Renamed Labels';

    $button.prop('disabled', true).text(loadingLabel);

    $.ajax({
      url: ppchToolsSettings.ajaxUrl,
      type: 'POST',
      data: {
        action: 'ppch_reset_custom_labels',
        nonce: ppchToolsSettings.resetLabelsNonce
      },
      success: function (response) {
        if (response.success) {
          location.reload();
        } else {
          alert(response.data.message);
          $button.prop('disabled', false).text(defaultLabel);
        }
      },
      error: function () {
        $button.prop('disabled', false).text(defaultLabel);
      }
    });
  });
});
