/**
 * @license RequireJS domReady 2.0.1 Copyright (c) 2010-2012, The Dojo Foundation All Rights Reserved.
 * Available via the MIT or new BSD license.
 * see: http://github.com/requirejs/domReady for details
 */
define(function(){"use strict";function a(e){var n;for(n=0;n<e.length;n+=1)e[n](d)}function c(){var e=l;o&&e.length&&(l=[],a(e))}function f(){o||(o=!0,t&&clearInterval(t),c())}function u(e){return o?e(d):l.push(e),u}var e,n,t,i="undefined"!=typeof window&&window.document,o=!i,d=i?document:null,l=[];if(i){if(document.addEventListener)document.addEventListener("DOMContentLoaded",f,!1),window.addEventListener("load",f,!1);else if(window.attachEvent){window.attachEvent("onload",f),n=document.createElement("div");try{e=null===window.frameElement}catch(r){}n.doScroll&&e&&window.external&&(t=setInterval(function(){try{n.doScroll(),f()}catch(e){}},30))}"complete"===document.readyState&&f()}return u.version="2.0.1",u.load=function(e,n,t,i){i.isBuild?t(null):u(t)},u});
