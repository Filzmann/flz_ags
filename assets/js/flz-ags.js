(function () {
  'use strict';

  function gradeKey(className) {
    className = (className || '').trim();
    if (/^WKK/i.test(className)) return 'WKK';
    var match = className.match(/^(\d{1,2})(?:[._]|$)/);
    return match ? String(parseInt(match[1], 10)) : '';
  }

  function includesValue(list, value) {
    return list.split(',').map(function (item) {
      return item.trim();
    }).filter(Boolean).indexOf(String(value)) !== -1;
  }

  function isAllowed(item, selectedClass, weekday) {
    var grade = gradeKey(selectedClass);
    var onlyGrade7 = item.getAttribute('data-only-grade-7') === '1';
    var allowed = (item.getAttribute('data-allowed-grades') || '').split(',').map(function (entry) {
      return entry.trim().toUpperCase();
    }).filter(Boolean);

    if (weekday) {
      var weekdays = item.getAttribute('data-weekdays') || item.getAttribute('data-weekday') || '';
      if (!includesValue(weekdays, weekday)) return false;
    }

    if (!selectedClass) return true;
    if (onlyGrade7 && grade !== '7') return false;
    if (allowed.length > 0 && allowed.indexOf(String(grade).toUpperCase()) === -1) return false;

    return true;
  }

  function applyFilters(scope) {
    var classSelect = scope.querySelector('[data-flz-ags-class-select]');
    var weekdaySelect = scope.querySelector('[data-flz-ags-weekday-select]');
    var selectedClass = classSelect ? classSelect.value : '';
    var weekday = weekdaySelect ? weekdaySelect.value : '';
    var items = scope.querySelectorAll('[data-flz-ags-filter-item]');

    items.forEach(function (item) {
      var show = isAllowed(item, selectedClass, weekday);
      item.hidden = !show;

      var input = item.querySelector('input[type="radio"]');
      if (input) {
        var isFull = item.getAttribute('data-full') === '1';
        input.disabled = !show || isFull;
        if (!show && input.checked) input.checked = false;
      }
    });
  }

  function initScope(scope) {
    var classSelects = scope.querySelectorAll('[data-flz-ags-class-select]');
    var weekdaySelects = scope.querySelectorAll('[data-flz-ags-weekday-select]');

    classSelects.forEach(function (select) {
      select.addEventListener('change', function () { applyFilters(scope); });
    });
    weekdaySelects.forEach(function (select) {
      select.addEventListener('change', function () { applyFilters(scope); });
    });

    applyFilters(scope);
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.flz-ags').forEach(initScope);
  });
}());
