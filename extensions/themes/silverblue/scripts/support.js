
/**
 * This file is part of the {@link http://ontowiki.net OntoWiki} project.
 *
 * @copyright Copyright (c) 2009, {@link http://aksw.org AKSW}
 * @license http://opensource.org/licenses/gpl-license.php GNU General Public License (GPL)
 */

/**
 * OntoWiki Support functions
 */

function toggleExpansion(event) {
    var target = $(event.target);
    var resourceUri = target.next().attr('about') ? target.next().attr('about') : target.next().attr('resource');
    
    if (target.hasClass('expand')) {
        target.removeClass('expand').addClass('collapse');
        
        if (target.parent().children('.expansion').length) {
            target.parent().children('.expansion').slideDown(effectTime);
        } else {
            var expansion = $('<div class="expansion" style="font-size:100%"></div>');
            target.parent().append(expansion);
            var url    = urlBase + 'view/';
            var params = 'r=' + encodeURIComponent(resourceUri);
            $.ajax({
                url:      url, 
                data:     params, 
                dataType: 'html', 
                success:  function(content) {
                    expansion.hide();
                    expansion.append(content);
                    expansion.slideDown(effectTime);
                    /*map.updateInfoWindow([
                        new GInfoWindowTab('', target.parent().html())
                    ])*/ // only a javascript error, i think it was neccessery for the old ontowiki
                }
            });
        }
    } else {
        target.removeClass('collapse').addClass('expand');
        target.parent().find('.expansion').slideUp(effectTime);
    }
}

function expand(event) {
    target = $(event.target);
    resourceURI = target.next().attr('about');
    encodedResourceURI = encodeURIComponent(resourceURI);
    resource = target.next();

    if (target.is('.expand')) {
        target.removeClass('expand').addClass('deexpand');
        // target.next().after('<div class="is-processing expanded-content"></div>');
        url = urlBase + 'resource/properties/';
        params = 'r=' + encodedResourceURI;
        $.ajax({
            url: url,
            data: params,
            dataType: 'html',
            // success: function(msg){alert( 'Data Saved: ' + msg );}
            success: function(content) {
                map.updateInfoWindow([new GInfoWindowTab('', target.parent().html() + '<div style="font-size:90%">' + content + '</div>')]);
                // resource.next().html(content);
                // resource.next().removeClass('is-processing');
                }
        });
    }
    else if (target.is('.deexpand')) {
        target.removeClass('deexpand').addClass('expand');
        target.next().next().remove();
    }
}

/**
 * Changes the Ratio between main and side-section
 */
function setSectionRatio(x) {
    $('div.section-sidewindows').css('width', x + '%');
    $('div.section-mainwindows').css('width', (100 - x) + '%');
    $('div.section-mainwindows').css('margin-left', x + '%');
}

function showWindowMenu(event) {
    // remove all other menus
    $('.contextmenu-enhanced .contextmenu').remove();
    
    menuX  = event.pageX - 11;
    menuY  = event.pageY - 11;
    menuId = 'windowmenu-' + menuX + '-' + menuY;

    // create the plain menu with correct style and position
    $('.contextmenu-enhanced').append('<div class="contextmenu is-processing" id="' + menuId + '"></div>');
    $('#' + menuId)
        .attr({style: 'z-index: ' + menuZIndex + '; top: ' + menuY + 'px; left: ' + menuX + 'px;'})
        .click(function(event) {event.stopPropagation();});

    $('#' + menuId).fadeIn();

    // setting url parameters
    var urlParams = {};
    urlParams.module = $(event.target).parents('.window').eq(0).attr('id');

    // load menu with specific options from service
    $.ajax({
        type: "GET",
        url: urlBase + 'service/menu/',
        data: urlParams,
        error: function (XMLHttpRequest, textStatus, errorThrown) {
            alert("error occured - details at firebug-console");
            console.log("menu service error\nfailure message:\n" + textStatus);
            $('#' + menuId).fadeOut();
        },
        success: function(data, textStatus) {
            try {

                menuData = $.evalJSON(data);

                var menuStr = '';
                var tempStr = '';
                var href    = '';

                // construct menu content
                for (var key in menuData) {
                    if ( menuData[key] == '_---_' ) {
                        menuStr += '</ul><hr/><ul>';
                    } else {
                        if ( typeof(menuData[key]) == 'string' ) {
                            tempStr = '<a href="' + menuData[key] + '">' + key + '</a>';
                        } else {
                            tempStr = '<a ';
                            for (var attr in menuData[key]) {
                                tempStr += attr + '="' + menuData[key][attr] + '" ';
                            }
                            tempStr += '>' + key + '</a>';
                        }
                        menuStr += '<li>' + tempStr + '</li>';
                    }
                }

                // append menu string with surrounding list
                $('#' + menuId).append('<ul>' + menuStr + '</ul>');

                // remove is-processing
                $('#' + menuId).toggleClass('is-processing');

            } catch (e) {
                alert("error occured - details at firebug-console");
                console.log("menu service error\nmenu service replied:\n" + data);
                $('#' + menuId).fadeOut();
            }

        }
    });

    // prevent href trigger
    event.stopPropagation();

}

