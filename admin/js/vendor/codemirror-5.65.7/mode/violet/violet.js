// CodeMirror, copyright (c) by Marijn Haverbeke and others
// Distributed under an MIT license: https://codemirror.net/LICENSE


(function(mod) {
    if (typeof exports === "object" && typeof module === "object") { // CommonJS
        mod(require("../../lib/codemirror"), require("../../addon/mode/overlay"));
    } else if (typeof define === "function" && define.amd) { // AMD
        define(["../../lib/codemirror", "../../addon/mode/overlay"], mod);
    } else { // Plain browser env
        /* eslint-disable-next-line no-undef */
        mod(CodeMirror);
    }
})(function(CodeMirror) {
    "use strict";

    CodeMirror.defineMode("violet", function(config, parserConfig) {

        function tokenize(stream, state) {

            /* -- Markdown Extras -- */

            if (stream.match(/\[\^[A-Za-z0-9-_]+\](?!:)/, true)) {
                return "footnote-ref";
            }

            if (stream.match(/\[\^[A-Za-z0-9-_]+\]:/, false)) {
                state.tokenize = tokenizeFootnote;
                return null;
            }

            if (stream.match(/{\s*(?:[#.][-\w]+\s*|[\w]+=[\\\w_]+\s*)+}\s*$/, true)) {
                stream.skipToEnd();
                return "attribute";
            }

            if (stream.match(/\*\[[A-Za-z0-9]+\]:/, false)) {
                state.tokenize = tokenizeAbbreviation;
                return null;
            }

            if (stream.match("~~", true)) {
                state.tokenize = tokenizeStrikethrough;
                return "strikethrough";
            }

            /* -- violetCMS stuff -- */

            if (stream.match(/{#[^#}]+#}/, true)) {
                return "violet-comment";
            }

            if (stream.match("{{", true)) {
                state.tokenize = tokenizePlugin;
                return "violet-plugin";
            }

            stream.next();
            return null;
        }

        // Apply plugin syntax highlighting
        function tokenizePlugin(stream, state) {

            // Ignore all white spaces
            if (stream.eatSpace()) {
                return null;
            }

            // If found closing tag reset
            if (stream.match("}}", true)) {
                state.waitValue = null;
                state.waitPipe = null;
                state.tokenize = tokenize;
                return "violet-plugin";
            }

            // Attempt to match a pipe that precedes a value
            if (state.waitPipe) {

                if (stream.peek() !== "|") {
                    stream.next();
                    return "violet-plugin-name";
                }

                if (stream.eat("|")) {
                    state.waitPipe = false;
                    state.waitValue = true;
                    return "violet-plugin";
                } else {
                    throw Error ("Unexpected error while waiting for value.");
                }
            }

            // Highlight value
            if (state.waitValue) {
                stream.next();
                return "violet-plugin-value";
            }

            // If nothing was found, advance to the next character
            stream.next();
            state.waitPipe = true;
            return "violet-plugin-name";
        }

        function tokenizeStrikethrough(stream, state) {
            if (stream.match(/^.*?~~/, true)) {
                state.tokenize = tokenize;
            } else {
                stream.skipToEnd();
            }
            return "strikethrough";
        }

        function tokenizeFootnote(stream, state) {
            if (stream.match(/\[\^[A-Za-z0-9-_]+\]:/, true)) {
                return "footnote";
            }

            if (stream.eatSpace()) {
                return null;
            }

            stream.skipToEnd();
            state.tokenize = tokenize;
            return "footnote-text";
        }

        function tokenizeAbbreviation(stream, state) {
            if (stream.match(/\*\[[A-Za-z0-9]+\]:/, true)) {
                return "abbreviation";
            }

            if (stream.eatSpace()) {
                return null;
            }

            stream.skipToEnd();
            state.tokenize = tokenize;
            return "abbreviation-text";
        }

        const violetOverlay =  {
            startState: function () {
                return {
                    waitPipe: false,
                    waitValue: false,
                    tokenize: tokenize
                };
            },
            token: function (stream, state) {
                return state.tokenize(stream, state);
            }
        };

        return CodeMirror.overlayMode(CodeMirror.getMode(config, parserConfig.inner), violetOverlay, true);
    });
});
