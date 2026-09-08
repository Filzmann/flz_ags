(function ($) {
  'use strict';

  var pageSearchTimers = {};
  var pageSettings = window.flzAgsAdmin || { ajaxUrl: '', nonce: '', strings: {} };
  var strings = pageSettings.strings || {};

  function pageString(key, fallback) {
    return strings[key] || fallback;
  }

  function normalizeListValue(value) {
    value = String(value || '').toLocaleLowerCase('de');
    return typeof value.normalize === 'function'
      ? value.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      : value;
  }

  function listIncludes(list, value) {
    return String(list || '').split(',').map(function (item) {
      return item.trim();
    }).filter(Boolean).indexOf(String(value)) !== -1;
  }

  function applyAdminCourseList(scope) {
    var searchInput = scope.querySelector('[data-flz-ags-admin-search]');
    var categorySelect = scope.querySelector('[data-flz-ags-admin-category]');
    var gradeSelect = scope.querySelector('[data-flz-ags-admin-grade]');
    var leaderSelect = scope.querySelector('[data-flz-ags-admin-leader]');
    var weekdaySelect = scope.querySelector('[data-flz-ags-admin-weekday]');
    var container = scope.querySelector('[data-flz-ags-admin-items]');
    var status = scope.querySelector('[data-flz-ags-admin-results-status]');
    var noResults = scope.querySelector('[data-flz-ags-admin-no-results]');
    var searchTerm = searchInput ? normalizeListValue(searchInput.value.trim()) : '';
    var category = categorySelect ? normalizeListValue(categorySelect.value) : '';
    var grade = gradeSelect ? gradeSelect.value : '';
    var leader = leaderSelect ? normalizeListValue(leaderSelect.value) : '';
    var weekday = weekdaySelect ? weekdaySelect.value : '';
    var sortKey = scope.flzAgsSortKey || 'default';
    var sortDirection = scope.flzAgsSortDirection === 'descending' ? -1 : 1;
    var allowedSorts = ['default', 'title', 'category', 'grade', 'leader', 'weekday'];
    var items = Array.prototype.slice.call(scope.querySelectorAll('[data-flz-ags-admin-item]'));
    var visibleCount = 0;

    if (allowedSorts.indexOf(sortKey) === -1) sortKey = 'default';

    items.sort(function (left, right) {
      var attribute = 'data-sort-' + sortKey;
      var leftValue = left.getAttribute(attribute) || '';
      var rightValue = right.getAttribute(attribute) || '';
      var numeric = sortKey === 'default' || sortKey === 'grade' || sortKey === 'weekday';
      var comparison = numeric
        ? Number(leftValue) - Number(rightValue)
        : normalizeListValue(leftValue).localeCompare(normalizeListValue(rightValue), 'de');

      if (comparison === 0 && sortKey !== 'title') {
        comparison = normalizeListValue(left.getAttribute('data-sort-title')).localeCompare(normalizeListValue(right.getAttribute('data-sort-title')), 'de');
      }
      return comparison * sortDirection;
    });

    scope.querySelectorAll('[data-flz-ags-admin-sort-button]').forEach(function (button) {
      var header = button.closest('th');
      var active = button.getAttribute('data-flz-ags-admin-sort-button') === sortKey;

      button.setAttribute('aria-pressed', active ? 'true' : 'false');
      if (header) header.setAttribute('aria-sort', active ? (sortDirection === 1 ? 'ascending' : 'descending') : 'none');
    });

    items.forEach(function (item) {
      var details = scope.querySelector('[data-flz-ui-editable-details-for="' + item.id + '"]');
      var allowedGrades = item.getAttribute('data-allowed-grades') || '';
      var show = true;

      if (category) show = normalizeListValue(item.getAttribute('data-category')) === category;
      if (grade) {
        show = show && (item.getAttribute('data-only-grade-7') === '1'
          ? grade === '7'
          : (!allowedGrades || listIncludes(allowedGrades, grade)));
      }
      if (show && weekday) show = listIncludes(item.getAttribute('data-weekdays'), weekday);
      if (show && leader) show = normalizeListValue(item.getAttribute('data-leader')) === leader;
      if (show && searchTerm) show = normalizeListValue(item.getAttribute('data-search')).indexOf(searchTerm) !== -1;

      item.hidden = !show;
      if (show) visibleCount += 1;

      if (details) {
        if (!show && !details.hidden) {
          details.setAttribute('data-flz-ags-filter-hidden', 'true');
          details.hidden = true;
        } else if (show && details.getAttribute('data-flz-ags-filter-hidden') === 'true') {
          details.removeAttribute('data-flz-ags-filter-hidden');
          details.hidden = false;
        }
      }

      if (container) {
        container.appendChild(item);
        if (details) container.appendChild(details);
      }
    });

    if (noResults) noResults.hidden = visibleCount !== 0 || items.length === 0;
    if (status) status.textContent = visibleCount + (visibleCount === 1 ? ' AG angezeigt.' : ' AGs angezeigt.');
  }

  function initAdminCourseLists() {
    if (!document.querySelectorAll) return;

    document.querySelectorAll('[data-flz-ags-admin-list]').forEach(function (scope) {
      scope.querySelectorAll('[data-flz-ags-admin-search]').forEach(function (control) {
        control.addEventListener('input', function () { applyAdminCourseList(scope); });
      });
      scope.querySelectorAll('[data-flz-ags-admin-category], [data-flz-ags-admin-grade], [data-flz-ags-admin-leader], [data-flz-ags-admin-weekday]').forEach(function (control) {
        control.addEventListener('change', function () { applyAdminCourseList(scope); });
      });
      scope.querySelectorAll('[data-flz-ags-admin-sort-button]').forEach(function (button) {
        button.addEventListener('click', function () {
          var sortKey = button.getAttribute('data-flz-ags-admin-sort-button');
          var allowedSorts = ['title', 'category', 'grade', 'leader', 'weekday'];

          if (allowedSorts.indexOf(sortKey) === -1) return;

          if (scope.flzAgsSortKey === sortKey) {
            scope.flzAgsSortDirection = scope.flzAgsSortDirection === 'ascending' ? 'descending' : 'ascending';
          } else {
            scope.flzAgsSortKey = sortKey;
            scope.flzAgsSortDirection = 'ascending';
          }
          applyAdminCourseList(scope);
        });
      });
      applyAdminCourseList(scope);
    });
  }

  function setPageMessage($field, message, isError) {
    var $results = $field.find('[data-flz-ags-page-results]');
    $results.empty().append(
      $('<p>')
        .addClass(isError ? 'flz-ags-page-message flz-ags-page-message-error' : 'flz-ags-page-message')
        .text(message)
    );
  }

  function updateSelectedPage($field, page) {
    var $selected = $field.find('[data-flz-ags-page-selected]');
    $field.find('[data-flz-ags-page-id]').val(page && page.id ? page.id : '');
    $selected.empty();

    if (!page || !page.id) {
      $('<strong>')
        .text($field.attr('data-empty-label') || pageString('noSelection', 'Keine Detailseite ausgewählt.'))
        .appendTo($selected);
      return;
    }

    $('<strong>').text(page.label || page.title || ('Seite #' + page.id)).appendTo($selected);
    if (page.editUrl) {
      $selected.append(' · ').append($('<a>').attr('href', page.editUrl).text('bearbeiten'));
    }
    if (page.url) {
      $selected.append(' · ').append(
        $('<a>')
          .attr('href', page.url)
          .attr('target', '_blank')
          .attr('rel', 'noopener noreferrer')
          .text('ansehen')
      );
    }
  }

  function renderPageResults($field, pages) {
    var $results = $field.find('[data-flz-ags-page-results]');
    $results.empty();

    if (!pages || !pages.length) {
      setPageMessage($field, pageString('noResults', 'Keine passende Seite gefunden.'), false);
      return;
    }

    $.each(pages, function (_, page) {
      var $button = $('<button>')
        .attr('type', 'button')
        .addClass('button flz-ags-page-result')
        .text(page.label || page.title || ('Seite #' + page.id))
        .data('flzAgsPage', page);
      $results.append($button);
    });
  }

  function searchPages($input) {
    var term = $.trim($input.val());
    var $field = $input.closest('[data-flz-ags-page-field]');

    if (term.length < 2) {
      $field.find('[data-flz-ags-page-results]').empty();
      return;
    }

    setPageMessage($field, pageString('searching', 'Suche läuft …'), false);
    $.ajax({
      url: pageSettings.ajaxUrl,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'flz_ags_search_detail_pages',
        nonce: pageSettings.nonce,
        term: term
      }
    }).done(function (response) {
      if (!response || !response.success) {
        setPageMessage($field, pageString('searchError', 'Die Seitensuche konnte nicht geladen werden.'), true);
        return;
      }
      renderPageResults($field, response.data.pages || []);
    }).fail(function () {
      setPageMessage($field, pageString('searchError', 'Die Seitensuche konnte nicht geladen werden.'), true);
    });
  }

  $(document).on('click', '[data-flz-ags-media-button]', function (event) {
    event.preventDefault();

    var $field = $(this).closest('.flz-ags-image-field');
    var $input = $field.find('[data-flz-ags-image-input]');
    var $preview = $field.find('[data-flz-ags-image-preview]');

    var frame = wp.media({
      title: 'Vorschaubild auswählen',
      button: { text: 'Bild verwenden' },
      multiple: false,
      library: { type: 'image' }
    });

    frame.on('select', function () {
      var attachment = frame.state().get('selection').first().toJSON();
      var url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
      $input.val(url).trigger('change');
      $preview.attr('src', url);
    });

    frame.open();
  });

  $(document).on('click', '[data-flz-ags-clear-image]', function (event) {
    event.preventDefault();
    var $field = $(this).closest('.flz-ags-image-field');
    $field.find('[data-flz-ags-image-input]').val('').trigger('change');
  });

  $(document).on('change', '[data-flz-ags-image-input]', function () {
    var value = $(this).val();
    var $preview = $(this).closest('.flz-ags-image-field').find('[data-flz-ags-image-preview]');
    if (value) {
      $preview.attr('src', value);
    }
  });

  $(document).on('input', '[data-flz-ags-page-search]', function () {
    var input = this;
    var timerKey = $(input).attr('id') || 'flz-ags-page-search';

    window.clearTimeout(pageSearchTimers[timerKey]);
    pageSearchTimers[timerKey] = window.setTimeout(function () {
      searchPages($(input));
    }, 250);
  });

  $(document).on('click', '.flz-ags-page-result', function (event) {
    event.preventDefault();
    var $button = $(this);
    var $field = $button.closest('[data-flz-ags-page-field]');
    var page = $button.data('flzAgsPage');

    updateSelectedPage($field, page);
    $field.find('[data-flz-ags-page-search]').val(page && page.title ? page.title : '');
    $field.find('[data-flz-ags-page-results]').empty();
  });

  $(document).on('click', '[data-flz-ags-clear-detail-page]', function (event) {
    event.preventDefault();
    var $field = $(this).closest('[data-flz-ags-page-field]');
    updateSelectedPage($field, null);
    $field.find('[data-flz-ags-page-search]').val('');
    $field.find('[data-flz-ags-page-results]').empty();
  });

  $(document).on('click', '[data-flz-ags-create-detail-page]', function (event) {
    event.preventDefault();
    var $button = $(this);
    var $field = $button.closest('[data-flz-ags-page-field]');
    var titleSelector = $button.attr('data-title-source');
    var title = titleSelector ? $.trim($(titleSelector).val()) : '';

    if (!title) {
      setPageMessage($field, pageString('createNeedsTitle', 'Bitte zuerst einen AG-Titel eintragen.'), true);
      return;
    }

    $button.prop('disabled', true);
    setPageMessage($field, pageString('searching', 'Suche läuft …'), false);
    $.ajax({
      url: pageSettings.ajaxUrl,
      method: 'POST',
      dataType: 'json',
      data: {
        action: 'flz_ags_create_detail_page',
        nonce: pageSettings.nonce,
        title: title
      }
    }).done(function (response) {
      if (!response || !response.success || !response.data || !response.data.page) {
        setPageMessage(
          $field,
          response && response.data && response.data.message ? response.data.message : pageString('createError', 'Die Detailseite konnte nicht angelegt werden.'),
          true
        );
        return;
      }
      updateSelectedPage($field, response.data.page);
      $field.find('[data-flz-ags-page-search]').val(response.data.page.title || title);
      $field.find('[data-flz-ags-page-results]').empty();
    }).fail(function (xhr) {
      var message = pageString('createError', 'Die Detailseite konnte nicht angelegt werden.');
      if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
        message = xhr.responseJSON.data.message;
      }
      setPageMessage($field, message, true);
    }).always(function () {
      $button.prop('disabled', false);
    });
  });

  initAdminCourseLists();
}(jQuery));