/*
 * Save a key-value pair via ajax
 */
function sessionStore(name, value, options) {
    var defaultOptions = {
        encode:    false, 
        namespace: _OWSESSION, 
        callback:  null, 
        method:    'set', 
        url:       urlBase + 'service/session/', 
        withValue: false
    };
    var config = $.extend(defaultOptions, options);

    // TODO
    if (!config.encode) {
        if (!config.withValue) {
            config.url += '?name=' + name + '&value=' + value + '&method=' + config.method + '&namespace=' + config.namespace;
        } else {
            config.url += '?name=' + name + '&' + value + '&method=' + config.method + '&namespace=' + config.namespace;
        }

        $.get(config.url, config.callback);
    } else {
        var params = {name: name, value: value, namespace: config.namespace};
        $.get(config.url, params, config.callback);
    }
}

/*
 * This function sets an automatic id attribute if no id exists
 * parameter: el -> jquery element
 */
function setAutoId(element) {
    if (!element.attr('id')) {
        element.attr('id', 'autoid' + idCounter++);
    }
}

/*
 * hide a href by putting this attribute into an array
 * parameter: el -> jquery element
 */
function hideHref(element) {
    setAutoId(element);
    
    if (element.attr('href')) {
        tempHrefs[element.attr('id')] = element.attr('href');
        element.removeAttr('href');
    }
}

function showHref(element) {
    if (tempHrefs[element.attr('id')]) {
        element.attr('href', tempHrefs[element.attr('id')]);
    }
}

function serializeArray(array, key)
{
    if (typeof key == 'undefined') {
        key = 'value';
    }
    
    var serialization = '';
    
    if (array.length) {
        serialization += key + '[]=' + encodeURIComponent(array[0]);
        
        for (var i = 1; i < array.length; ++i) {
            serialization += '&' + key + '[]=' + encodeURIComponent(array[i]);
        }
    } else {
        serialization += key + '=';
    }
    
    return serialization;
}

/*
 * remove all other menus
 */
function removeResourceMenus() {
    $('.contextmenu-enhanced .contextmenu').remove();
}

function showAddInstanceMenu(event, menuData) {
    // remove all other menus
    removeResourceMenus();

    var pos = $('.init-resource').offset();
    menuX = pos.left - $('.init-resource').innerWidth() + 4;
    menuY = pos.top + $('.init-resource').outerHeight();
    menuId = 'windowmenu-' + menuX.toFixed() + '-' + menuY.toFixed();

    // create the plain menu with correct style and position
    $('.contextmenu-enhanced').append('<div class="contextmenu is-processing" id="' + menuId + '"></div>');
    $('#' + menuId)
        .css({ 
          'z-index': menuZIndex,
          'top': menuY + 'px',
          'left': menuX + 'px'
        })
        .click(function(event) {event.stopPropagation();})
        .fadeIn();

    var tempMenu = "";
    for (var key in menuData) {
        var label = menuData[key]['http://www.w3.org/2000/01/rdf-schema#label'][0].value;
        tempMenu += '<li><a href="javascript:createInstanceFromClassURI(\'' + key + '\');">' + label + '</a></li>'
    }
    // append menu
    // console.log(tempMenu);
    $('#' + menuId).append('<ul>' + tempMenu + '</ul>');
    // remove is-processing
    $('#' + menuId).toggleClass('is-processing');
    // repositioning
    menuX = pos.left - $('#' + menuId).innerWidth() + $('.init-resource').outerWidth();
    menuY = pos.top + $('.init-resource').outerHeight();
    
    // set new position
    $('#' + menuId).css({ top: menuY + 'px', left: menuX + 'px'});

    // remove is-processing
    $('#' + menuId).removeClass("is-processing");
    // prevent href trigger
    event.stopPropagation();

}

