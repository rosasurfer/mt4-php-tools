'use strict';

/**
 * Polyfill and extend objects.
 */
if (!Array.isArray            ) Array.isArray             = function isArray   (/*mixed*/    arg)         { return Object.prototype.toString.call(arg) === '[object Array]'; };
if (!Array.prototype.forEach  ) Array.prototype.forEach   = function forEach   (/*function*/ func, scope) { for (var i=0, len=this.length; i < len; ++i) func.call(scope, this[i], i, this); }

if (!Date.prototype.addDays   ) Date.prototype.addDays    = function addDays   (/*int*/ days)    { this.setTime(this.getTime() + (days*24*60*60*1000)); return this; }
if (!Date.prototype.addHours  ) Date.prototype.addHours   = function addHours  (/*int*/ hours)   { this.setTime(this.getTime() + (  hours*60*60*1000)); return this; }
if (!Date.prototype.addMinutes) Date.prototype.addMinutes = function addMinutes(/*int*/ minutes) { this.setTime(this.getTime() + (   minutes*60*1000)); return this; }
if (!Date.prototype.addSeconds) Date.prototype.addSeconds = function addSeconds(/*int*/ seconds) { this.setTime(this.getTime() + (      seconds*1000)); return this; }

if (!String.prototype.capitalize     ) String.prototype.capitalize      = function capitalize     ()                  { return this.charAt(0).toUpperCase() + this.slice(1); }
if (!String.prototype.capitalizeWords) String.prototype.capitalizeWords = function capitalizeWords()                  { return this.replace(/\w\S*/g, function(word) { return word.capitalize(); }); }
if (!String.prototype.decodeEntities ) String.prototype.decodeEntities  = function decodeEntities ()                  { if (!String.prototype.decodeEntities.textarea) /*static*/ String.prototype.decodeEntities.textarea = document.createElement('textarea'); String.prototype.decodeEntities.textarea.innerHTML = this; return String.prototype.decodeEntities.textarea.value; }
if (!String.prototype.trim           ) String.prototype.trim            = function trim           ()                  { return this.replace(/(^\s+)|(\s+$)/g, ''); }
if (!String.prototype.startsWith     ) String.prototype.startsWith      = function startsWith     (/*string*/ prefix) { return (this.indexOf(prefix) === 0); }
if (!String.prototype.contains       ) String.prototype.contains        = function contains       (/*string*/ string) { return (this.indexOf(string) != -1); }
if (!String.prototype.endsWith       ) String.prototype.endsWith        = function endsWith       (/*string*/ suffix) { var pos = this.lastIndexOf(suffix); return (pos!=-1 && this.length==pos+suffix.length); }
if (!String.prototype.repeat         ) String.prototype.repeat          = function repeat         (/*int*/    count)  {
    if (count < 0)                    throw new RangeError('repeat count must be non-negative');
    if (count == Infinity)            throw new RangeError('repeat count must be less than infinity');
    count = Math.floor(count);
    if (this.length * count >= 1<<28) throw new RangeError('repeat count must not overflow maximum string size');
    var result = '';
    while (count--)
        result += this;
    return result;
}

// fix broken Internet Explorer substr()
if ('ab'.substr(-1) != 'b') {
    String.prototype.substr = function substr(start, length) {
        var from = start;
            if (from < 0) from += this.length;
            if (from < 0) from = 0;
        var to = length===undefined ? this.length : from+length;
            if (from > to) to = from;
        return this.substring(from, to);
    }
}

// fix inaccurate Number.toFixed()
(function() {
    /**
     * Decimal adjustment of a number.
     *
     * @param  string type  - type of adjustment
     * @param  number value - number
     * @param  int    exp   - exponent (the 10 logarithm of the adjustment base)
     *
     * @return number - adjusted value
     *
     * @see    https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Math/round#Example:_Decimal_rounding
     */
    function decimalAdjust(type, value, exp) {
        // if the exp is undefined or zero...
        if (exp===undefined || +exp===0) {
            return Math[type](value);
        }
        value = +value;
        exp   = +exp;
        // if the value is not a number or the exp is not an integer...
        if (isNaN(value) || typeof(exp)!='number' || exp%1===0) {
            return NaN;
        }
        // shift
        value = value.toString().split('e');
        value = Math[type](+(value[0] +'e'+ (value[1] ? (+value[1]-exp) : -exp)));
        // shift back
        value = value.toString().split('e');
        return +(value[0] +'e'+ (value[1] ? (+value[1]+exp) : exp));
    }

    // decimal round, floor and ceil
    if (!Math.round10) Math.round10 = function round10(value, exp) { return decimalAdjust('round', value, exp); };
    if (!Math.floor10) Math.floor10 = function floor10(value, exp) { return decimalAdjust('floor', value, exp); };
    if (!Math.ceil10)  Math.ceil10  = function ceil10 (value, exp) { return decimalAdjust('ceil',  value, exp); };

    Number.prototype.toFixed10 = function toFixed10(precision) {
        return Math.round10(this, -precision).toFixed(precision);
    }
})();


