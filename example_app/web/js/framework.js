
window.setTimeout(function() {
	if ( !arguments.length ) {
		// polyfill
		var sto = window.setTimeout;
		window.setTimeout = function(cb, speed) {
			var args = [];
			for ( var i=2; i<arguments.length; i++ ) {
				args.push(arguments[i]);
			}
			sto(function() {
				cb.apply(null, args);
			}, speed);
		};
		window.setTimeout.polyfilled = true;
	}
}, 0, 1);

$ = function(q) {
	if ( 'function' == typeof q ) {
		if ( 'complete' == document.readyState ) {
			q();
			return;
		}
		document.bind('DOMContentLoaded', q);
		return;
	}
	return document.querySelector(q);
}
document.$ = $;
HTMLElement.prototype.one = function(q) {
	if ( 'string' != typeof q ) {
		return q;
	}
	return this.querySelector(q);
};

A = function(arr) {
	try {
		return Array.prototype.slice.call(arr);
	}
	catch (ex) {
		for ( var r = [], L = arr.length, i = 0; i<L; i++ ) {
			r.push(arr[i]);
		}
		return r;
	}
};

Array.prototype.contains = function(el) {
	for ( var L=this.length, i=0; i<L; i++ ) {
		if ( el == this[i] ) {
			return true;
		}
	}
	return false;
};

$$ = function(q) {
	return A(document.querySelectorAll(q), 0);
}
document.$$ = $$;
HTMLElement.prototype.all = function(q) {
	return A(this.querySelectorAll(q));
};

Array.prototype.each = Array.prototype.forEach;
Object.prototype.bind = function(type, event) { // Object so Window inherits it too
	this.addEventListener(type, event, false);
	return this;
};
Object.prototype.invoke = function(method, args) {
	this[method].apply(this, args);
	return this;
};
Array.prototype.invoke = function(method, args) {
	this.each(function(el) {
		el[method].apply(el, args);
	});
	return this;
};
Array.prototype.bind = function(type, fn) {
	return this.invoke('bind', [type, fn]);
};

HTMLElement.prototype.attr = function(key, val) {
	if ( val === undefined ) {
		return this.getAttribute(key);
	}
	this.setAttribute(key, val);
	return this;
};

HTMLElement.prototype.serialize = function() {
	var v = [];
	A(this.elements).forEach(function(el, i) {
		if ( !el.name ) {
			return;
		}
		var type = el.type || el.nodeName.toLowerCase();
		switch( type ) {
			case 'fieldset':
				// no value
			break;
			case 'checkbox':
			case 'radio':
				if ( el.checked ) {
					v.push(encodeURIComponent(el.name) + '=' + encodeURIComponent( el.value || '1' ));
				}
			break;
			default:
				v.push(encodeURIComponent(el.name) + '=' + encodeURIComponent(el.value));
			break;
		}
	});
	return v.join('&');
};
HTMLElement.prototype.prev = function() {
	var s = this.previousSibling;
	if ( s && s.nodeType != 1 ) {
		s = s.previousSibling;
	}
	return s;
};
HTMLElement.prototype.next = function() {
	var s = this.nextSibling;
	if ( s && s.nodeType != 1 ) {
		s = s.nextSibling;
	}
	return s;
};
HTMLElement.prototype.remove = function() {
	return this.parentNode.removeChild(this);
};
HTMLElement.prototype.html = function(html) {
	if ( html != null ) {
		if ( 'function' == typeof html ) {
			html = html(this);
		}
		this.innerHTML = html;
		return this;
	}
	return this.innerHTML;
};

HTMLElement.prototype.is = function(q) {
	return this.parentNode.all(q).contains(this);
};
HTMLElement.prototype.parent = function(q) {
	if ( !q ) {
		return this.parentNode;
	}
	var p = this.parentNode;
	try {
		while ( p && !p.is(q) ) {
			p = p.parentNode;
		}
		return p;
	}
	catch (ex) {}
	return false;
};
HTMLElement.prototype.fire = function(type, args) {
	var e = document.createEvent('MouseEvents');
	e.initMouseEvent(type, true, true, window, 0, 0, 0, 0, 0, false, false, false, false, 0, null);
	return this.dispatchEvent(e);
};

$.ajax = function(url, handler, data, options) {
	var xhr = new XMLHttpRequest,
		method = data ? 'POST' : 'GET';
	xhr.open(method, url);
	xhr.setRequestHeader('Ajax', '1');
	if ( data ) {
		xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
	}
	if ( options ) {
		try {
			for ( x in options ) {
				if ( options.hasOwnProperty(x) ) {
					xhr[x] = options[x];
				}
			}
		}
		catch (ex) { alert(ex.message); }
	}
	xhr.onreadystatechange = function(e) {
		if ( 4 === this.readyState ) {
			this.event = e;
			handler.call(this, this.responseText);
		}
	};
	xhr.send(data || '');
console.log(xhr);
	return false;
}

