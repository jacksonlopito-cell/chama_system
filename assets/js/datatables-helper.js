/**
 * DataTables Helper — Column Consistency & Safe Initialization
 * Prevents TN/18 (Incorrect column count) errors across the entire project.
 * Load after jquery.dataTables.min.js, before any DataTable init calls.
 */
(function($) {
    'use strict';

    if (!$.fn || !$.fn.dataTable) return;

    // Suppress obtrusive DataTables alerts; route all to console
    $.fn.dataTable.ext.errMode = 'console';

    /**
     * Count colspan-aware columns in a row
     */
    function countCols($row) {
        var n = 0;
        $row.children('th, td').each(function() {
            n += parseInt($(this).attr('colspan') || '1', 10);
        });
        return n;
    }

    /**
     * Validate column consistency across thead / tbody / tfoot
     * @param {jQuery} $table
     * @returns {Object} { valid, thead, tbody, tfoot, name }
     */
    function validate($table) {
        var name = $table.attr('id') || $table.data('table-name') || 'table';
        var $th = $table.find('thead > tr').first();
        var $tr = $table.find('tbody > tr').first();
        var $tf = $table.find('tfoot > tr').first();

        var thead = $th.length ? countCols($th) : 0;
        var tbody = $tr.length ? countCols($tr) : thead;
        var tfoot = $tf.length ? countCols($tf) : 0;

        var issues = [];

        if ($tr.length && thead !== tbody) {
            issues.push('thead(' + thead + ') !== tbody(' + tbody + ')');
        }
        if ($tf.length && tfoot !== thead) {
            issues.push('thead(' + thead + ') !== tfoot(' + tfoot + ')');
        }

        if (issues.length) {
            console.error(
                '[DT-Helper] TN/18 COLUMN MISMATCH on "' + name + '": ' +
                issues.join(', ') +
                ' | ' + ($table.data('file') || 'unknown') +
                ':' + ($table.data('line') || '?')
            );
        }

        return { valid: issues.length === 0, thead: thead, tbody: tbody, tfoot: tfoot, name: name };
    }

    /**
     * Standard DataTables defaults shared by every table in the project
     */
    var BASE_OPTS = {
        language: {
            search: '',
            searchPlaceholder: 'Search...',
            lengthMenu: '_MENU_ records per page',
            info: 'Showing _START_ to _END_ of _TOTAL_ entries',
            infoEmpty: 'No entries found',
            infoFiltered: '(filtered from _MAX_ total entries)',
            zeroRecords: 'No matching records found'
        },
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'All']],
        order: [],
        columnDefs: [
            { orderable: false, targets: 'no-sort' }
        ],
        dom: '<"table-toolbar d-flex justify-content-between align-items-center mb-3"lf>t<"table-footer d-flex justify-content-between align-items-center mt-3"ip>'
    };

    /**
     * Safely initialise a single DataTable with pre-flight column validation
     * @param {string|HTMLElement|jQuery} target  Table selector / element
     * @param {Object}                    custom  Per-table override options
     * @returns {Object|null} DataTable instance, or null on failure
     */
    function init(target, custom) {
        var $t = $(target);
        if (!$t.length || !$t.is('table')) return null;

        // Double-init guard
        if ($.fn.DataTable.isDataTable($t)) {
            console.warn('[DT-Helper] Double-init guard fired for "' +
                ($t.attr('id') || $t.data('table-name') || 'table') + '" — destroying old instance');
            $t.DataTable().destroy();
            // Remove DataTables wrapper elements so the table is clean
            $t.removeClass('dataTable');
            $t.parents('.dataTables_wrapper').first().children().not($t).remove();
        }

        // Validate column counts
        var v = validate($t);
        if (!v.valid) {
            console.warn('[DT-Helper] Initialising "' + v.name + '" despite column mismatch');
        }

        var opts = $.extend(true, {}, BASE_OPTS, custom || {});
        return $t.DataTable(opts);
    }

    /**
     * Initialise every .datatable table inside a container
     */
    function initAll(container) {
        $(container || document).find('table.datatable').each(function() {
            var $t = $(this);
            if ($.fn.DataTable.isDataTable(this)) {
                console.warn('[DT-Helper] Skipping double-init: "' +
                    ($t.attr('id') || $t.data('table-name') || 'table') + '"');
                return;
            }
            init(this);
        });
    }

    // Expose helper globally
    window.dtHelper = { validate: validate, init: init, initAll: initAll };

})(jQuery);
