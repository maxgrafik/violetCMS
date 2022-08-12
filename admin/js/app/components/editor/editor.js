/**!
 * violetCMS – CodeMirror
 *
 * @copyright  Copyright (C) 2022 Hendrik Meinl
 * @license MIT
 */

/* global i18n */
define([
    "knockout",
    "cm/lib/codemirror",
    "cm/mode/markdown/markdown",
    "cm/mode/violet/violet",
    "cm/addon/edit/continuelist",
    "cm/addon/mode/overlay",
    "i18n"
], function(ko, CodeMirror) {
    "use strict";

    /**
     * @param {object} params         The params object
     * @param {object} params.proxy   Proxy for parent-child binding
     * @param {object} params.config  The config object ($root.Config)
     */

    function Editor(params) {

        const self = this;


        /* ----- VIEW MODELS ----- */

        self.currentTokenTypes = ko.observableArray([]);


        /* ----- Init CodeMirror ----- */

        const el = document.querySelector("#editor");

        const mode = {
            name: "markdown",
            highlightFormatting: true,
            xml: !params.config.Markdown.EscapeHTML()
        };

        const isMac = /Mac/.test(navigator.platform);
        const keyBold   = isMac ? "Cmd-B" : "Ctrl-B";
        const keyItalic = isMac ? "Cmd-I" : "Ctrl-I";

        const keyMap = {};
        keyMap[keyBold]   = self.toggleBold.bind(self);
        keyMap[keyItalic] = self.toggleItalic.bind(self);
        keyMap["Enter"]   = "newlineAndIndentContinueMarkdownList";

        self.CodeMirror = CodeMirror.fromTextArea(el, {
            mode:  {name: "violet", inner: mode},
            theme: "violet",
            indentUnit: 2,
            indentWithTabs: false,
            extraKeys: keyMap,
            lineWrapping: true,
            /* inputStyle: "contenteditable", */
            lineWiseCopyCut: false,
            configureMouse: () => ({addNew: false}),
            spellcheck: true,
            allowDropFileTypes: ["text/plain", "text/markdown"]
        });


        /* ----- CodeMirror Events ----- */

        self.CodeMirror.on("cursorActivity", function() {
            self.observeTokenTypeAtCursor();
        });

        self.CodeMirror.on("change", function () {
            params.proxy.hasChanges(true);
        });


        /* ----- COMPUTED ----- */

        self.isActive = function(name) {
            return ko.pureComputed(function() {
                return self.currentTokenTypes().includes(name);
            }, self);
        };

        /**
         * "trailingSpace" is built-in for markdown without any option to turn it off
         * So we use "showTrailingSpace" CSS class to conditionally show/hide line breaks
         */
        self.showTrailingSpace = ko.pureComputed(function() {
            return !params.config.Markdown.AutoLineBreak();
        }, self);


        /**
         * The links types to chose from (editLink dialog)
         */
        self.LinkTypeSelect = ko.pureComputed(function() {
            const types = [];
            types.push({ title: i18n("OptLinkTypePage"), value: "page" });
            types.push({ title: i18n("OptImageSrcMedia"), value: "media" });
            types.push({ title: i18n("OptLinkTypeEmail"), value: "email" });
            types.push({ title: i18n("OptLinkTypePhone"), value: "phone" });
            types.push({ title: i18n("OptLinkTypeURL"), value: "URL" });
            return types;
        }, self);

        /**
         * The src types to chose from (editImageLink dialog)
         */
        self.ImageSourceSelect = ko.pureComputed(function() {
            const types = [];
            types.push({ title: i18n("OptImageSrcMedia"), value: "srcMedia" });
            types.push({ title: i18n("OptImageSrcURL"), value: "srcURL" });
            return types;
        }, self);

        /**
         * The pages to chose from (editLink dialog)
         */
        self.PageSelect = ko.pureComputed(function() {
            const links = [];
            function walkSitemap(node, indent) {
                ko.utils.arrayForEach(node, function(page) {
                    links.push({
                        title: indent + page.title(),
                        value: page.url()
                    });
                    walkSitemap(page.children(), "⇢ " + indent); //→⇢
                });
            }
            walkSitemap(params.proxy.Sitemap(), "");
            return links;
        }, self);

        /**
         * The available plugins
         */
        self.PluginSelect = ko.pureComputed(function() {
            const plugins = [];
            ko.utils.arrayForEach(params.proxy.Plugins(), function(plugin) {
                if (plugin.enabled() && !plugin.hidden()) {
                    plugins.push({ title: plugin.name(), value: plugin.name() });
                }
            });
            return plugins;
        });


        /* ----- HELPERS ----- */

        self.newLink  = ko.observable(null);
        self.newImage = ko.observable(null);

        /**
         * Link view model - used by editLink/editImageLink dialog
         */
        self.Link = function(text, type, url) {
            this.type  = ko.observable(type);
            this.text  = ko.observable(text);
            this.url   = ko.observable(url);
            this.route = ko.observable(null);
            this.title = ko.observable(null);
        };


        /* ----- REGISTER DIALOGS ----- */

        if (!ko.components.isRegistered("editLink")) {
            ko.components.register("editLink", {
                viewModel: { require: "dialog/dialog" },
                template: { require: "text!dialog/link.html" }
            });
        }

        if (!ko.components.isRegistered("editImage")) {
            ko.components.register("editImage", {
                viewModel: { require: "dialog/dialog" },
                template: { require: "text!dialog/image.html" }
            });
        }

        if (!ko.components.isRegistered("mediabrowser")) {
            ko.components.register("mediabrowser", { require: "components/mediabrowser/mediabrowser" });
        }


        /* ----- override proxy ----- */

        params.proxy["getValue"] = self.getValue.bind(self);
        params.proxy["setValue"] = self.setValue.bind(self);

        params.proxy["ready"](true);

    }

    Editor.prototype.getValue = function() {
        const self = this;
        return self.CodeMirror.getValue();
    };

    Editor.prototype.setValue = function(text) {
        const self = this;
        return self.CodeMirror.setValue(text);
    };

    Editor.prototype.observeTokenTypeAtCursor = function() {
        const self = this;

        self.currentTokenTypes([]);

        const pos = self.CodeMirror.getCursor("from");

        let tokenTypes = self.CodeMirror.getTokenTypeAt(pos);

        if (!tokenTypes) {
            return;
        }

        const editorTypes = ["header", "strong", "em", "quote", "variable-2", "variable-3", "link", "image"];

        tokenTypes = tokenTypes.split(" ");
        ko.utils.arrayForEach(tokenTypes, function(type) {
            if (editorTypes.includes(type)) {
                if (type === "variable-2" || type === "variable-3") {
                    const text = self.CodeMirror.getLine(pos.line);
                    if (/^\s*\d+\.\s/.test(text)) {
                        self.currentTokenTypes.push("ordered-list");
                    } else {
                        self.currentTokenTypes.push("unordered-list");
                    }
                } else {
                    self.currentTokenTypes.push(type);
                }
            }
        });
    };

    /* --- toggle block types --- */

    Editor.prototype.toggleHeader = function() {
        const self = this;
        self._toggleBlockType("header");
    };

    Editor.prototype.toggleQuote = function() {
        const self = this;
        self._toggleBlockType("quote");
    };

    Editor.prototype.toggleUnorderedList = function() {
        const self = this;
        self._toggleBlockType("unordered-list");
    };

    Editor.prototype.toggleOrderedList = function() {
        const self = this;
        self._toggleBlockType("ordered-list");
    };

    /* --- toggle range types --- */

    Editor.prototype.toggleBold = function() {
        const self = this;
        self._toggleRangeType("strong");
    };

    Editor.prototype.toggleItalic = function() {
        const self = this;
        self._toggleRangeType("em");
    };

    /* --- links/images/plugins --- */

    Editor.prototype.createLink = function() {
        const self = this;
        const text = self.CodeMirror.doc.getSelection();
        const type = text ? ((text.indexOf("@") > -1) ? "email" : ((/^[\d\s+-/]+$/.test(text)) ? "phone" : null)) : null;
        const url = type === "email" ? text : (type === "phone" ? "+XX"+text.replace(/[\s-/]/g, "").replace(/^0/, "") : null);
        self.newLink(new self.Link(text, type, url));
    };

    Editor.prototype.insertImage = function() {
        const self = this;
        const text = self.CodeMirror.doc.getSelection();
        self.newImage(new self.Link(text));
    };

    Editor.prototype.insertPlugin = function(data) {
        const self = this;
        const text = self.CodeMirror.doc.getSelection();
        self.CodeMirror.doc.replaceSelection(
            "{{" + data +
            (text !== "" ? "|" + text : "") +
            "}}"
        );
        self.CodeMirror.focus();
    };

    Editor.prototype.markdownGuide = function() {
        window.open("https://www.markdownguide.org/basic-syntax/", "_blank");
    };


    /* --- private functions --- */

    Editor.prototype._toggleBlockType = function(type) {
        const self = this;

        self.CodeMirror.startOperation();

        const hasSelection = self.CodeMirror.doc.somethingSelected();

        const posFrom = self.CodeMirror.getCursor("from");
        const posTo   = self.CodeMirror.getCursor("to");

        const lineFrom = posFrom.line;
        const lineTo   = posTo.line;

        let isMixed = false;
        if (lineFrom !== lineTo) {
            for (let line = lineFrom; line <= lineTo && isMixed !== true; line++) {
                const text = self.CodeMirror.doc.getLine(line).trim();
                if (text === "" && line === lineTo) {
                    break;
                }
                const char = text.replace(/^(?:([>*+-])|(\d+\.))?(.*)/, (noop, p1, p2) => {
                    return p2 ? "digit" : p1 || "";
                });
                isMixed = (isMixed !== false && isMixed !== char) ? true : char;
            }
            isMixed = (isMixed === true);
        }

        for (let line = lineFrom; line <= lineTo; line++) {

            let text = self.CodeMirror.doc.getLine(line);

            if (text.trim() === "" && line !== lineFrom && line === lineTo) {
                break;
            }

            const rangeStart = {line: line, ch: 0};
            const rangeEnd = {line: line, ch: text.length};

            switch (type) {
            case "header":
                text = self._toggleHeader(text);
                break;
            case "unordered-list":
                text = isMixed ? text.replace(/^(\s{0,3})([*+-]|\d+\.)\s+/, "$1") : text;
                text = self._toggleList(text, null);
                break;
            case "ordered-list":
                text = isMixed ? text.replace(/^(\s{0,3})([*+-]|\d+\.)\s+/, "$1") : text;
                text = self._toggleList(text, line-lineFrom+1);
                break;
            case "quote":
                text = isMixed ? text.replace(/^(\s{0,3})>\s*/, "$1") : text;
                text = self._toggleQuote(text);
                break;
            default:
                self.CodeMirror.endOperation();
                self.CodeMirror.focus();
                return;
            }

            self.CodeMirror.doc.replaceRange(text, rangeStart, rangeEnd);
        }

        if (hasSelection) {
            const selectionStart = {line: lineFrom, ch: 0};
            const selectionEnd = {line: lineTo, ch: 99999999999999};
            self.CodeMirror.doc.setSelection(selectionStart, selectionEnd);
        }

        self.CodeMirror.endOperation();
        self.CodeMirror.focus();
    };

    Editor.prototype._toggleHeader = function(line) {
        const match = /^(#{0,6})\s+(.*)/.exec(line);
        if (match) {
            let headerLevel = match[1];
            let headerText = match[2];
            headerLevel = headerLevel === "######" ? "" : headerLevel+"#";
            headerText = headerLevel ? " " + headerText : headerText;
            return headerLevel + headerText;
        } else {
            return "# " + line;
        }
    };

    Editor.prototype._toggleList = function(line, listIndex) {
        const match = /^(\s{0,3})([*+-]|\d+\.)\s+(.*)/.exec(line);
        if (match) {                                        // is list item
            const listIndent = match[1];
            const listType   = match[2];
            const listText   = match[3];
            if (/^\s{0,3}[*+-]/.test(listType)) {           // is unordered
                if (listIndex !== null) {                   // should be ordered
                    return listIndent + listIndex + ". " + listText;
                } else {                                    // else un-list
                    return listText;
                }
            } else {                                        // is ordered
                if (listIndex === null) {                   // should be unordered
                    return listIndent + "* " + listText;
                } else {                                    // else un-list
                    return listText;
                }
            }
        } else {                                            // not list item
            if (listIndex !== null) {
                return listIndex + ". " + line.replace(/^\s+/, "");
            } else {
                return "* " + line.replace(/^\s+/, "");
            }
        }
    };

    Editor.prototype._toggleQuote = function(line) {
        const match = /^(\s{0,3})>\s*(.*)/.exec(line);
        if (match) {
            const quoteIndent = match[1];
            const quoteText   = match[2];
            return quoteIndent + quoteText;
        } else {
            return "> " + line.replace(/^\s+/, "");
        }
    };

    Editor.prototype._toggleRangeType = function(type) {
        const self = this;

        const posFrom  = self.CodeMirror.getCursor("from");
        const posTo    = self.CodeMirror.getCursor("to");

        /* --- No Selection => just insert chars --- */

        if (!self.CodeMirror.doc.somethingSelected()) {

            if (type === "strong") {
                self.CodeMirror.doc.replaceSelection("****");
                self.CodeMirror.doc.setCursor({
                    line: posFrom.line,
                    ch: posFrom.ch+2,
                });
            } else if (type === "em") {
                self.CodeMirror.doc.replaceSelection("**");
                self.CodeMirror.doc.setCursor({
                    line: posFrom.line,
                    ch: posFrom.ch+1,
                });
            }
            self.CodeMirror.focus();
            return;
        }

        /* --- Has Selection => more complicated --- */

        let text = "";

        // token at cursor determines toggle on/off
        const tokenAt = self.CodeMirror.getTokenTypeAt(posFrom);
        const toggleOn = !(tokenAt ? tokenAt.split(" ") : []).includes(type);

        const lineFrom = posFrom.line;
        const lineTo   = posTo.line;

        // if toggleOn -> the chars to insert
        let char = "";
        if (toggleOn && type === "strong") {
            char = "**";
        } else if (toggleOn && type === "em") {
            char = "*";
        }

        let selectionStart = 0;
        let selectionEnd = 0;

        for (let line = lineFrom; line <= lineTo; line++) {
            const lineText = self.CodeMirror.doc.getLine(line);

            // blank line => new paragraph
            if (lineText.trim() === "") {
                if (line !== lineFrom) {
                    // end previous line with chars
                    text = text.replace(/\s*\n$/, char + "\n");
                    if (line === lineTo) {
                        break;
                    }
                    // start next(!) line with chars
                    text += "\n" + char;
                    selectionEnd += char.length;
                }
                continue;
            }

            // get selected text of current line
            // include chars on selection boundary
            let selectedText = lineText;
            if (line === lineTo) {
                const match = /^[*_]+/.exec(lineText.slice(posTo.ch));
                if (match) {
                    posTo.ch += match[0].length;
                }
                selectedText = selectedText.slice(0, posTo.ch);
            }
            if (line === lineFrom) {
                const match = /[*_]+$/.exec(lineText.slice(0, posFrom.ch));
                if (match) {
                    posFrom.ch -= match[0].length;
                }
                selectedText = selectedText.slice(posFrom.ch);
            }

            // clean selected text
            if (type === "strong") {
                selectedText = selectedText.replaceAll(/\*\*([\s\S]*?)\*\*/g, "$1");
                selectedText = selectedText.replaceAll(/__([\s\S]*?)__/g, "$1");
            } else if (type === "em") {
                selectedText = selectedText.replaceAll(/\*([\s\S]*?)\*(?!\*)/g, "$1");
                selectedText = selectedText.replaceAll(/_([\s\S]*?)_(?!_)/g, "$1");
            }

            // handle text before selection (if first line)
            // check if the text before contains a token that needs to be closed
            if (line === lineFrom) {
                let textBefore = lineText.slice(0, posFrom.ch);
                const tokenBefore = self.CodeMirror.getTokenTypeAt({line: posFrom.line, ch: posFrom.ch-1});
                const tokenContainsType = (tokenBefore ? tokenBefore.split(" ") : []).includes(type);
                if (tokenContainsType && !/[*_]\s*$/.test(textBefore)) {
                    textBefore = textBefore.replace(/\s+$/, "") + (type === "strong" ? "**" : "*")  + " ";
                }
                text += textBefore + char;
                selectionStart = text.length;
            }

            text += selectedText;

            // handle text after selection (if last line)
            if (line === lineTo) {
                let textAfter = lineText.slice(posTo.ch);
                const tokenAfter = self.CodeMirror.getTokenTypeAt({line: posTo.line, ch: posTo.ch+1});
                const tokenContainsType = (tokenAfter ? tokenAfter.split(" ") : []).includes(type);
                if (tokenContainsType && !/^\s*[*_]/.test(textAfter)) {
                    textAfter = " " + (type === "strong" ? "**" : "*") + textAfter.replace(/^\s+/, "");
                    textAfter = char + textAfter;
                }
                text += char + textAfter;
                selectionEnd += (lineFrom === lineTo ? selectionStart : 0) + selectedText.length;
            }

            text += (line < lineTo ? "\n" : "");
        }

        self.CodeMirror.doc.replaceRange(text,
            {line: posFrom.line, ch: 0},
            {line: posTo.line, ch: 99999999999999}
        );

        self.CodeMirror.doc.setSelection(
            {line: posFrom.line, ch: selectionStart},
            {line: posTo.line, ch: selectionEnd}
        );

        self.CodeMirror.focus();
    };

    Editor.prototype.submitDialog = function(data) {
        const self = this;

        const vm = ko.toJS(data);
        const url = (vm.type === "page" ? vm.route : vm.url || "");

        let pattern;

        switch(vm.type) {
        case "URL":
            pattern = "[#text#](#url##title#)";
            break;
        case "email":
            pattern = "[#text#](mailto:#url#)";
            break;
        case "phone":
            pattern = "[#text#](tel:#url#)";
            break;
        case "srcMedia":
        case "srcURL":
            pattern = "![#text#](#url##title#)";
            break;
        default:
            pattern = "[#text#](#url##title#)";
            break;
        }

        const link = pattern.replaceAll(/#text#|#url#|#title#/g, (match) => {
            switch (match) {
            case "#text#":
                return vm.text || "";
            case "#url#":
                return (
                    (vm.type === "URL" || vm.type === "srcURL")
                    && !/^[a-z]*:\/\//.test(url)
                        ? "https://" + url
                        : url
                );
            case "#title#":
                return vm.title ? ' "'+vm.title+'"' : "";
            default:
                return "";
            }
        });

        const pos = self.CodeMirror.getCursor("from");

        self.CodeMirror.doc.replaceSelection(link);
        self.CodeMirror.focus();

        if (!vm.text) {
            self.CodeMirror.setCursor({
                line: pos.line,
                ch: pos.ch + (link.startsWith("!") ? 2 : 1)
            });
        }
    };

    Editor.prototype.resetDialog = function(item, changedProps) {
        const link = ko.unwrap(item);
        if (changedProps.includes("type")) {
            link.url(null);
        }
        return true;
    };

    Editor.prototype.dispose = function() {
        const self = this;
        if (self.CodeMirror) {
            self.CodeMirror.toTextArea();
            self.CodeMirror = null;
        }
    };

    return {
        viewModel: Editor,
        template: { require: "text!components/editor/editor.html" }
    };

});
