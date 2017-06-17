'use strict';


/**
 * Resolve the name of the specified jquery-tableSorter theme.
 *
 * @param  mixed element - object or object id identifying the theme stylesheet
 * 
 * @return string - theme name
 */
function getTableSorterTheme(element) {
    var link, sheet, name, type=rs.getType(element);

    if (type == 'CSSStyleSheet') {
        sheet = element;
    }
    else {
        if (type == 'string') {
            if (!element.startsWith('#')) element = '#'+ element; 
            element = $(element);
        }               
        if (element.jquery)                 link = element.get(0);
        else if (type == 'HTMLLinkElement') link = element;
        if (link && link.sheet) sheet = link.sheet;
    }
    
    if (sheet) {
        var path = $('<a>', {href: sheet.href})[0].pathname;
        var match = path.match(/theme\.(.+?)(\.min)?\.css$/i);
        if (match) name = match[1];
    }
    return name;
}
