/**!
 * violetCMS – knockout Pikaday
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license MIT
 */

define(["knockout", "pikaday"], function(ko, Pikaday) {
    "use strict";

    /**
     * This binding will look for a global ko.languageFormats() observable
     * to set and update Pikaday's localisation
     */
    ko.bindingHandlers.datepicker = {
        init: function(element, valueAccessor, allBindings) {
            const position = allBindings.get("position") || "bottom right";
            const value = valueAccessor();
            const picker = new Pikaday({
                field: element,
                position: position,
                firstDay: 1,
                yearRange: 3,
                showWeekNumber: true,
                showDaysInNextAndPreviousMonths: true,
                onSelect: function(date) {
                    let y, m, d, ISOString = null;
                    if (date) {
                        y = date.getFullYear();
                        m = date.getMonth()+1;
                        d = date.getDate();
                        ISOString = y + "-" + (m < 10 ? "0"+m : m) + "-" + (d < 10 ? "0"+d : d);
                    }
                    value(ISOString);
                }
            });
            if (ko.languageFormats && ko.languageFormats() !== null) {
                picker.config({ i18n: ko.languageFormats() });
            }
            ko.utils.domData.set(element, "Pikaday", picker);
            ko.utils.domNodeDisposal.addDisposeCallback(element, function() {
                picker.destroy();
            });
        },
        update: function(element, valueAccessor) {
            const picker = ko.utils.domData.get(element, "Pikaday");
            if (picker) {
                const value = valueAccessor();
                const valueUnwrapped = ko.unwrap(value);
                const date = valueUnwrapped ? new Date(valueUnwrapped) : null;
                date ? picker.setDate(date, true) : picker.clearDate(true);
                if (ko.languageFormats && ko.languageFormats() !== null) {
                    picker.config({ i18n: ko.languageFormats() });
                }
            }
        }
    };

});
