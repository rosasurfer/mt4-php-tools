/**
 * Extend objects.
 */
if (!String.prototype.decodeEntities) String.prototype.decodeEntities = function()                  { return this.replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&').replace(/&Auml;/g, 'Ä').replace(/&Ouml;/g, 'Ö').replace(/&Uuml;/g, 'Ü').replace(/&auml;/g, 'ä').replace(/&ouml;/g, 'ö').replace(/&uuml;/g, 'ü').replace(/&szlig;/g, 'ß'); }
if (!String.prototype.endsWith      ) String.prototype.endsWith       = function(/*string*/ suffix) { var pos = this.lastIndexOf(suffix); return (pos != -1 && this.length == pos+suffix.length); }
if (!String.prototype.startsWith    ) String.prototype.startsWith     = function(/*string*/ prefix) { return (this.indexOf(prefix) === 0); }
if (!String.prototype.trim          ) String.prototype.trim           = function()                  { return this.replace(/(^\s+)|(\s+$)/g, ''); }
if ('ab'.substr(-1) != 'b') {       // broken Internet Explorer
   String.prototype.substr = function(start, length) {
      var from = start;
         if (from < 0) from += this.length;
         if (from < 0) from = 0;
      var to = typeof(length)=='undefined' ? this.length : from+length;
         if (from > to) to = from;
      return this.substring(from, to);
   }
   /*
   String.prototype.substr = function(substr) {
      return function(start, length) {
         if (start < 0)
            start = this.length + start;
         return substr.call(this, start, length);
      }
   }(String.prototype.substr);
   */
}                                   // Internet Explorer 8 support
if (!Array.prototype.forEach) Array.prototype.forEach = function(/*function*/fn, scope) { for (var i=0, len=this.length; i < len; ++i) fn.call(scope, this[i], i, this); }


/**
 * Shortcut for getElementById(), eine ggf. existierende jQuery()-Implementierung wird nicht überschrieben.
 */
if (typeof($ ) == 'undefined') $  = function(id) { return document.getElementById(id); }
if (typeof($$) == 'undefined') $$ = function(id) { return document.getElementById(id); }


/**
 * Get an array with all query parameters.
 *
 * @param  url   - static URL to get query parameters from (if not given, the current page's url is used)
 *
 * @return Array - [key1=>value1, key2=>value2, ..., keyN=>valueN]
 */
function getQueryParameters(/*string*/url) {
   var pos, search;
   if (typeof(url) == 'undefined') search = location.search;
   else                            search = ((pos=url.indexOf('?'))==-1) ? '' : url.substr(pos);

   var result={}, values, pairs=search.slice(1).split('&');
   pairs.forEach(function(/*string*/pair) {
      values = pair.split('=');     // split(str, limit) verwirft anders als PHP überzählige Teilstrings
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

   for (var i in arg) {
      try {
         property = arg.toString() +'.'+ i +' = '+ arg[i];
      }
      catch (ex) {
         property = arg.toString() +'.'+ i +' = Exception while reading property (name: '+ ex.name +', message: '+ ex.message +')';
      }
      properties[properties.length] = property.replace(/</g, '&lt;').replace(/>/g, '&gt;');
   }

   if (properties.length) {
      var popup = open('', 'show_properties', 'resizable,scrollbars,width=700,height=800');
      if (!popup) return alert('Cannot open popup for '+ location +'\nPlease disable popup blocker.');

      var div = popup.document.createElement('div');
      div.style.fontSize   = '13px';
      div.style.fontFamily = 'arial,helvetica,sans-serif';
      div.innerHTML        = properties.sort().join('<br>\n');
      popup.document.getElementsByTagName('body')[0].appendChild(div);
      popup.focus();
   }
   else {
      var type = typeof(arg);
      if (type == 'function') type = '';
      else                    type = type.charAt(0).toUpperCase() + type.slice(1);
      alert((type +' '+ arg +' has no known properties.').trim());
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
            Logger.div.style.fontSize   = '13px';
            Logger.div.style.fontFamily = 'arial,helvetica,sans-serif';

            var bodies = document.getElementsByTagName('body');
            if (!bodies || !bodies.length)
               return alert('Logging error,\nyou can only log from inside the <body> tag !');
            bodies[0].appendChild(Logger.div);
         }
         target = Logger.div;
      }

      if (target === true) {
         if (!Logger.popupDiv) {
            Logger.popup = open('', 'logWindow', 'resizable,scrollbars,width=600,height=400');
            if (!Logger.popup)
               return alert('Cannot open popup for '+ location +'\nPlease disable popup blocker.');

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
 * Detect Internet Explorer.
 *
 * @return bool
 */
function isIE() {
   return document.all && !window.opera && navigator.vendor!='KDE';
}
