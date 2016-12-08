/**
 * Extend objects.
 */
// Internet Explorer 8 support
if (!Array.prototype.forEach  ) Array.prototype.forEach   = function(/*function*/ func, scope) { for (var i=0, len=this.length; i < len; ++i) func.call(scope, this[i], i, this); }

if (!Date.prototype.addDays   ) Date.prototype.addDays    = function(/*int*/ days)    { this.setTime(this.getTime() + (days*24*60*60*1000)); return this; }
if (!Date.prototype.addHours  ) Date.prototype.addHours   = function(/*int*/ hours)   { this.setTime(this.getTime() + (  hours*60*60*1000)); return this; }
if (!Date.prototype.addMinutes) Date.prototype.addMinutes = function(/*int*/ minutes) { this.setTime(this.getTime() + (   minutes*60*1000)); return this; }
if (!Date.prototype.addSeconds) Date.prototype.addSeconds = function(/*int*/ seconds) { this.setTime(this.getTime() + (      seconds*1000)); return this; }

if (!String.prototype.capitalize     ) String.prototype.capitalize      = function()                  { return this.charAt(0).toUpperCase() + this.slice(1); }
if (!String.prototype.capitalizeWords) String.prototype.capitalizeWords = function()                  { return this.replace(/\w\S*/g, function(word) { return word.capitalize(); }); }
if (!String.prototype.decodeEntities ) String.prototype.decodeEntities  = function()                  { if (!String.prototype.decodeEntities.textarea) /*static*/ String.prototype.decodeEntities.textarea = document.createElement('textarea'); String.prototype.decodeEntities.textarea.innerHTML = this; return String.prototype.decodeEntities.textarea.value; }
if (!String.prototype.trim           ) String.prototype.trim            = function()                  { return this.replace(/(^\s+)|(\s+$)/g, ''); }
if (!String.prototype.startsWith     ) String.prototype.startsWith      = function(/*string*/ prefix) { return (this.indexOf(prefix) === 0); }
if (!String.prototype.contains       ) String.prototype.contains        = function(/*string*/ string) { return (this.indexOf(string) != -1); }
if (!String.prototype.endsWith       ) String.prototype.endsWith        = function(/*string*/ suffix) { var pos = this.lastIndexOf(suffix); return (pos!=-1 && this.length==pos+suffix.length); }

// fix broken Internet Explorer substr()
if ('ab'.substr(-1) != 'b') {
   String.prototype.substr = function(start, length) {
      var from = start;
         if (from < 0) from += this.length;
         if (from < 0) from = 0;
      var to = typeof(length)=='undefined' ? this.length : from+length;
         if (from > to) to = from;
      return this.substring(from, to);
   }
}