/**
 * Namespace
 */
var rosasurfer = {


    /**
     * Get an object holding all query parameters. Array parameters are supported for the first dimension.
     *
     * @param  string url [optional] - URL to get query parameters from (default: the current page location)
     *
     * @return array - {key1: value1, key2: value2, ..., keyN: valueN}
     */
    getQueryParameters: function getQueryParameters(url) {
        var pos, query;
        if (url===undefined) query = location.search;
        else                 query = ((pos=url.indexOf('?'))==-1) ? '' : url.substr(pos);

        var result={}, pairs=query.slice(1).replace(/\+/g, ' ').split('&'), reArray=/^([^[]+)\[(.*)\]$/, matches, lengths={};

        pairs.forEach(function(pair) {
            var name, value, values=pair.split('=');                       // Unlike PHP the JavaScript function split(str, limit)
                                                                           // discards additional occurrences.
            if (values.length > 1) {
                name  = decodeURIComponent(values.shift());
                value = decodeURIComponent(values.join('='));

                if (name.contains('[') && (matches = name.match(reArray))) {
                    var array = matches[1];
                    var key = matches[2];
                    if (typeof(result[array]) != 'object')
                        result[array] = {};
                    if (!key.length)        Array.prototype.push.call(result[array], value);
                    else if (key=='length') lengths[array] = value;        // backup length parameter to not confuse Array.push()
                    else                    result[array][key] = value;
                }
                else result[name] = value;
            }
        });

        Object.keys(result).forEach(function(key) {
            if (typeof(result[key]) == 'object') {
               delete result[key].length;                                  // delete length property defined by Array.push()
               if (lengths[key] !== undefined)
                  result[key].length = lengths[key];                       // restore a backed-up length parameter
            }
        });
        return result;
    },


    /**
     * Get a single query parameter value.
     *
     * @param  string name           - parameter name
     * @param  string url [optional] - URL to get a query parameter from (default: the current page location)
     *
     * @return string - value or undefined if the parameter doesn't exist in the query string
     */
    getQueryParameter: function getQueryParameter(name, url) {
        if (name===undefined) return alert('rosasurfer.getQueryParameter()\n\nUndefined parameter "name"');
        return this.getQueryParameters(url)[name];
    },


    /**
     * Whether or not a parameter exists in the query string.
     *
     * @param  string name           - parameter name
     * @param  string url [optional] - URL to check for the query parameter (default: the current page location)
     *
     * @return bool
     */
    isQueryParameter: function isQueryParameter(name, url) {
        if (name===undefined) return alert('rosasurfer.isQueryParameter()\n\nUndefined parameter "name"');
        return this.getQueryParameters(url)[name] !== undefined;
    },


    /**
     * Load a url via GET and pass the response to the specified callback function.
     *
     * @param string   url      - url to load
     * @param function callback - callback function
     */
    getUrl: function getUrl(url, callback) {                   // request.readyState = returns the status of the XMLHttpRequest
        var request = new XMLHttpRequest();                    //  0: request not initialized
        request.url = url;                                     //  1: server connection established
        request.onreadystatechange = function() {              //  2: request received
            if (request.readyState == 4) {                     //  3: processing request
                callback(request);                             //  4: request finished and response is ready
            }                                                  //
        };                                                     // request.status = returns the HTTP status-code
        request.open('GET', url , true);                       //  200: "OK"
        request.send(null);                                    //  404: "Not Found" etc.
    },


    /**
     * Load a url via POST and pass the response to the specified callback function.
     *
     * @param string   url      - url to load
     * @param string   data     - content to send in the request's body (i.e. POST parameter)
     * @param object   headers  - additional request header
     * @param function callback - callback function
     */
    postUrl: function postUrl(url, data, headers, callback) {  // request.readyState = returns the status of the XMLHttpRequest
        var request = new XMLHttpRequest();                    //  0: request not initialized
        request.url = url;                                     //  1: server connection established
        request.onreadystatechange = function() {              //  2: request received
            if (request.readyState == 4) {                     //  3: processing request
                callback(request);                             //  4: request finished and response is ready
            }                                                  //
        };                                                     // request.status = returns the HTTP status-code
        request.open('POST', url , true);                      //  200: "OK"
        for (var name in headers) {                            //  404: "Not Found" etc.
            request.setRequestHeader(name, headers[name]);
        }
        request.send(data);
    },


    /**
     * Return a nicer representation of the specified argument's type.
     *
     * @param  mixed arg
     *
     * @return string
     */
    getType: function getType(arg) {
        var type = typeof(arg);
        if (type == 'object') {
            if (arg === null) {
                type = 'null';
            }
            else {
                type = arg.constructor.name || arg.constructor.toString();
                if (type.startsWith('[object ')) {
                    type = type.slice(8, -1);
                }
            }
        }
        return type;
    },


    /**
     * Show all accessible properties of the passed argument.
     *
     * @param  mixed arg
     * @param  bool  sort [optional] - whether or not to sort the displayed properties (default: yes)
     */
    showProperties: function showProperties(arg, sort) {
        if (arg === undefined) return alert('rosasurfer.showProperties()\n\nPassed parameter: undefined');
        if (arg === null)      return alert('rosasurfer.showProperties()\n\nPassed parameter: null');
        sort = (sort===undefined) ? true : Boolean(sort);

        var property='', properties=[], type=this.getType(arg);

        for (var i in arg) {
            try {
                property = type +'.'+ i +' = '+ arg[i];
            }
            catch (ex) {
                property = type +'.'+ i +' = exception while reading property ('+ ex.name +': '+ ex.message +')';
            }
            properties[properties.length] = property.replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        if (properties.length) {
            if (sort)
               properties = properties.sort();
            this.log(properties.join('<br>\n'));
        }
        else {
            alert('rosasurfer.showProperties()\n\n'+ type +' has no known properties.');
        }
    },


    /**
     * Log a message.
     *
     * @param  mixed  msg
     * @param  string target - Whether to log to the top (default) or the bottom of the page.
     *                         The method will remember the last used 'target' parameter.
     */
    log: function log(msg, target/*='top'*/) {
        if (this.log.console)
            return console.log(msg);

        var div = this.log.div;
        if (!div) {
            div = this.log.div = document.createElement('div');
            div.setAttribute('id', 'rosasurfer.log.output');
            div.style.position        = 'absolute';
            div.style.zIndex          = '65535';
            div.style.top             = '6px';
            div.style.left            = '6px';
            div.style.padding         = '6px';
            div.style.textAlign       = 'left';
            div.style.font            = 'normal normal 12px/1.5em arial,helvetica,sans-serif';
            div.style.color           = 'black';
            div.style.backgroundColor = 'lightgray';
            var bodies = document.getElementsByTagName('body');
            if (!bodies || !bodies.length) return alert('rosasurfer.log()\n\nFailed attaching the output DIV to the page body (BODY tag not found).\nWait for document.onLoad() or use console.log() if you want to log from the page header?');
            bodies[0].appendChild(div);
        }
        if      (target=='top'   ) div.style.position = 'absolute';
        else if (target=='bottom') div.style.position = 'relative';

        msg = typeof(msg)=='undefined' ? 'undefined' : msg.toString().replace(/ {2,}/g, function(match) {
            return '&nbsp;'.repeat(match.length);
        });

        div.innerHTML += msg +'<br>\n';
    },


    /**
     * Clear the log output in the current page.
     */
    clearLog: function clearLog() {
        if (this.log.div)
            this.log.div.innerHTML = '';
    },


    /**
     * Log a message to the status bar.
     *
     * @param  mixed msg
     */
    logStatus: function logStatus(msg) {
        if (this.getType(msg) == 'Event') this.logEvent(msg);
        else                              self.status = msg;
    },


    /**
     * Log event infos to the status bar.
     *
     * @param  Event ev
     */
    logEvent: function logEvent(ev) {
        this.logStatus(ev.type +' event,  window: ['+ (ev.pageX - pageXOffset) +','+ (ev.pageY - pageYOffset) +']  page: ['+ ev.pageX +','+ ev.pageY +']');
    }
};
var rs = rs || rosasurfer;
