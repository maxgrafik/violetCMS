/**!
 * violetCMS – Utilities
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license MIT
 */

define(function() {
    "use strict";

    /* --- animation event --- */

    /* eslint-disable-next-line */
    const animationEvent = (!!window.WebKitAnimationEvent) ? "webkitAnimationEnd" : "animationend";


    /* --- element manipulation --- */

    function make(tagName, attrs) {
        const el = document.createElement(tagName);
        for (const prop in attrs) {
            el.setAttribute(prop, attrs[prop]);
        }
        return el;
    }

    function show(selector) {
        const el = document.querySelector(selector);
        if (el.hasAttribute("hidden")) {
            el.removeAttribute("hidden");
            addClass(el, "fadeIn", function() {
                removeClass(el, "fadeIn");
            });
        }
    }

    function hide(selector, callback) {
        const el = document.querySelector(selector);
        if (!el.hasAttribute("hidden")) {
            addClass(el, "fadeOut", function() {
                el.setAttribute("hidden", "");
                removeClass(el, "fadeOut");
                if (callback && typeof callback === "function") {
                    callback.call(el);
                }
            });
        }
    }

    function slideIn(selector, callback) {
        const el = document.querySelector(selector);
        addClass(el, "slideIn", function() {
            removeClass(el, "slideIn");
            if (callback && typeof callback === "function") {
                callback.call(el);
            }
        });
    }


    /* --- css class handling --- */

    function hasClass(el, className) {
        return el.classList ? el.classList.contains(className) : new RegExp("\\b"+ className+"\\b").test(el.className);
    }

    function addClass(el, className, callback) {
        if (el.classList) {
            el.classList.add(className);
        } else if (!hasClass(el, className)) {
            el.className += " " + className;
        }
        if (callback && typeof callback === "function") {
            el.addEventListener(animationEvent, function listener() {
                el.removeEventListener(animationEvent, listener);
                callback.call(this);
            });
        }
    }

    function removeClass(el, className, callback) {
        if (el.classList) {
            el.classList.remove(className);
        } else {
            el.className = el.className.replace(new RegExp("\\b"+ className+"\\b", "g"), "");
        }
        if (callback && typeof callback === "function") {
            el.addEventListener(animationEvent, function listener() {
                el.removeEventListener(animationEvent, listener);
                callback.call(this);
            });
        }
    }


    /* --- string manipulation --- */

    function extend(obj, src) {
        for (const key in src) {
            obj[key] = src[key];
        }
        return obj;
    }

    function formatDate(date, i18n, asShortdate) {

        const defaults = {
            months    : ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"],
            weekdays  : ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"],
            format    : "MM d, yyyy",
            shortdate : "mm/dd/yyyy"
        };

        const options = extend(defaults, i18n);

        const monthName = options.months[date.getMonth()];
        const dayName = options.weekdays[date.getDay()];

        const result = [];
        const format = asShortdate ? options.shortdate : options.format;

        let regexMatch;
        const regexPattern = /(yyyy|MM|mm|m|DD|D|dd|d|.)/g;
        while ((regexMatch = regexPattern.exec(format)) !== null) {
            switch (regexMatch[0]) {
            case "yyyy":
                result.push( date.getFullYear() );
                break;
            case "MM":
                result.push( monthName );
                break;
            case "M":
                result.push( monthName.substr(0, 3) );
                break;
            case "mm":
                result.push( ("0" + (date.getMonth()+1)).slice(-2) );
                break;
            case "m":
                result.push( (date.getMonth()+1) );
                break;
            case "DD":
                result.push( dayName );
                break;
            case "D":
                result.push( dayName.substr(0, 3) );
                break;
            case "dd":
                result.push( ("0" + date.getDate()).slice(-2) );
                break;
            case "d":
                result.push( date.getDate() );
                break;
            default:
                result.push(regexMatch[0]);
            }
        }
        return result.join("");
    }

    function formatDateAndTime(date, i18n, shortdate) {
        const d = formatDate(date, i18n, shortdate);
        const t = date.toLocaleTimeString([], {hour: "2-digit", minute: "2-digit"});
        return d + " – " + t;
    }


    /* --- lightbox --- */

    function showLightbox(MIMEType, url) {
        const backdrop = make("div", { class: "modal-backdrop fadeIn" });
        backdrop.addEventListener("click", function() {
            hideLightbox();
        });
        let el;
        if (MIMEType.startsWith("audio")) {
            el = make("audio", { class: "lightbox-audio", controls: "" });
            el.appendChild(
                make("source", { src: url, type: MIMEType })
            );
        } else if (MIMEType.startsWith("image")) {
            el = make("img", { class: "lightbox-img", src: url });
        } else if (MIMEType.startsWith("video")) {
            el = make("video", { class: "lightbox-video", controls: "" });
            el.appendChild(
                make("source", { src: url, type: MIMEType })
            );
        }

        el.addEventListener("click", function(event) {
            event.stopPropagation();
        });

        backdrop.appendChild(el);
        document.body.appendChild(backdrop);
    }

    function hideLightbox() {
        const backdrop = document.getElementsByClassName("modal-backdrop");
        if (backdrop.length > 0) {
            removeClass(backdrop[0], "fadeIn");
            addClass(backdrop[0], "fadeOut", function() {
                document.body.removeChild(backdrop[0]);
            });
        }
    }

    /* --- notifications --- */

    function notify(msg, msgType) {

        const widgetText = make("p", {});
        widgetText.textContent = msg;

        const widget = make("aside", { class: "notification " + msgType });
        widget.appendChild(widgetText);

        document.body.appendChild(widget);
        slideIn(".notification", function() {
            widget.parentElement.removeChild(widget);
        });
    }


    /* --- modals --- */

    function showModal(target, useBackdrop) {
        if (useBackdrop === true || useBackdrop === "static") {
            if (useBackdrop === true) {
                const backdrop = make("div", { class: "modal-backdrop fadeIn" });
                backdrop.addEventListener("click", function() {
                    const dlgCloseBtn = document.querySelector("dialog:not([hidden]) button.close");
                    if (dlgCloseBtn) {
                        dlgCloseBtn.click();
                    } else {
                        hideModal();
                    }
                });
                document.body.appendChild(backdrop);
            }
        }
        const el = (typeof target === "string") ? document.getElementById(target) : target;
        if (el) {
            addClass(el, "popIn", function() {
                removeClass(el, "popIn");
            });
            el.removeAttribute("hidden");
        }
    }

    function hideModal(callback) {
        const backdrop = document.getElementsByClassName("modal-backdrop");
        if (backdrop.length > 0) {
            removeClass(backdrop[0], "fadeIn");
            addClass(backdrop[0], "fadeOut", function() {
                document.body.removeChild(backdrop[0]);
            });
        }
        const dlg = document.querySelector("dialog:not([hidden])");
        if (dlg) {
            addClass(dlg, "popOut", function() {
                this.setAttribute("hidden", "");
                removeClass(this, "popOut");
                if (callback && typeof callback === "function") {
                    callback();
                }
            });
        }
    }

    function shakeModal() {
        const dlg = document.querySelector("dialog:not([hidden])");
        if (dlg) {
            addClass(dlg, "shake", function() {
                removeClass(this, "shake");
            });
        }
    }


    /* --- automatic tab creation & navigation --- */

    // mobile menu handler
    function createMobileMenu() {
        const menuTrigger = document.querySelector(".hamburger");
        if (menuTrigger) {
            menuTrigger.addEventListener("click", function(event) {
                event.preventDefault();
                event.stopPropagation();
                const mobileMenu = document.getElementsByTagName("nav")[0];
                if (menuTrigger.classList.contains("is-active")) {
                    menuTrigger.classList.remove("is-active");
                    mobileMenu.classList.remove("is-active");
                } else {
                    menuTrigger.classList.add("is-active");
                    mobileMenu.classList.add("is-active");
                }
                mobileMenu.addEventListener("click", function(e) {
                    const anchor = e.target.closest("a");
                    if (anchor) {
                        menuTrigger.classList.remove("is-active");
                        mobileMenu.classList.remove("is-active");
                    }
                });
            });
        }
    }

    function createTabs(el, params, callback) {

        // clear any child nodes
        while (el.firstChild) { el.firstChild.remove(); }

        const tabs = params.tabs;
        const index = params.index || 0;

        const tabPanels = document.querySelectorAll("[role='tabpanel']");
        /* eslint-disable-next-line no-cond-assign */
        for (let i = 0, thisPanel; thisPanel = tabPanels[i]; i++) {
            if (tabs && tabs[i]) {

                let thisNavAnchor = make("a", { href: "#"+thisPanel.id });

                thisNavAnchor.textContent = tabs[i];
                thisNavAnchor.addEventListener("click", function(event) {
                    event.preventDefault();

                    if (callback && typeof callback === "function") {
                        const selection = this.parentElement;
                        const parent = this.parentElement.parentElement;
                        callback( Array.prototype.indexOf.call(parent.children, selection) );
                    }

                    const target = document.querySelector(this.getAttribute("href"));
                    const panels = document.querySelectorAll("[role='tabpanel']");

                    if (panels.length === 0) { return; }

                    removeClass(el.querySelector(".active"), "active");
                    addClass(this.parentNode, "active");

                    /* eslint-disable-next-line no-cond-assign */
                    for (let j = 0, thisTab; thisTab = panels[j]; j++) {
                        if (hasClass(thisTab, "active")) {
                            removeClass(thisTab, "active");
                            addClass(target, "active");
                            break; /* there should only be one */
                        }
                    }
                });

                let thisNavItem = make("li", { role: "tab", class: "tab-"+tabs.length });
                if (index && index === i || !index && i === 0) {
                    addClass(thisNavItem, "active");
                    addClass(thisPanel, "active");
                }

                thisNavItem.appendChild(thisNavAnchor);
                el.appendChild(thisNavItem);
                thisNavAnchor = thisNavItem = null;
            }
        }
    }

    return {
        make              : make,
        show              : show,
        hide              : hide,
        showModal         : showModal,
        hideModal         : hideModal,
        shakeModal        : shakeModal,
        showLightbox      : showLightbox,
        notify            : notify,
        createTabs        : createTabs,
        createMobileMenu  : createMobileMenu,
        formatDate        : formatDate,
        formatDateAndTime : formatDateAndTime
    };

});