function showResourceMenu(event, json) {
    // remove all other menus
    removeResourceMenus();
    
    menuX  = event.pageX - 30;
    menuY  = event.pageY - 20;
    menuId = 'windowmenu-' + menuX + '-' + menuY;
    
    // create the plain menu with correct style and position
    $('.contextmenu-enhanced').append('<div class="contextmenu is-processing" id="' + menuId + '"></div>');
    $('#' + menuId)
        .attr({style: 'z-index: ' + menuZIndex + '; top: ' + menuY + 'px; left: ' + menuX + 'px;'})
        .click(function(event) {event.stopPropagation();});

    $('#' + menuId).fadeIn();
    
    parentHref = tempHrefs[$(event.target).parent().attr('id')];
    
    function onJSON(menuData, textStatus) {
        try {
            //console.log(menuData)
            var menuStr = '';
            var tempStr = '';
            var href    = '';

            // construct menu content
            for (var key in menuData) {
                href = menuData[key];
                if ( menuData[key] == '_---_' ) {
                    menuStr += '</ul><hr/><ul>';
                } else {
                    if (typeof(href) == 'object') {
                        tempStr = '<a class="' + href['class'] + '" about="' + href['about'] + '">' + key + '</a>';
                    } else {
                        tempStr = '<a href="' + href + '">' + key + '</a>';
                        if (href == parentHref) {
                            tempStr = '<strong>' + tempStr + '</strong>';
                        }
                    }
                    menuStr += '<li>' + tempStr + '</li>';
                }
            }

            // append menu string with surrounding list
            $('#' + menuId).append('<ul>' + menuStr + '</ul>');

            // remove is-processing
            $('#' + menuId).toggleClass('is-processing');

        } catch (e) {
            alert("error occured - details at firebug-console");
            console.log("menu service error\nmenu service replied:\n" + data);
            $('#' + menuId).fadeOut();
        }
    }

    if(json == undefined){
        var aboutUri, modelUri, resourceUri;

        // URI of the resource clicked (used attribute can be about and resource)
        if ( typeof $(event.target).parent().attr('about') != 'undefined' ) {
            aboutUri = $(event.target).parent().attr('about');
        } else if ( typeof $(event.target).parent().attr('resource') != 'undefined' ) {
            aboutUri = $(event.target).parent().attr('resource');
        }

        if (aboutUri == null) {
            // no usable resource uri, so we exit here
            return false;
        } else if ($(event.target).parent().hasClass('Model')) {
            modelUri = aboutUri;
        } else {
            resourceUri = aboutUri;
        }

        var urlParams = {};
        if (modelUri != null) {
            urlParams.model = modelUri;
        } else {
            urlParams.resource = resourceUri;
        }

        // load menu with specific options from service
        $.ajax({
            type: "GET",
            url: urlBase + 'service/menu/',
            data: urlParams,
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                alert("error occured - details at firebug-console");
                console.log("menu service error\nfailure message:\n" + textStatus);
                $('#' + menuId).fadeOut();
            },
            success: function(data, textStatus){onJSON($.evalJSON(data), textStatus);}
        });
    } else {
        onJSON(json)
    }

    // prevent href trigger
    event.stopPropagation();
}

/**
 * Loads RDFauthor if necessary and executes callback afterwards.
 */
function loadRDFauthor(callback) {
    var loaderURI = RDFAUTHOR_BASE + 'src/rdfauthor.js';
    
    if ($('head').children('script[src="' + loaderURI + '"]').length > 0) {
        callback();
    } else {
        RDFAUTHOR_READY_CALLBACK = callback;
        // load script
        var s = document.createElement('script');
        s.type = 'text/javascript';
        s.src = loaderURI;
        document.getElementsByTagName('head')[0].appendChild(s);
    }
}