// fix inaccurate Number.toFixed()
(function() {
   /**
    * Decimal adjustment of a number.
    *
    * @param  string  type  - The type of adjustment.
    * @param  number  value - The number.
    * @param  int     exp   - The exponent (the 10 logarithm of the adjustment base).
    *
    * @return number - The adjusted value.
    *
    * @see    https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Math/round#Example:_Decimal_rounding
    */
   function decimalAdjust(type, value, exp) {
      // if the exp is undefined or zero...
      if (typeof(exp)=='undefined' || +exp===0) {
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
   if (!Math.round10) Math.round10 = function(value, exp) { return decimalAdjust('round', value, exp); };
   if (!Math.floor10) Math.floor10 = function(value, exp) { return decimalAdjust('floor', value, exp); };
   if (!Math.ceil10)  Math.ceil10  = function(value, exp) { return decimalAdjust('ceil',  value, exp); };

   Number.prototype.toFixed10 = function(precision) {
      return Math.round10(this, -precision).toFixed(precision);
   }
})();


/**
 * Define our namespace.
 */
var rs = rs || {};


/**
 * Get an array with all query parameters.
 *
 * @param  url   - static URL to get query parameters from (if not given, the current page's url is used)
 *
 * @return array - [key1=>value1, key2=>value2, ..., keyN=>valueN]
 */
function getQueryParameters(/*string*/url) {
   var pos, search;
   if (typeof(url) == 'undefined') search = location.search;
   else                            search = ((pos=url.indexOf('?'))==-1) ? '' : url.substr(pos);

   var result={}, values, pairs=search.slice(1).split('&');
   pairs.forEach(function(/*string*/pair) {
      values = pair.split('=');                                      // unlike PHP the JavaScript function split(str, limit) discards additional occurrences
      if (values.length > 1)
         result[values.shift()] = values.join('=');
   });
   return result;
}


/**
 * Get a single query parameter value.
 *
 * @param  name   - parameter name
 * @param  url    - static URL to get a query parameter from (if not given, the current page's url is used)
 *
 * @return string - value or null if the parameter doesn't exist in the query string
 */
function getQueryParameter(/*string*/name, /*string*/url) {
   if (typeof(name) == 'undefined') return alert('getQueryParameter()\n\nUndefined parameter: name');

   return getQueryParameters(url)[name];
}


/**
 * Whether or not a parameter exists in the query string.
 *
 * @param  name - parameter name
 * @param  url  - static URL to check for the query parameter (if not given, the current page's url is used)
 *
 * @return bool
 */
function isQueryParameter(/*string*/name, /*string*/url) {
   if (typeof(name) == 'undefined') return alert('isQueryParameter()\n\nUndefined parameter: name');

   return typeof(getQueryParameters(url)[name]) != 'undefined';
}


/**
 * Show all accessible properties of the given argument.
 */
function showProperties(/*mixed*/arg) {
   if (typeof(arg) == 'undefined') return alert('showProperties()\n\nUndefined parameter: arg');

   var properties=[], property='';
   var name = (arg.constructor===Array) ? 'array' : arg.toString();

   for (var i in arg) {
      try {
         property = name +'.'+ i +' = '+ arg[i];
      }
      catch (ex) {
         break;
         property = name +'.'+ i +' = Exception while reading property (name: '+ ex.name +', message: '+ ex.message +')';
      }
      properties[properties.length] = property.replace(/</g, '&lt;').replace(/>/g, '&gt;');
   }

   if (properties.length) {
      log(properties.sort().join('<br>\n'));
   }
   else {
      var type = (arg.constructor===Array) ? 'array' : typeof(arg);
      alert('showProperties()\n\n'+ type.toLowerCase() +' '+ arg +' has no known properties.');
   }
}


/**
 * Log a message to the bottom of the current page or to a log window.
 */
var Logger = {
   div:      null,
   popup:    null,
   popupDiv: null,

   log:     function(/*string*/msg, /*mixed*/target) { Logger.writeln(msg          , target); },
   writeln: function(/*string*/msg, /*mixed*/target) { Logger.write  (msg +'<br>\n', target); },

   write: function(/*string*/msg, /*mixed*/target) {
      if (!target) {
         if (!Logger.div) {
            Logger.div = document.createElement('div');
            Logger.div.setAttribute('id', 'logger');
            Logger.div.style.zIndex          = ''+ (0xFFFFFFFFFFFF+1);
            Logger.div.style.position        = 'absolute';
            Logger.div.style.left            = '10px';
            Logger.div.style.top             = '10px';
            Logger.div.style.padding         = '10px';
            Logger.div.style.textAlign       = 'left';
            Logger.div.style.fontSize        = '13px';
            Logger.div.style.fontFamily      = 'arial,helvetica,sans-serif';
            Logger.div.style.color           = 'black';
            Logger.div.style.backgroundColor = 'lightgray';

            var bodies = document.getElementsByTagName('body');
            if (!bodies || !bodies.length)
               return alert('Logger.write()\n\nError: you can only log from inside the <body> tag !');
            bodies[0].appendChild(Logger.div);
         }
         target = Logger.div;
      }

      if (target === true) {
         if (navigator.userAgent.endsWith('/4.0'))
            return Logger.write(msg);                                // workaround for flawed GreaseMonkey in Firefox 4.0

         if (!Logger.popupDiv) {
            Logger.popup = open('', 'logWindow', 'resizable,scrollbars,width=600,height=400');
            if (!Logger.popup)
               return alert('Logger.write()\n\nCannot open popup for '+ location +'\nPlease disable your popup blocker.');

            Logger.popupDiv = Logger.popup.document.createElement('div');
            Logger.popupDiv.style.fontSize   = '13px';
            Logger.popupDiv.style.fontFamily = 'arial,helvetica,sans-serif';
            Logger.popup.document.getElementsByTagName('body')[0].appendChild(Logger.popupDiv);
         }
         else if (Logger.popup.closed) {
            Logger.popup = Logger.popupDiv = null;
            return Logger.write(msg, target);
         }
         target = Logger.popupDiv;
      }
      target.innerHTML += msg;
   },

   clear: function() {
      if (Logger.div)
         Logger.div.innerHTML = '';
      if (Logger.popup && !Logger.popup.closed && Logger.popupDiv)
         Logger.popupDiv.innerHTML = '';
   }
}
log = Logger.log;


/**
 * Log a message to the status bar.
 */
function logStatus(/*mixed*/msg) {
   if (typeof(msg)=='object' && msg=='[object Event]')
      logEvent(msg);
   else
      self.status = msg;
}


/**
 * Log Event infos to the status bar.
 */
function logEvent(/*Event*/ev) {
   logStatus(ev.type +' event,  window: ['+ (ev.pageX - pageXOffset) +','+ (ev.pageY - pageYOffset) +']  page: ['+ ev.pageX +','+ ev.pageY +']');
}


/**
 * Load a url via GET and pass the response to the specified callback function.
 *
 * @param string   url      - url to load
 * @param function callback - callback function
 */
function loadUrl(/*string*/ url, /*function*/ callback) {                                       // request.readyState = returns the status of the XMLHttpRequest
   var request = new XMLHttpRequest();                                                          //  0: request not initialized
   request.url = url;                                                                           //  1: server connection established
   request.onreadystatechange = function() {                                                    //  2: request received
      if (request.readyState == 4) {                                                            //  3: processing request
         callback(request);                                                                     //  4: request finished and response is ready
      }                                                                                         //
   };                                                                                           // request.status = returns the HTTP status-code
   request.open('GET', url , true);                                                             //  200: "OK"
   request.send(null);                                                                          //  404: "Not Found" etc.
}


/**
 * Load a url via POST and pass the response to the specified callback function.
 *
 * @param string   url      - url to load
 * @param string   data     - content to send in the request's body (i.e. POST parameter)
 * @param object   headers  - additional request header
 * @param function callback - callback function
 */
function postUrl(/*string*/ url, /*string*/ data, /*object*/ headers, /*function*/ callback) {  // request.readyState = returns the status of the XMLHttpRequest
   var request = new XMLHttpRequest();                                                          //  0: request not initialized
   request.url = url;                                                                           //  1: server connection established
   request.onreadystatechange = function() {                                                    //  2: request received
      if (request.readyState == 4) {                                                            //  3: processing request
         callback(request);                                                                     //  4: request finished and response is ready
      }                                                                                         //
   };                                                                                           // request.status = returns the HTTP status-code
   request.open('POST', url , true);                                                            //  200: "OK"
   for (var name in headers) {                                                                  //  404: "Not Found" etc.
      request.setRequestHeader(name, headers[name]);
   }
   request.send(data);
}
