/**
 * Help tooltips on the Checklists screen (core/Utils/HelpTip.php).
 *
 * - Opens on hover, on keyboard focus of the info button and on click/tap.
 * - Stays open while the pointer is over the button or the tooltip, so the
 *   text can be read and selected (WCAG 2.2, 1.4.13 Content on Hover or Focus).
 * - Escape closes it without moving focus.
 * - Kept inside the viewport on narrow screens, in LTR and RTL.
 *
 * Events are delegated from the document, so tooltips in rows added later
 * (custom tasks, Pro duplicates) work too.
 *
 * @since 2.31.0
 */
(function () {
  'use strict';

  var HIDE_DELAY = 200;
  var MARGIN = 8;
  var hideTimer = null;
  var current = null;

  function bubbleOf(tip) {
    return tip ? tip.querySelector('.ppch-help-tip__bubble') : null;
  }

  // The visible horizontal area: the viewport, narrowed by every ancestor
  // that clips its content (the table scrolls sideways on narrow screens).
  function visibleArea(el) {
    var area = { left: 0, right: document.documentElement.clientWidth };
    for (var node = el.parentElement; node && node !== document.body; node = node.parentElement) {
      if (/(auto|scroll|hidden|clip)/.test(window.getComputedStyle(node).overflowX)) {
        var box = node.getBoundingClientRect();
        area.left = Math.max(area.left, box.left);
        area.right = Math.min(area.right, box.right);
      }
    }
    return area;
  }

  function place(tip) {
    var bubble = bubbleOf(tip);
    if (!bubble) {
      return;
    }

    bubble.style.transform = '';
    bubble.style.maxWidth = '';
    tip.classList.remove('ppch-help-tip--below');

    // Keep it inside the viewport and inside the table's scroll container,
    // which would otherwise clip it on narrow screens.
    var area = visibleArea(tip);
    var left = area.left;
    var right = area.right;
    var room = right - left - 2 * MARGIN;
    if (room > 0 && bubble.offsetWidth > room) {
      bubble.style.maxWidth = room + 'px';
    }

    var rect = bubble.getBoundingClientRect();
    var shift = 0;

    if (rect.right > right - MARGIN) {
      shift = right - MARGIN - rect.right;
    }
    if (rect.left + shift < left + MARGIN) {
      shift = left + MARGIN - rect.left;
    }
    if (shift) {
      bubble.style.transform = 'translateX(' + Math.round(shift) + 'px)';
    }

    // Not enough room above (e.g. under the admin bar): show it below.
    if (rect.top < MARGIN + 32) {
      tip.classList.add('ppch-help-tip--below');
    }
  }

  function show(tip) {
    clearTimeout(hideTimer);
    if (current && current !== tip) {
      hide(current);
    }
    var bubble = bubbleOf(tip);
    if (!bubble) {
      return;
    }
    bubble.hidden = false;
    tip.classList.add('is-open');
    current = tip;
    place(tip);
  }

  function hide(tip) {
    var bubble = bubbleOf(tip);
    if (bubble) {
      bubble.hidden = true;
      bubble.style.transform = '';
      bubble.style.maxWidth = '';
    }
    tip.classList.remove('is-open', 'ppch-help-tip--below');
    if (current === tip) {
      current = null;
    }
  }

  function hideSoon(tip) {
    clearTimeout(hideTimer);
    hideTimer = setTimeout(function () {
      var focused = document.activeElement;
      if (!tip.matches(':hover') && !(focused && tip.contains(focused))) {
        hide(tip);
      }
    }, HIDE_DELAY);
  }

  function tipFrom(target) {
    return target && target.closest ? target.closest('.ppch-help-tip') : null;
  }

  document.addEventListener('mouseover', function (event) {
    var tip = tipFrom(event.target);
    if (tip) {
      show(tip);
    }
  });

  document.addEventListener('mouseout', function (event) {
    var tip = tipFrom(event.target);
    if (tip && !tip.contains(event.relatedTarget)) {
      hideSoon(tip);
    }
  });

  document.addEventListener('focusin', function (event) {
    var tip = tipFrom(event.target);
    if (tip) {
      show(tip);
    } else if (current) {
      hide(current);
    }
  });

  document.addEventListener('focusout', function (event) {
    var tip = tipFrom(event.target);
    if (tip && !tip.contains(event.relatedTarget)) {
      hideSoon(tip);
    }
  });

  document.addEventListener('click', function (event) {
    var button = event.target.closest ? event.target.closest('.ppch-help-tip__button') : null;
    if (button) {
      // Touch screens have no hover: a tap opens it, a tap elsewhere closes it.
      show(tipFrom(button));
      event.preventDefault();
      return;
    }
    if (current && !current.contains(event.target)) {
      hide(current);
    }
  });

  document.addEventListener('keydown', function (event) {
    if ((event.key === 'Escape' || event.key === 'Esc') && current) {
      hide(current);
      // Escape only dismisses the tooltip; the focus stays where it is.
      event.stopPropagation();
    }
  }, true);

  window.addEventListener('resize', function () {
    if (current) {
      place(current);
    }
  });
})();