function populateRDFauthor(data, protect, resource, graph, workingmode) {
    /*
     * Set default values
     */
    protect  = arguments.length >= 2 ? protect : true;
    resource = arguments.length >= 3 ? resource : null;
    graph    = arguments.length >= 4 ? graph : null;

    var currentSubject;
    for (currentSubject in data) {
        break;
    }
    var fullDataSet = $.extend({}, data[currentSubject], RDFAUTHOR_DATATYPES_FIX_ADDITIONAL_DATA);
    RDFAUTHOR_DATATYPES_FIX[currentSubject] = fullDataSet;

if(window.RDFAUTHOR_START_FIX != undefined) {
    if (RDFAUTHOR_START_FIX == "editSingleTerm") {
        var reduced = {};
        reduced[currentSubject] = {};
        reduced[currentSubject][EDIT_SINGLE_PROPERTY] = RDFAUTHOR_DATATYPES_FIX[currentSubject][EDIT_SINGLE_PROPERTY];
        data = reduced;
    }
    if (RDFAUTHOR_START_FIX == "addProperty") {
        var reduced = {};
        reduced[currentSubject] = {};
        reduced[currentSubject][EDIT_SINGLE_PROPERTY] = RDFAUTHOR_DATATYPES_FIX[currentSubject][EDIT_SINGLE_PROPERTY];
        data = reduced;
        RDFAUTHOR_DISPLAY_FIX.push(EDIT_SINGLE_PROPERTY);
    }
    if (RDFAUTHOR_START_FIX == "editMode") {
        if($.inArray("http://www.w3.org/1999/02/22-rdf-syntax-ns#type", RDFAUTHOR_DISPLAY_FIX) !== -1){
            RDFAUTHOR_DISPLAY_FIX.splice( $.inArray("http://www.w3.org/1999/02/22-rdf-syntax-ns#type", RDFAUTHOR_DISPLAY_FIX), 1 );
        }
    }
    if (RDFAUTHOR_START_FIX == "newResource") {
        if($.inArray("http://www.w3.org/1999/02/22-rdf-syntax-ns#type", RDFAUTHOR_DISPLAY_FIX == -1)){
            RDFAUTHOR_DISPLAY_FIX.push("http://www.w3.org/1999/02/22-rdf-syntax-ns#type");
        }
    }
}

    for (var currentSubject in data) {
        for (var currentProperty in data[currentSubject]) {
            if($.inArray(currentProperty, RDFAUTHOR_DISPLAY_FIX) !== -1 || RDFAUTHOR_START_FIX == "addProperty") {
                var objects = data[currentSubject][currentProperty];
                if(objects == undefined){
                    return;
                }
                for (var i = 0; i < objects.length; i++) {
                var objSpec = objects[i];
                    //the old way
                    if(objSpec.range == undefined) {
                        if (objSpec.type == 'uri') {
                            var value = '<' + objSpec.value + '>';
                        } else if (objSpec.type == 'bnode') {
                            var value = '_:' + objSpec.value;
                        } else {
                            // IE fix, object keys with empty strings are removed
                            var value = objSpec.value ? objSpec.value : "";
                        }
                        var newObjectSpec = {
                            value: value,
                            type: String(objSpec.type).replace('typed-', '')
                        }
                        if (newObjectSpec.value) {
                            if (newObjectSpec.type == 'literal') {
                                newObjectSpec.options = {
                                    datatype: objSpec.datatype
                                }
                            }
                            if (objSpec.lang) {
                                newObjectSpec.options = {
                                    lang: objSpec.lang
                                }
                            }
                        }
                    }else{
                        //the new way
                        var value = objSpec.value ? objSpec.value : "";
                        var newObjectSpec = {
                            value: value,
                            type: String(objSpec.range[0]).replace('typed-', '')
                        }

                    }

                var stmt = new Statement({
                    subject: '<' + currentSubject + '>',
                    predicate: '<' + currentProperty + '>',
                    object: newObjectSpec
                }, {
                    graph: graph, 
                    title: objSpec.title, 
                    protected: protect ? true : false, 
                    hidden: objSpec.hidden ? objSpec.hidden : false
                });


                if (workingmode == 'class' || RDFAUTHOR_START_FIX == "addProperty") {
                    // remove all values except for type
                    if ( stmt.predicateURI() !== 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type' ) {
                        stmt._object.value = "";
                    } else {
                        stmt._hidden = true;
                    }
                }
                if((RDFAUTHOR_START_FIX != "editMode" && RDFAUTHOR_START_FIX != "editSingleTerm") || (objSpec.type[0] != "http://www.w3.org/2002/07/owl#DataTypeProperty" && objSpec.type[0] != "http://www.w3.org/2002/07/owl#DatatypeProperty")){
                    RDFauthor.addStatement(stmt);
                }

                }
            }
        }
    }
}

