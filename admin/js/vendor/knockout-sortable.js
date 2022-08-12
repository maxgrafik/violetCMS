/**!
 * violetCMS – knockout Sortable
 * code heavily inspired by https://github.com/SortableJS/knockout-sortablejs
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license MIT
 */

define(["knockout", "sortable"], function(ko, Sortable) {
    "use strict";

    const eventHandlers = {
        sourceVM: null,
        targetVM: null,
        onAdd: function(event, data, viewModel) {
            this.targetVM = viewModel;
        },
        onRemove: function(event, data, viewModel) {
            this.sourceVM = viewModel;
        },
        onEnd: function(event, data, viewModel) {
            const sourceVM = this.sourceVM || viewModel;
            const targetVM = this.targetVM || viewModel;
            if (sourceVM !== null && targetVM !== null) {
                const trueIndex = sourceVM.indexOf(data);
                const newIndex = event.newIndex;

                event.item.parentNode.removeChild(event.item);

                sourceVM.splice(trueIndex, 1);
                sourceVM.valueHasMutated();
                ko.tasks.runEarly();

                targetVM.splice(newIndex, 0, data);
                targetVM.valueHasMutated();
            } else {
                console.error("ReferenceError: sourceVM or targetVM missing");
            }
            this.sourceVM = this.targetVM = null;
        }
    };

    ko.bindingHandlers.sortable = {
        init: function(element, valueAccessor, allBindings, viewModel, bindingContext) {

            // safari bug
            // see https://github.com/SortableJS/sortablejs/issues/1571
            const useFallback = !!navigator.vendor.match(/apple/i);

            const options = ko.unwrap(valueAccessor())["options"];
            const sortableDefaults = {
                group: "sitemap",
                forceFallback: useFallback,
                fallbackOnBody: true,
                swapThreshold: 0.5,
                invertSwap: true,
                filter: "a, button",
                animation: 500
            };

            /* extend with given options */
            const sortableOptions = ko.utils.extend(sortableDefaults, options || {});

            /* run our own actions on selected events */
            ["onAdd", "onRemove", "onEnd"].forEach(function(eventType) {
                if (sortableOptions[eventType] || eventHandlers[eventType]) {
                    sortableOptions[eventType] = function(parentBindings, optionsHandler, event) {

                        let data = null;

                        const noChildContext = parentBindings["noChildContext"];
                        if (noChildContext) {
                            const alias = parentBindings["as"];
                            const context = ko.contextFor(event.item);
                            data = context[alias];
                        } else {
                            data = ko.dataFor(event.item);
                        }

                        const vm = parentBindings["viewModel"] || parentBindings["foreach"];
                        if (eventHandlers[eventType]) {
                            eventHandlers[eventType](event, data, vm);
                        }
                        if (optionsHandler) {
                            optionsHandler(event, data); /* still calling any given event handler */
                        }
                    }.bind(null, ko.unwrap(valueAccessor()), sortableOptions[eventType]);
                }
            });

            const sortableInstance = Sortable.create(element, sortableOptions);

            ko.utils.domData.set(element, "Sortable", sortableInstance);
            ko.utils.domNodeDisposal.addDisposeCallback(element, function() {
                sortableInstance.destroy();
                ko.utils.domData.set(element, "Sortable", null);
            });

            return ko.bindingHandlers.template.init(element, valueAccessor, allBindings, viewModel, bindingContext);
        },
        update: function(element, valueAccessor, allBindings, viewModel, bindingContext) {
            const sortableOptions = ko.unwrap(valueAccessor()).options || {};
            const sortableInstance = ko.utils.domData.get(element, "Sortable");
            if (sortableInstance) {
                for (const prop in sortableOptions) {
                    /* NEVER UPDATE THESE EVENTS - this breaks knockout-sortable */
                    if (["onAdd", "onRemove", "onEnd"].indexOf(prop) < 0) {
                        sortableInstance.option(prop, ko.unwrap(sortableOptions[prop]));
                    }
                }
            }
            return ko.bindingHandlers.template.update(element, valueAccessor, allBindings, viewModel, bindingContext);
        }
    };

});
