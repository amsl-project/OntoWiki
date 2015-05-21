var __i18n = {

    'Cancel' : {
        'en' : 'Cancel',
        'de' : 'Abbrechen'
    },

    'Test' : {
        'en' : 'en',
        'de' : 'de'
    },

    'Delete' : {
        'en' : 'Delete Knowledgebase',
        'de' : 'Wissensbasis löschen'
    },

    'is' : {
        'en' : 'is',
        'de' : 'ist'
    },

    'from' : {
        'en' : 'from',
        'de' : 'aus'
    },

    'no results' : {
        'en' : 'No results found.',
        'de' : 'Keine Resultate gefunden.'
    },

    'press enter' : {
        'en' : 'Press enter to start the advanced search',
        'de' : 'Zum Start der erweiterten Suche Eingabetaste drücken'
    },

    'empty message' : {
        'en' : 'Maximal 7 results are shown. Press Enter to see all.',
        'de' : 'Es werden maximal 7 Resultate angezeigt. Drücken Sie Enter um alle zu sehen.'
    }
};

var _translate = function (key) {
    var args = [];
    if (Object.prototype.toString.call(key) === '[object Array]') {
       args = key.slice(1);
       key = key[0];
    }

    if(key in __i18n && RDFAUTHOR_LANGUAGE in __i18n[key]) {
        var translation = __i18n[key][RDFAUTHOR_LANGUAGE];
        for(var i = 0; i < args.length; i++) {
            translation = translation.replace('%' + (i+1), args[i]);
        }
        return translation;
    }
    else {
        return key;
    }
};