/*
 * get the rdfa init description from the service in class mode and start the
 * RDFauthor window
 * dataCallback is called right after the json request to manipulate the requested data
 */
function createInstanceFromClassURI(type, dataCallback) {
    var serviceUri = urlBase + 'service/rdfauthorinit';

    // check if an resource is in editing mode
    if(typeof RDFAUTHOR_STATUS != 'undefined') {
        if(RDFAUTHOR_STATUS === 'active') {
            alert("Please finish all other editing actions before creating a new instance.");
            return;
            //RDFauthor.cancel();
            //RDFauthor.reset();
        }
    }

    // remove resource menus
    removeResourceMenus();

    loadRDFauthor(function() {
        $.getJSON(serviceUri, {
            mode: 'class',
            uri: type
        }, function(data) {
            if (data.hasOwnProperty('propertyOrder')) {
                var propertyOrder = data.propertyOrder;
                delete data.propertyOrder;
            }
            else {
                var propertyOrder = null;
            }
            // pass data through callback
            if (typeof dataCallback == 'function') {
                data = dataCallback(data);
            }
            var addPropertyValues = data['addPropertyValues'];
            var addOptionalPropertyValues = data['addOptionalPropertyValues'];
            RDFAUTHOR_DISPLAY_FIX = Object.keys(data['addPropertyValues']);
            RDFAUTHOR_DATATYPES_FIX_ADDITIONAL_DATA = data['additionalData'];
            delete data.addPropertyValues;
            delete data.addOptionalPropertyValues;
            delete data.additionalData;
            delete data.displayProperties;

            // get default resource uri for subjects in added statements (issue 673)
            // grab first object key
            for (var subjectUri in data) {break;};
            // add statements to RDFauthor
            populateRDFauthor(data, true, subjectUri, selectedGraph.URI, 'class');
            RDFauthor.setOptions({
                saveButtonTitle: 'Create Resource',
                cancelButtonTitle: 'Cancel',
                title: ['createNewInstanceOf', type],
                autoParse: false, 
                showPropertyButton: true,
                loadOwStylesheet: false,
                addPropertyValues: addPropertyValues,
                addOptionalPropertyValues: addOptionalPropertyValues,
                onSubmitSuccess: function (responseData) {
                    var newLocation;
                    if (responseData && responseData.changed) {
                        newLocation = resourceURL(responseData.changed);
                    } else {
                        newLocation = window.location.href;
                    }
                    // HACK: reload whole page after 500 ms
                    window.setTimeout(function () {
                        window.location.href = newLocation;
                    }, 500);
                },
                onCancel: function () {
                    // HACK: reload whole page after 500 ms
                    window.setTimeout(function () {
                        window.location.href = window.location.href;
                    }, 500);
                }
            });
           
            var options = {};
            if (propertyOrder != null) {
                options.propertyOrder = propertyOrder;
            }
			options.workingMode = 'class';
            RDFauthor.start(null, options);
        })
    });
}

/*
 * get the rdfauthor init description from the service in and start the RDFauthor window
 */
function editResourceFromURI(resource) {
    var serviceUri = urlBase + 'service/rdfauthorinit';

    // remove resource menus
    removeResourceMenus();

    loadRDFauthor(function() {
        $.getJSON(serviceUri, {
           mode: 'edit',
           uri: resource
        }, function(data) {
            var addPropertyValues = data['addPropertyValues'];
            var addOptionalPropertyValues = data['addOptionalPropertyValues'];
            RDFAUTHOR_DISPLAY_FIX = Object.keys(data['addPropertyValues']);
            RDFAUTHOR_DATATYPES_FIX_ADDITIONAL_DATA = data['additionalData'];
            delete data.addPropertyValues;
            delete data.addOptionalPropertyValues;
			if (data.hasOwnProperty('propertyOrder')) {
                var propertyOrder = data.propertyOrder;
                delete data.propertyOrder;
            }
            else {
                var propertyOrder = null;
            }
            
            // get default resource uri for subjects in added statements (issue 673)
            // grab first object key
            for (var subjectUri in data) {break;};

            // add statements to RDFauthor
            populateRDFauthor(data, false, resource, selectedGraph.URI);

            RDFauthor.setOptions({
                saveButtonTitle: 'Save Changes',
                cancelButtonTitle: 'Cancel',
                title: ['editResource', resource],
                autoParse: false, 
                showPropertyButton: true,
                loadOwStylesheet: false,
                addPropertyValues: addPropertyValues,
                addOptionalPropertyValues: addOptionalPropertyValues,
                onSubmitSuccess: function () {
                    // HACK: reload whole page after 500 ms
                    /*
                    window.setTimeout(function () {
                        window.location.href = window.location.href;
                    }, 500);
                    */
                },
                onCancel: function () {
                    // HACK: reload whole page after 500 ms
                    window.setTimeout(function () {
                        window.location.href = window.location.href;
                    }, 500);
                }
            });

            var options = {};
            if (propertyOrder != null) {
                options.propertyOrder = propertyOrder;
            }
			options.workingMode = 'class';
            RDFauthor.start(null, options);
        })
    });
}

