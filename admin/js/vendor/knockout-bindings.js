/**!
 * violetCMS – knockout Bindings
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license MIT
 */

/* global i18n */
define(["knockout", "utils"], function(ko, ux) {
    "use strict";

    ko.dirtyFlag = function(data) {
        let hash = ko.observable(ko.toJSON(data));
        const fn = function() {};
        fn.isDirty = ko.computed(function() {
            return (hash() !== ko.toJSON(data));
        }).extend({ notify: "always" });
        fn.setClean = function() {
            hash(ko.toJSON(data));
        };
        fn.getDirty = function() {
            const a = JSON.parse(hash());
            const b = JSON.parse(ko.toJSON(data));
            const c = [];
            for (const key of Object.keys(a)) {
                if (Object.prototype.hasOwnProperty.call(b, key)) {
                    if (a[key] !== b[key]) {
                        c.push(key);
                    }
                }
            }
            return c;
        };
        fn.dispose = function() {
            fn.isDirty.dispose();
            hash = null;
        };
        return fn;
    };

    ko.extenders.boolean = function(target) {

        function forceBoolean(value) {
            if (typeof value !== "boolean") {
                target(value === "true" ? true : false);
            }
        }

        forceBoolean(target());

        target.subscribe(forceBoolean);

        return target;
    };

    ko.bindingHandlers.i18n = {
        init: function(element) {
            const el = element.firstChild;
            if (el === null) {
                element.appendChild(document.createTextNode(""));
            } else if (el.nodeType !== 3) {
                element.insertBefore(document.createTextNode(""), el);
            }
        },
        update: function(element, valueAccessor, allBindings) {
            const language = ko.language(); // just for subscription
            if (language) {
                const key = valueAccessor();
                const obj = allBindings.get("i18nObj");
                const translation = obj ? i18n(key, obj) : i18n(key);
                const el = element.firstChild; // get the first child node (should be a text node)
                el.textContent = translation;
            }
        }
    };

    ko.bindingHandlers.localeDateAndTime = {
        update: function(element, valueAccessor, allBindings) {
            const value = valueAccessor();
            const valueUnwrapped = ko.unwrap(value);
            if (valueUnwrapped) {
                const shortdate = allBindings.get("shortdate");
                const date = new Date(valueUnwrapped);
                const dateLocale = ux.formatDateAndTime(date, ko.languageFormats(), shortdate);
                element.tagName === "INPUT" ? element.value = dateLocale : element.textContent = dateLocale;
            } else {
                element.tagName === "INPUT" ? element.value = "" : element.textContent = "";
            }
        }
    };

    ko.bindingHandlers.navTabs = {
        update: function(element, valueAccessor) {
            const params = ko.unwrap(valueAccessor());
            ux.createTabs(element, params, function(selected) {
                if (params.selected && ko.isWritableObservable(params.selected)) {
                    params.selected(selected);
                }
            });
        }
    };

    ko.bindingHandlers.modal = {
        init: function(element, valueAccessor, allBindings, viewModel, bindingContext) {
            ko.applyBindingsToNode(element, {
                if: valueAccessor()
            }, bindingContext);
        },
        update: function(element, valueAccessor) {
            const observable = valueAccessor();
            const value = ko.unwrap(observable);
            if (value !== null) {
                ux.showModal(element, true);
            }
        }
    };

    ko.bindingHandlers.menu = {
        init: function(element) {
            let el;

            function onClickElement() {
                element.classList.toggle("active");
                el = element.nextElementSibling;
                if (el && el.classList.contains("dropdown")) {
                    el.classList.toggle("active");
                }
            }

            function onClickBody(event) {
                el = event.target.closest(".dropdown");
                if (!el) {
                    element.classList.remove("active");
                    el = element.nextElementSibling;
                    if (el && el.classList.contains("dropdown")) {
                        el.classList.remove("active");
                    }
                }
            }

            element.addEventListener("click", onClickElement);
            document.body.addEventListener("click", onClickBody);

            ko.utils.domNodeDisposal.addDisposeCallback(element, function() {
                element.removeEventListener("click", onClickElement);
                document.body.removeEventListener("click", onClickBody);
            });
        },
        update: function(element, valueAccessor, allBindings, viewModel, bindingContext) {
            const menuData = valueAccessor();
            const menuOptions = ko.unwrap(menuData.options);

            const selectedOption = ko.utils.arrayFirst(menuOptions, function(option) {
                return ko.isObservable(menuData.value) && option.value === ko.unwrap(menuData.value);
            });

            if (selectedOption) {
                element.textContent = selectedOption.title;
            } else if (menuOptions.length > 0) {
                if (ko.isObservable(menuData.value)) {
                    menuData.value(menuOptions[0].value);
                }
                element.textContent = menuOptions[0].title;
            }

            let el;

            if (menuOptions) {
                el = element.nextElementSibling;
                if (el && el.classList.contains("dropdown")) {
                    el.removeEventListener("click", onMenuSelect);
                    el.parentElement.removeChild(el);
                }
                el = buildMenu(menuOptions);
                el.addEventListener("click", onMenuSelect);
                element.parentElement.appendChild(el);
            }

            function onMenuSelect(event) {
                event.preventDefault();
                event.stopImmediatePropagation();

                el = event.target.closest("li");
                if (el && el.dataset) {
                    if (ko.isObservable(menuData.value)) {
                        menuData.value(el.dataset.value);
                    } else if (typeof menuData.value === "function") {
                        menuData.value.call(bindingContext.$data, el.dataset.value);
                    }
                }

                element.classList.remove("active");
                el = element.nextElementSibling;
                if (el && el.classList.contains("dropdown")) {
                    el.classList.remove("active");
                }
            }

            function buildMenu(options) {
                let li;
                const ul = ux.make("UL", { class: "dropdown" });
                ko.utils.arrayForEach(options, function(item) {
                    if (ko.unwrap(item.title) === null) {
                        li = ux.make("HR");
                    } else {
                        li = ux.make("LI", { "data-value": ko.unwrap(item.value) });
                        li.appendChild(document.createTextNode(ko.unwrap(item.title)));
                    }
                    ul.appendChild(li);
                });
                return ul;
            }
        }
    };

});
