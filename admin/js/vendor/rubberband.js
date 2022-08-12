/**!
 * violetCMS – Rubberband Selection
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license MIT
 */

define(function() {
    "use strict";

    const Rubberband = function(dragzoneSelector, itemSelector, onSelect) {

        let rb = false, disabled = false, mouseDownX, mouseDownY;
        const dragzone = document.querySelector(dragzoneSelector);

        if (dragzone) {
            dragzone.addEventListener("mousedown", selectionStart);
        }

        function selectionStart(event) {
            if (!disabled && event.button === 0) {
                mouseDownX = event.pageX;
                mouseDownY = event.pageY;
                document.body.addEventListener("mousemove", selectionDrag);
                window.addEventListener("mouseup", selectionEnd);
            }
        }

        function selectionDrag(event) {
            if (!rb && (Math.abs(mouseDownX-event.pageX) > 10 || Math.abs(mouseDownY-event.pageY) > 10)) {
                createRubberband();
            }
            drawRubberband(event);
        }

        function selectionEnd() {
            getSelection();
            if (rb) {
                rb.parentElement.removeChild(rb);
                rb = false;
            }
            document.activeElement.blur();
            document.body.classList.remove("hasActiveRubberband");
            document.body.removeEventListener("mousemove", selectionDrag);
            window.removeEventListener("mouseup", selectionEnd);
        }

        function createRubberband() {
            rb = document.createElement("div");
            rb.classList.add("rubberband");
            rb.style.top = mouseDownY+"px";
            rb.style.left = mouseDownX+"px";
            rb = document.body.appendChild(rb);
            document.body.classList.add("hasActiveRubberband");
        }

        function drawRubberband(event) {
            if (rb) {
                if (event.pageX > mouseDownX) {
                    rb.style.left = mouseDownX+"px";
                    rb.style.width = (event.pageX-mouseDownX)+"px";
                } else {
                    rb.style.left = event.pageX+"px";
                    rb.style.width = (mouseDownX-event.pageX)+"px";
                }
                if (event.pageY > mouseDownY) {
                    rb.style.top = mouseDownY+"px";
                    rb.style.height = (event.pageY-mouseDownY)+"px";
                } else {
                    rb.style.top = event.pageY+"px";
                    rb.style.height = (mouseDownY-event.pageY)+"px";
                }
            }
        }

        function getSelection() {
            const selectableItems = dragzone.querySelectorAll(itemSelector);
            if (rb && selectableItems.length) {
                const selectedItems = [];
                const rbRect = rb.getBoundingClientRect();

                for (const item of selectableItems) {
                    const itemRect = item.getBoundingClientRect();
                    if (!(itemRect.right < rbRect.left || itemRect.left > rbRect.right || itemRect.bottom < rbRect.top || itemRect.top > rbRect.bottom)) {
                        selectedItems.push(item);
                    }
                }

                if (onSelect && typeof onSelect === "function") {
                    onSelect(selectedItems);
                }
            }
        }

        Rubberband.prototype.enabled = function(status) {
            if (status) {
                disabled = false;
            } else {
                disabled = true;
                if (rb) {
                    rb.parentElement.removeChild(rb);
                    rb = false;
                }
                document.body.classList.remove("hasActiveRubberband");
                document.body.removeEventListener("mousemove", selectionDrag);
                window.removeEventListener("mouseup", selectionEnd);
            }
        };

    };

    return Rubberband;
});