/**
 * Creates a new internal OntoWiki URL for the given resource URI.
 * @return string
 */
function resourceURL(resourceURI) {
    if (resourceURI.indexOf(urlBase) === 0) {
        // URL base is a prefix of requested resource URL
        return resourceURI;
    }

    return urlBase + 'view/?r=' + encodeURIComponent(resourceURI);
}

/**
 * Starts RDFauthor in inline mode to edit a single property
 *
 * @param event the JavaScript event which startes the method
 */
function editProperty(event) {
    var element = $.event.fix(event).target;
    var t2 = $(element.closest("td"));
    var t3 = t2.find("li");
    var t4 = $(t3[0]);
    var t5 = t4.attr("property");

    if(t5 == undefined){
        var t6 = t4.find("a");
        var t5 = t6.attr("rel");
    }

    RDFAUTHOR_START_FIX = "editSingleTerm";
    EDIT_SINGLE_PROPERTY = t5;

    $('.toolbar a.save').removeClass('hidden');
    $('.toolbar a.cancel').removeClass('hidden');

    $('.contextmenu').css("display", "none");
    loadRDFauthor(function () {
        var serviceURI = urlBase + 'service/rdfauthorinit';
        var prototypeResource = selectedResource.URI;
        RDFauthor.reset();

        $.getJSON(serviceURI, {
            mode: 'edit',
            uri: prototypeResource
        }, function(data) {
            if (data.hasOwnProperty('propertyOrder')) {
                var propertyOrder = data.propertyOrder;
                delete data.propertyOrder;
            }
            else {
                var propertyOrder = null;
            }

            var addPropertyValues = data['addPropertyValues'];
            var addOptionalPropertyValues = data['addOptionalPropertyValues'];
            RDFAUTHOR_DISPLAY_FIX = data['displayProperties'];
            RDFAUTHOR_DATATYPES_FIX_ADDITIONAL_DATA = data['additionalData'];
            delete data['addPropertyValues'];
            delete data['addOptionalPropertyValues'];
            delete data.additionalData;
            delete data.displayProperties;
            // get default resource uri for subjects in added statements (issue 673)
            // grab first object key
            for (var subjectUri in data) {
                break;
            }
            ;

            populateRDFauthor(data, true, subjectUri, selectedGraph.URI);
            RDFauthor.setOptions({
                saveButtonTitle: 'Save Changes',
                cancelButtonTitle: 'Cancel',
                title: $('.section-mainwindows .window').eq(0).children('.title').eq(0).text(),
                loadOwStylesheet: false,
                onSubmitSuccess: function () {
                    $('.edit').each(function () {
                        $(this).fadeOut(effectTime);
                    });
                    $('.edit-enable').removeClass('active');

                    // HACK: reload whole page after 1000 ms
                    /*
                     window.setTimeout(function () {
                     window.location.href = window.location.href;
                     }, 1000);
                     */
                },
                onCancel: function () {
                    $('.edit').each(function () {
                        $(this).fadeOut(effectTime);
                    });
                    $('.edit-enable').removeClass('active');
                },
                viewOptions: {
                    type: RDFAUTHOR_VIEW_MODE,
                    container: function (statement) {
                        var element = RDFauthor.elementForStatement(statement);
                        var parent = $(element).closest('div');

                        if (!parent.hasClass('ontowiki-processed')) {
                            parent.children().each(function () {
                                $(this).hide();
                            });
                            parent.addClass('ontowiki-processed');
                        }

                        return parent.get(0);
                    }
                }
            });

            RDFauthor.start($(element).closest('td'));
        });
        $('.edit-enable').addClass('active');
        $('.edit').each(function() {
            var button = this;
            $(this).fadeIn(effectTime);
        });
    });
}

