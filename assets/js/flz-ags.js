(function () {
  'use strict';

  var activeRegistrationPanel = null;

  function gradeKey(className) {
    className = (className || '').trim();
    var match = className.match(/(?:^|\b)klasse\s*(7|8|9|10|11|12)\b/i);

    if (match) return match[1];

    match = className.match(/^(7|8|9|10|11|12)(?=\D|$)/);
    return match ? match[1] : '';
  }

  function includesValue(list, value) {
    return list.split(',').map(function (item) {
      return item.trim();
    }).filter(Boolean).indexOf(String(value)) !== -1;
  }

  function normalize(value) {
    value = String(value || '').toLocaleLowerCase('de');
    return typeof value.normalize === 'function'
      ? value.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      : value;
  }

  function isAllowed(item, selectedClass, weekday, leader, searchTerm) {
    var grade = gradeKey(selectedClass);
    var onlyGrade7 = item.getAttribute('data-only-grade-7') === '1';
    var allowed = (item.getAttribute('data-allowed-grades') || '').split(',').map(function (entry) {
      return entry.trim().toUpperCase();
    }).filter(Boolean);

    if (weekday) {
      var weekdays = item.getAttribute('data-weekdays') || item.getAttribute('data-weekday') || '';
      if (!includesValue(weekdays, weekday)) return false;
    }

    if (leader && normalize(item.getAttribute('data-leader')) !== normalize(leader)) return false;
    if (searchTerm && normalize(item.getAttribute('data-search')).indexOf(normalize(searchTerm)) === -1) return false;

    if (!selectedClass) return true;
    if (onlyGrade7 && grade !== '7') return false;
    if (allowed.length > 0 && allowed.indexOf(String(grade).toUpperCase()) === -1) return false;

    return true;
  }

  function applyFilters(scope) {
    var classSelect = scope.querySelector('[data-flz-ags-class-select]');
    var weekdaySelect = scope.querySelector('[data-flz-ags-weekday-select]');
    var leaderSelect = scope.querySelector('[data-flz-ags-leader-select]');
    var searchInput = scope.querySelector('[data-flz-ags-search-input]');
    var sortSelect = scope.querySelector('[data-flz-ags-sort-select]');
    var itemsContainer = scope.querySelector('[data-flz-ags-items]');
    var resultsStatus = scope.querySelector('[data-flz-ags-results-status]');
    var noResults = scope.querySelector('[data-flz-ags-no-results]');
    var selectedClass = classSelect ? classSelect.value : '';
    var weekday = weekdaySelect ? weekdaySelect.value : '';
    var leader = leaderSelect ? leaderSelect.value : '';
    var searchTerm = searchInput ? searchInput.value.trim() : '';
    var sortKey = sortSelect ? sortSelect.value : 'default';
    var allowedSorts = ['default', 'title', 'grade', 'leader', 'weekday'];
    var items = Array.prototype.slice.call(scope.querySelectorAll('[data-flz-ags-filter-item]'));
    var visibleCount = 0;

    if (allowedSorts.indexOf(sortKey) === -1) sortKey = 'default';

    if (itemsContainer) {
      items.sort(function (left, right) {
        var attribute = 'data-sort-' + sortKey;
        var leftValue = left.getAttribute(attribute) || '';
        var rightValue = right.getAttribute(attribute) || '';
        var numeric = sortKey === 'default' || sortKey === 'grade' || sortKey === 'weekday';
        var comparison = numeric
          ? Number(leftValue) - Number(rightValue)
          : normalize(leftValue).localeCompare(normalize(rightValue), 'de');

        if (comparison === 0 && sortKey !== 'title') {
          comparison = normalize(left.getAttribute('data-sort-title')).localeCompare(normalize(right.getAttribute('data-sort-title')), 'de');
        }
        return comparison;
      });

      items.forEach(function (item) {
        if (item.closest('.flz-ags') === scope) itemsContainer.appendChild(item);
      });
    }

    items.forEach(function (item) {
      if (item.closest('.flz-ags') !== scope) return;

      var show = isAllowed(item, selectedClass, weekday, leader, searchTerm);
      item.hidden = !show;
      if (show) visibleCount += 1;

      var input = item.getAttribute('data-weekday') ? item.querySelector('input[type="radio"]') : null;
      if (input) {
        var isFull = item.getAttribute('data-full') === '1';
        input.disabled = !show || isFull;
        if (!show && input.checked) input.checked = false;
      }
    });

    if (resultsStatus) resultsStatus.textContent = visibleCount + (visibleCount === 1 ? ' AG angezeigt.' : ' AGs angezeigt.');
    if (noResults) noResults.hidden = visibleCount !== 0;
  }

  function setRegistrationPanelSemantics(panel, active) {
    var drawer;

    if (!panel) return;

    drawer = panel.querySelector('.flz-ui-floating-panel__drawer');
    if (!drawer) return;

    if (active) {
      drawer.setAttribute('role', 'dialog');
      drawer.setAttribute('aria-modal', 'true');
    } else {
      drawer.setAttribute('role', 'region');
      drawer.removeAttribute('aria-modal');
    }
  }

  function clearRegistrationModalBackground() {
    document.querySelectorAll('[data-flz-ags-modal-inert]').forEach(function (element) {
      element.inert = false;
      element.removeAttribute('data-flz-ags-modal-inert');
    });
  }

  function makeRegistrationModalBackgroundInert(panel) {
    var current = panel;
    var parent;

    while (current && current.parentElement) {
      parent = current.parentElement;
      Array.prototype.forEach.call(parent.children, function (sibling) {
        if (sibling === current || sibling.inert) return;

        sibling.inert = true;
        sibling.setAttribute('data-flz-ags-modal-inert', 'true');
      });

      if (parent === document.body) break;
      current = parent;
    }
  }

  function closeCompetingRegistrationPanel(panel) {
    var trigger;

    panel.classList.remove('is-open');
    trigger = panel.querySelector('[data-flz-ui-floating-panel-open]');
    if (trigger) trigger.setAttribute('aria-expanded', 'false');
    setRegistrationPanelSemantics(panel, false);
  }

  function syncRegistrationModalState() {
    var openPanels = Array.prototype.slice.call(document.querySelectorAll('.flz-ags-registration-panel.is-open'));

    if (activeRegistrationPanel && openPanels.indexOf(activeRegistrationPanel) === -1) {
      setRegistrationPanelSemantics(activeRegistrationPanel, false);
      activeRegistrationPanel = null;
    }

    if (!activeRegistrationPanel && openPanels.length > 0) {
      activeRegistrationPanel = openPanels[0];
    }

    openPanels.forEach(function (panel) {
      if (panel !== activeRegistrationPanel) closeCompetingRegistrationPanel(panel);
    });

    clearRegistrationModalBackground();
    if (!activeRegistrationPanel) return;

    setRegistrationPanelSemantics(activeRegistrationPanel, true);
    makeRegistrationModalBackgroundInert(activeRegistrationPanel);
  }

  function initScope(scope) {
    var classSelects = scope.querySelectorAll('[data-flz-ags-class-select]');
    var weekdaySelects = scope.querySelectorAll('[data-flz-ags-weekday-select]');
    var leaderSelects = scope.querySelectorAll('[data-flz-ags-leader-select]');
    var searchInputs = scope.querySelectorAll('[data-flz-ags-search-input]');
    var sortSelects = scope.querySelectorAll('[data-flz-ags-sort-select]');

    classSelects.forEach(function (select) {
      select.addEventListener('change', function () { applyFilters(scope); });
    });
    weekdaySelects.forEach(function (select) {
      select.addEventListener('change', function () { applyFilters(scope); });
    });
    leaderSelects.forEach(function (select) {
      select.addEventListener('change', function () { applyFilters(scope); });
    });
    searchInputs.forEach(function (input) {
      input.addEventListener('input', function () { applyFilters(scope); });
    });
    sortSelects.forEach(function (select) {
      select.addEventListener('change', function () { applyFilters(scope); });
    });

    applyFilters(scope);
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.flz-ags').forEach(initScope);
    syncRegistrationModalState();
  });

  document.addEventListener('click', syncRegistrationModalState);
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') syncRegistrationModalState();
  });
}());
