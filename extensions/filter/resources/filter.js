/*
 * @copyright Copyright (c) 2012, {@link http://aksw.org AKSW}
 * @license   http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

var filterboxcounter = 0; // dont overwrite previous filters

function showAddFilterBox() {
    // $("#addFilterWindowOverlay").show();
    $("#addFilterWindowOverlay").modal({
        overlay: 80,
        overlayCss: {backgroundColor: '#000'},
        overlayClose: true,
        onOpen: function (dialog) {
            dialog.overlay.fadeIn(effectTime, function () {
                dialog.data.show();
                dialog.container.fadeIn(effectTime);
            })
        },
        onClose: function (dialog) {
            dialog.container.fadeOut(effectTime, function () {
                dialog.overlay.fadeOut(effectTime, function () {
                    $.modal.close();
                });
            });
        }
    });
}
function updatePossibleValues() {
    if ($("#property option:selected").length == 0) {
        return;
    }

    $("#addwindow #possiblevalues").addClass("is-processing");
    $("#property option:selected").each(function () {
        var inverse = $(this).hasClass("InverseProperty") ? "true" : "false";
        $("#possiblevalues").load(urlBase + "filter/getpossiblevalues?predicate=" + escape($(this).attr("about")) + "&inverse=" + inverse + "&list=" + listName, {}, function () {
            // properties loaded
            $("#addwindow #possiblevalues").removeClass("is-processing");

            // if type of possible values is uri, we disable the free text search
            var possible = $("#addwindow #possiblevalues > option");
            if (possible.length < 1)
                return;

            if (possible.first().attr("type") === 'uri') {
                $('#equalsfilterselect').find('option[value=unequals]').removeAttr('disabled').show();

                $('#resttype').find('option[value=contains]').attr('disabled', 'disabled').hide();
                $('#resttype').find('option[value=larger]').attr('disabled', 'disabled').hide();
                $('#resttype').find('option[value=smaller]').attr('disabled', 'disabled').hide();
                $('#resttype').find('option[value=between]').attr('disabled', 'disabled').hide();
                $('#resttype').val('bound');
            }
            else {
                $('#equalsfilterselect').find('option[value=unequals]').attr('disabled', 'disabled').hide();

                $('#resttype').find('option[value=contains]').removeAttr('disabled').show();
                $('#resttype').find('option[value=larger]').removeAttr('disabled').show();
                $('#resttype').find('option[value=smaller]').removeAttr('disabled').show();
                $('#resttype').find('option[value=between]').removeAttr('disabled').show();
                $('#resttype').val('contains');
                //$('#freeSearch').show();
            }
            $('#equalsfilterselect').val('equals');
            updateRestType();
        });
    });
}

function updateRestType() {
    var type = $("#resttype option:selected").val();
    if (type == "contains" || type == "larger" || type == "smaller") {
        if ($("#valueboxes").children().length != 1) {
            $("#valueboxes").empty();
            $("#valueboxes").append("<input type=\"text\" id=\"value1\"/>");
        }
    }
    if (type == "between") {
        if ($("#valueboxes").children().length != 2) {
            $("#valueboxes").empty();
            $("#valueboxes").append("<input type=\"text\" id=\"value1\"/>");
            $("#valueboxes").append("<input type=\"text\" id=\"value2\"/>");
        }
    }
    if (type == "bound") {
        if ($("#valueboxes").children().length != 0) {
            $("#valueboxes").empty();
        }
    }
}

function removeAllFilters() {
    // $("#addFilterWindowOverlay").hide();
    $.modal.close();
    filter.removeAll(function () {

    });
}

function toggleHelp() {
    $("#helptext").slideToggle();
}

$(document).ready(function () {
    //initial layout
    $("#gqbclassrestrictionsexist").hide();
    $("#addFilterWindowOverlay").hide();
    $("#filterbox #clear").hide();

    $('#filter').droppable({
        accept: '.show-property',
        scope: 'Resource',
        activeClass: 'ui-droppable-accepted-window',
        hoverClass: 'ui-droppable-hovered',
        drop: function (event, ui) {
            $("#property option:selected").each(function () {
                $(this).attr('selected', false);
            });
            $("#property option[about=" + $(ui.draggable).attr('about') + "]").attr('selected', true);
            $("#property option:selected").each(updatePossibleValues);
            showAddFilterBox();
        }
    });

    $("#addwindowhide").click(function () {
        $.modal.close();
    });

    $("#addwindow #add").click(function () {
        $.modal.close();

        var prop = $("#addwindow #property option:selected").attr("about");
        var propLabel = $("#addwindow #property option:selected").html();
        var inverse = $("#addwindow #property option:selected").hasClass("InverseProperty");

        var filtertype = $("#addwindow #resttype option:selected").val();
        var negate = $("#negate").is(':checked');
        var value1 = $("#addwindow #value1").val();
        if (typeof value1 == "undefined") {
            value1 = null;
        }

        var value2 = $("#addwindow #value2").val();
        if (typeof value2 == "undefined") {
            value2 = null;
        }

        var type = "literal";
        var typedata = null;

        // if value entering is possible but nothing entered: check if user selected something in the possible values box
        var hasNoTextValue = (value1 == "" && $("#valueboxes").children().length == 1);
        if ( hasNoTextValue || $("#addwindow #possiblevalues").val()) {
            if ($("#addwindow #possiblevalues option:selected").length == 0) {
                return; // block add button
            }
            value1 = $("#addwindow #possiblevalues option:selected").attr("value");

            filtertype = $("#addwindow #equalsfilterselect").val();
            if (filtertype === 'unequals') {
                filtertype = 'equals';
                negate = true;
            }
            else {
                negate = false;
            }

            type = $("#addwindow #possiblevalues option:selected").attr("type");
            var language = $("#addwindow #possiblevalues option:selected").attr("language");
            var datatype = $("#addwindow #possiblevalues option:selected").attr("datatype");

            if (type == "literal" && typeof language != 'undefined') {
                typedata = language;
            } else if (type == "typed-literal") {
                typedata = datatype;
            }
        }
        // if value not empty, try to determine type from list of possible values
        else {
            var possible = $("#addwindow #possiblevalues > option");
            if (possible.length) {
                type = possible.first().attr("type");
                typedata = possible.first().attr("datatype");
            }
        }

        filter.add("filterbox" + filter.count, prop, inverse, propLabel, filtertype, value1, value2, type, typedata, function (newfilter) {
            //react in filter box
            //$("#addwindow").hide();
        }, false, negate);
    });

    //show possible values for select property
    $("#property").change(updatePossibleValues);

    //different filter types need different value input fields
    // bound: none
    // contains, larger, smaller: one
    // between: two - not implemented
    // date: datepicker - not implemented
    $("#resttype").change(updateRestType);

    //$.dump(filter);
    //register the filter box for (other) filter events
    //filter.addCallback(function(newfilter){ showFilter() });

    $('.filter .delete').click(function () {
        filter.remove($(this).parents('.filter').attr('id'));
    })
});