/**
 * Starts RDFauthor in overlay mode to edit a single property in the listview table
 *
 * @param event the JavaScript event which startes the method
 */
function editPropertyListmode(event) {
    var element = $.event.fix(event).target;
    var resource = $(element).parents('td').rdf().where('?s ?p ?o').dump();
    var resourceUri = Object.keys(resource)[0];
    var serviceUri = urlBase + 'service/rdfauthorinit';

    // remove resource menus
    removeResourceMenus();

    loadRDFauthor(function() {
        // add statements to RDFauthor
        populateRDFauthor(resource, false, resource, selectedGraph.URI);

        RDFauthor.setOptions({
            saveButtonTitle: 'Save Changes',
            cancelButtonTitle: 'Cancel',
            title: 'Edit Resource ' + resourceUri,
            autoParse: false, 
            showPropertyButton: false,
            loadOwStylesheet: false,
            onSubmitSuccess: function () {
                // HACK: reload whole page after 500 ms
                /*
                window.setTimeout(function () {
                    window.location.href = window.location.href;
                }, 500);
                */
            }
        });

        RDFauthor.start();
    });
}

function addProperty() {

    
    var selectorOptions = {

        container: $('body'),
        selectionCallback: function (uri, label, datatype) {
            var ID = RDFauthor.nextID();
            var td1ID = 'rdfauthor-property-selector-' + ID;
            var td2ID = 'rdfauthor-property-widget-' + ID;

            $('.edit').each(function() {
                $(this).fadeIn(effectTime);
            });

            $('table.rdfa')
                .removeClass('hidden')
                .show()
                .children('tbody')
                .prepend('<tr><td colspan="2" width="120"><div style="width:75%" id="' + td1ID + '"></div></td></tr>');

            $('table.rdfa').parent().find('p.messagebox').hide();
            var statement;

            if (datatype != undefined) {
                statement = new Statement({
                    subject: '<' + RDFAUTHOR_DEFAULT_SUBJECT + '>',
                    predicate: '<' + uri + '>',
                    object: {
                        value: defaultValueForSchemaType(datatype),
                        options: {
                           datatype: datatype
                        }
                    }
                }, {
                    title: label,
                    graph: RDFAUTHOR_DEFAULT_GRAPH
                });
                statement._object.value = '';
            }
            else {
                statement = new Statement({
                    subject: '<' + RDFAUTHOR_DEFAULT_SUBJECT + '>',
                    predicate: '<' + uri + '>'
                }, {
                    title: label,
                    graph: RDFAUTHOR_DEFAULT_GRAPH
                });
            }

            var owURL = urlBase + 'view?r=' + encodeURIComponent(uri);
            $('#' + td1ID).closest('td')
                .attr('colspan', '1')
                .html('<a class="hasMenu" about="' + uri + '" href="' + owURL + '">' + label + '</a>')
                .after('<td id="' + td2ID + '"></td>');
            RDFauthor.getView().addWidget(statement, null, {container: $('#' + td2ID), activate: true});
        }
    };
    
    var selector = new Selector(RDFAUTHOR_DEFAULT_GRAPH, RDFAUTHOR_DEFAULT_SUBJECT, selectorOptions);
    selector.presentInContainer();
}

function defaultValueForSchemaType(schematype){
    switch (schematype){
        case 'http://www.w3.org/2001/XMLSchema#integer':
            return '1';
        case 'http://www.w3.org/2001/XMLSchema#string':
            return '1';
        case 'http://www.w3.org/2001/XMLSchema#decimal':
            return '1';
        case 'http://www.w3.org/2001/XMLSchema#float':
            return '1';
        case 'http://www.w3.org/2001/XMLSchema#boolean':
            return 'true';
        case 'http://www.w3.org/2001/XMLSchema#date':
            return '2002-09-24';
        case 'http://www.w3.org/2001/XMLSchema#time':
            return '09:00:00';
        default :
            return '';


    }
}