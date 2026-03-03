// CodeMirror GraphQL mode
// Lightweight standalone mode for GraphQL syntax highlighting.
// No external dependencies beyond CodeMirror core.

(function(mod) {
  if (typeof exports == "object" && typeof module == "object") // CommonJS
    mod(require("codemirror"));
  else if (typeof define == "function" && define.amd) // AMD
    define(["codemirror"], mod);
  else // Plain browser env
    mod(window.kursoCM || CodeMirror);
})(function(CodeMirror) {
  "use strict";

  var keywords = /^(query|mutation|subscription|fragment|on|type|interface|union|enum|input|extend|schema|directive|scalar|implements|repeatable|true|false|null)\b/;
  var builtinTypes = /^(String|Int|Float|Boolean|ID)\b/;

  CodeMirror.defineMode("graphql", function() {
    return {
      startState: function() {
        return { inString: false, blockString: false };
      },

      token: function(stream, state) {
        // Block string (""")
        if (state.blockString) {
          if (stream.match('"""')) {
            state.blockString = false;
          } else {
            stream.next();
          }
          return "string";
        }

        // Regular string
        if (state.inString) {
          if (stream.eat('"')) {
            state.inString = false;
          } else if (stream.eat('\\')) {
            stream.next();
          } else {
            stream.next();
          }
          return "string";
        }

        // Whitespace
        if (stream.eatSpace()) return null;

        // Block string start
        if (stream.match('"""')) {
          state.blockString = true;
          return "string";
        }

        // Regular string start
        if (stream.eat('"')) {
          state.inString = true;
          return "string";
        }

        // Line comment
        if (stream.eat('#')) {
          stream.skipToEnd();
          return "comment";
        }

        // Variables: $name
        if (stream.eat('$')) {
          stream.eatWhile(/[\w]/);
          return "variable-2";
        }

        // Directives: @name
        if (stream.eat('@')) {
          stream.eatWhile(/[\w]/);
          return "meta";
        }

        // Spread operator
        if (stream.match("...")) {
          return "punctuation";
        }

        // Numbers
        if (stream.match(/^-?\d+(\.\d+)?([eE][+-]?\d+)?/)) {
          return "number";
        }

        // Punctuation
        if (stream.match(/^[{}\[\]():!|=]/)) {
          return "punctuation";
        }

        // Keywords
        if (stream.match(keywords)) {
          return "keyword";
        }

        // Built-in scalar types
        if (stream.match(builtinTypes)) {
          return "builtin";
        }

        // Identifiers (field names, type names, argument names)
        if (stream.match(/^\w+/)) {
          return "variable";
        }

        stream.next();
        return null;
      }
    };
  });

  CodeMirror.defineMIME("application/graphql", "graphql");
  CodeMirror.defineMIME("text/x-graphql", "graphql");
});
