/**
 * This file is part of the {@link http://amsl.technology amsl} project.
 *
 * @author Gregor Taetzner
 * @copyright Copyright (c) 2016, {@link http://ub.uni-leipzig.de Leipzig University Library}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */


/**
 * Now use the input fields to find SUSHI settings while typing
 */
$(document).ready(function() {
    $('#report-accordion').find('.typeahead').each(function(index) {
        var param_id = $(this).attr('name').substr(10).slice(0, -1);
        var query_id = $(this).closest('form').find("input[name='queryID']").attr('value');

        // initialize search engine
        var search = new Bloodhound({
            datumTokenizer: Bloodhound.tokenizers.obj.whitespace('literal'),
            queryTokenizer: Bloodhound.tokenizers.whitespace,
            limit: 10,
            remote: {
                url: urlBase + 'reports/complete?search=%SEARCH&parameter_id=' + param_id + '&query_id=' + query_id,
                wildcard: '%SEARCH'
            }
        });

        // initialize autocomplete
        $(this).typeahead(null, {
            display: function(item) {
                return item.uri
            },
            templates: {
                empty: [
                    '<div class="empty-message">',
                    'No results found',
                    '</div>'
                ].join('\n'),
                suggestion: Handlebars.compile('<div>{{literal}}</div>')
            },
            source: search
        }).on('typeahead:open', function() {
            $(this).closest('.ui-accordion-content').css('overflow','visible');
        }).on('typeahead:close', function() {
            $(this).closest('.ui-accordion-content').css('overflow','auto');
        });
    });

    $( "#report-accordion" ).accordion({
        icons: false
    });
});
