(() => {
  var __create = Object.create;
  var __defProp = Object.defineProperty;
  var __getOwnPropDesc = Object.getOwnPropertyDescriptor;
  var __getOwnPropNames = Object.getOwnPropertyNames;
  var __getProtoOf = Object.getPrototypeOf;
  var __hasOwnProp = Object.prototype.hasOwnProperty;
  var __commonJS = (cb, mod) => function __require() {
    return mod || (0, cb[__getOwnPropNames(cb)[0]])((mod = { exports: {} }).exports, mod), mod.exports;
  };
  var __copyProps = (to, from, except, desc) => {
    if (from && typeof from === "object" || typeof from === "function") {
      for (let key of __getOwnPropNames(from))
        if (!__hasOwnProp.call(to, key) && key !== except)
          __defProp(to, key, { get: () => from[key], enumerable: !(desc = __getOwnPropDesc(from, key)) || desc.enumerable });
    }
    return to;
  };
  var __toESM = (mod, isNodeMode, target) => (target = mod != null ? __create(__getProtoOf(mod)) : {}, __copyProps(isNodeMode || !mod || !mod.__esModule ? __defProp(target, "default", { value: mod, enumerable: true }) : target, mod));

  // src/Resources/app/administration/node_modules/diff-match-patch/index.js
  var require_diff_match_patch = __commonJS({
    "src/Resources/app/administration/node_modules/diff-match-patch/index.js"(exports, module) {
      var diff_match_patch = function() {
        this.Diff_Timeout = 1;
        this.Diff_EditCost = 4;
        this.Match_Threshold = 0.5;
        this.Match_Distance = 1e3;
        this.Patch_DeleteThreshold = 0.5;
        this.Patch_Margin = 4;
        this.Match_MaxBits = 32;
      };
      var DIFF_DELETE = -1;
      var DIFF_INSERT = 1;
      var DIFF_EQUAL = 0;
      diff_match_patch.Diff = function(op, text) {
        return [op, text];
      };
      diff_match_patch.prototype.diff_main = function(text1, text2, opt_checklines, opt_deadline) {
        if (typeof opt_deadline == "undefined") {
          if (this.Diff_Timeout <= 0) {
            opt_deadline = Number.MAX_VALUE;
          } else {
            opt_deadline = new Date().getTime() + this.Diff_Timeout * 1e3;
          }
        }
        var deadline = opt_deadline;
        if (text1 == null || text2 == null) {
          throw new Error("Null input. (diff_main)");
        }
        if (text1 == text2) {
          if (text1) {
            return [new diff_match_patch.Diff(DIFF_EQUAL, text1)];
          }
          return [];
        }
        if (typeof opt_checklines == "undefined") {
          opt_checklines = true;
        }
        var checklines = opt_checklines;
        var commonlength = this.diff_commonPrefix(text1, text2);
        var commonprefix = text1.substring(0, commonlength);
        text1 = text1.substring(commonlength);
        text2 = text2.substring(commonlength);
        commonlength = this.diff_commonSuffix(text1, text2);
        var commonsuffix = text1.substring(text1.length - commonlength);
        text1 = text1.substring(0, text1.length - commonlength);
        text2 = text2.substring(0, text2.length - commonlength);
        var diffs = this.diff_compute_(text1, text2, checklines, deadline);
        if (commonprefix) {
          diffs.unshift(new diff_match_patch.Diff(DIFF_EQUAL, commonprefix));
        }
        if (commonsuffix) {
          diffs.push(new diff_match_patch.Diff(DIFF_EQUAL, commonsuffix));
        }
        this.diff_cleanupMerge(diffs);
        return diffs;
      };
      diff_match_patch.prototype.diff_compute_ = function(text1, text2, checklines, deadline) {
        var diffs;
        if (!text1) {
          return [new diff_match_patch.Diff(DIFF_INSERT, text2)];
        }
        if (!text2) {
          return [new diff_match_patch.Diff(DIFF_DELETE, text1)];
        }
        var longtext = text1.length > text2.length ? text1 : text2;
        var shorttext = text1.length > text2.length ? text2 : text1;
        var i = longtext.indexOf(shorttext);
        if (i != -1) {
          diffs = [
            new diff_match_patch.Diff(DIFF_INSERT, longtext.substring(0, i)),
            new diff_match_patch.Diff(DIFF_EQUAL, shorttext),
            new diff_match_patch.Diff(DIFF_INSERT, longtext.substring(i + shorttext.length))
          ];
          if (text1.length > text2.length) {
            diffs[0][0] = diffs[2][0] = DIFF_DELETE;
          }
          return diffs;
        }
        if (shorttext.length == 1) {
          return [
            new diff_match_patch.Diff(DIFF_DELETE, text1),
            new diff_match_patch.Diff(DIFF_INSERT, text2)
          ];
        }
        var hm = this.diff_halfMatch_(text1, text2);
        if (hm) {
          var text1_a = hm[0];
          var text1_b = hm[1];
          var text2_a = hm[2];
          var text2_b = hm[3];
          var mid_common = hm[4];
          var diffs_a = this.diff_main(text1_a, text2_a, checklines, deadline);
          var diffs_b = this.diff_main(text1_b, text2_b, checklines, deadline);
          return diffs_a.concat([new diff_match_patch.Diff(DIFF_EQUAL, mid_common)], diffs_b);
        }
        if (checklines && text1.length > 100 && text2.length > 100) {
          return this.diff_lineMode_(text1, text2, deadline);
        }
        return this.diff_bisect_(text1, text2, deadline);
      };
      diff_match_patch.prototype.diff_lineMode_ = function(text1, text2, deadline) {
        var a = this.diff_linesToChars_(text1, text2);
        text1 = a.chars1;
        text2 = a.chars2;
        var linearray = a.lineArray;
        var diffs = this.diff_main(text1, text2, false, deadline);
        this.diff_charsToLines_(diffs, linearray);
        this.diff_cleanupSemantic(diffs);
        diffs.push(new diff_match_patch.Diff(DIFF_EQUAL, ""));
        var pointer = 0;
        var count_delete = 0;
        var count_insert = 0;
        var text_delete = "";
        var text_insert = "";
        while (pointer < diffs.length) {
          switch (diffs[pointer][0]) {
            case DIFF_INSERT:
              count_insert++;
              text_insert += diffs[pointer][1];
              break;
            case DIFF_DELETE:
              count_delete++;
              text_delete += diffs[pointer][1];
              break;
            case DIFF_EQUAL:
              if (count_delete >= 1 && count_insert >= 1) {
                diffs.splice(pointer - count_delete - count_insert, count_delete + count_insert);
                pointer = pointer - count_delete - count_insert;
                var subDiff = this.diff_main(text_delete, text_insert, false, deadline);
                for (var j = subDiff.length - 1; j >= 0; j--) {
                  diffs.splice(pointer, 0, subDiff[j]);
                }
                pointer = pointer + subDiff.length;
              }
              count_insert = 0;
              count_delete = 0;
              text_delete = "";
              text_insert = "";
              break;
          }
          pointer++;
        }
        diffs.pop();
        return diffs;
      };
      diff_match_patch.prototype.diff_bisect_ = function(text1, text2, deadline) {
        var text1_length = text1.length;
        var text2_length = text2.length;
        var max_d = Math.ceil((text1_length + text2_length) / 2);
        var v_offset = max_d;
        var v_length = 2 * max_d;
        var v1 = new Array(v_length);
        var v2 = new Array(v_length);
        for (var x = 0; x < v_length; x++) {
          v1[x] = -1;
          v2[x] = -1;
        }
        v1[v_offset + 1] = 0;
        v2[v_offset + 1] = 0;
        var delta = text1_length - text2_length;
        var front = delta % 2 != 0;
        var k1start = 0;
        var k1end = 0;
        var k2start = 0;
        var k2end = 0;
        for (var d = 0; d < max_d; d++) {
          if (new Date().getTime() > deadline) {
            break;
          }
          for (var k1 = -d + k1start; k1 <= d - k1end; k1 += 2) {
            var k1_offset = v_offset + k1;
            var x1;
            if (k1 == -d || k1 != d && v1[k1_offset - 1] < v1[k1_offset + 1]) {
              x1 = v1[k1_offset + 1];
            } else {
              x1 = v1[k1_offset - 1] + 1;
            }
            var y1 = x1 - k1;
            while (x1 < text1_length && y1 < text2_length && text1.charAt(x1) == text2.charAt(y1)) {
              x1++;
              y1++;
            }
            v1[k1_offset] = x1;
            if (x1 > text1_length) {
              k1end += 2;
            } else if (y1 > text2_length) {
              k1start += 2;
            } else if (front) {
              var k2_offset = v_offset + delta - k1;
              if (k2_offset >= 0 && k2_offset < v_length && v2[k2_offset] != -1) {
                var x2 = text1_length - v2[k2_offset];
                if (x1 >= x2) {
                  return this.diff_bisectSplit_(text1, text2, x1, y1, deadline);
                }
              }
            }
          }
          for (var k2 = -d + k2start; k2 <= d - k2end; k2 += 2) {
            var k2_offset = v_offset + k2;
            var x2;
            if (k2 == -d || k2 != d && v2[k2_offset - 1] < v2[k2_offset + 1]) {
              x2 = v2[k2_offset + 1];
            } else {
              x2 = v2[k2_offset - 1] + 1;
            }
            var y2 = x2 - k2;
            while (x2 < text1_length && y2 < text2_length && text1.charAt(text1_length - x2 - 1) == text2.charAt(text2_length - y2 - 1)) {
              x2++;
              y2++;
            }
            v2[k2_offset] = x2;
            if (x2 > text1_length) {
              k2end += 2;
            } else if (y2 > text2_length) {
              k2start += 2;
            } else if (!front) {
              var k1_offset = v_offset + delta - k2;
              if (k1_offset >= 0 && k1_offset < v_length && v1[k1_offset] != -1) {
                var x1 = v1[k1_offset];
                var y1 = v_offset + x1 - k1_offset;
                x2 = text1_length - x2;
                if (x1 >= x2) {
                  return this.diff_bisectSplit_(text1, text2, x1, y1, deadline);
                }
              }
            }
          }
        }
        return [
          new diff_match_patch.Diff(DIFF_DELETE, text1),
          new diff_match_patch.Diff(DIFF_INSERT, text2)
        ];
      };
      diff_match_patch.prototype.diff_bisectSplit_ = function(text1, text2, x, y, deadline) {
        var text1a = text1.substring(0, x);
        var text2a = text2.substring(0, y);
        var text1b = text1.substring(x);
        var text2b = text2.substring(y);
        var diffs = this.diff_main(text1a, text2a, false, deadline);
        var diffsb = this.diff_main(text1b, text2b, false, deadline);
        return diffs.concat(diffsb);
      };
      diff_match_patch.prototype.diff_linesToChars_ = function(text1, text2) {
        var lineArray = [];
        var lineHash = {};
        lineArray[0] = "";
        function diff_linesToCharsMunge_(text) {
          var chars = "";
          var lineStart = 0;
          var lineEnd = -1;
          var lineArrayLength = lineArray.length;
          while (lineEnd < text.length - 1) {
            lineEnd = text.indexOf("\n", lineStart);
            if (lineEnd == -1) {
              lineEnd = text.length - 1;
            }
            var line = text.substring(lineStart, lineEnd + 1);
            if (lineHash.hasOwnProperty ? lineHash.hasOwnProperty(line) : lineHash[line] !== void 0) {
              chars += String.fromCharCode(lineHash[line]);
            } else {
              if (lineArrayLength == maxLines) {
                line = text.substring(lineStart);
                lineEnd = text.length;
              }
              chars += String.fromCharCode(lineArrayLength);
              lineHash[line] = lineArrayLength;
              lineArray[lineArrayLength++] = line;
            }
            lineStart = lineEnd + 1;
          }
          return chars;
        }
        var maxLines = 4e4;
        var chars1 = diff_linesToCharsMunge_(text1);
        maxLines = 65535;
        var chars2 = diff_linesToCharsMunge_(text2);
        return { chars1, chars2, lineArray };
      };
      diff_match_patch.prototype.diff_charsToLines_ = function(diffs, lineArray) {
        for (var i = 0; i < diffs.length; i++) {
          var chars = diffs[i][1];
          var text = [];
          for (var j = 0; j < chars.length; j++) {
            text[j] = lineArray[chars.charCodeAt(j)];
          }
          diffs[i][1] = text.join("");
        }
      };
      diff_match_patch.prototype.diff_commonPrefix = function(text1, text2) {
        if (!text1 || !text2 || text1.charAt(0) != text2.charAt(0)) {
          return 0;
        }
        var pointermin = 0;
        var pointermax = Math.min(text1.length, text2.length);
        var pointermid = pointermax;
        var pointerstart = 0;
        while (pointermin < pointermid) {
          if (text1.substring(pointerstart, pointermid) == text2.substring(pointerstart, pointermid)) {
            pointermin = pointermid;
            pointerstart = pointermin;
          } else {
            pointermax = pointermid;
          }
          pointermid = Math.floor((pointermax - pointermin) / 2 + pointermin);
        }
        return pointermid;
      };
      diff_match_patch.prototype.diff_commonSuffix = function(text1, text2) {
        if (!text1 || !text2 || text1.charAt(text1.length - 1) != text2.charAt(text2.length - 1)) {
          return 0;
        }
        var pointermin = 0;
        var pointermax = Math.min(text1.length, text2.length);
        var pointermid = pointermax;
        var pointerend = 0;
        while (pointermin < pointermid) {
          if (text1.substring(text1.length - pointermid, text1.length - pointerend) == text2.substring(text2.length - pointermid, text2.length - pointerend)) {
            pointermin = pointermid;
            pointerend = pointermin;
          } else {
            pointermax = pointermid;
          }
          pointermid = Math.floor((pointermax - pointermin) / 2 + pointermin);
        }
        return pointermid;
      };
      diff_match_patch.prototype.diff_commonOverlap_ = function(text1, text2) {
        var text1_length = text1.length;
        var text2_length = text2.length;
        if (text1_length == 0 || text2_length == 0) {
          return 0;
        }
        if (text1_length > text2_length) {
          text1 = text1.substring(text1_length - text2_length);
        } else if (text1_length < text2_length) {
          text2 = text2.substring(0, text1_length);
        }
        var text_length = Math.min(text1_length, text2_length);
        if (text1 == text2) {
          return text_length;
        }
        var best = 0;
        var length = 1;
        while (true) {
          var pattern = text1.substring(text_length - length);
          var found = text2.indexOf(pattern);
          if (found == -1) {
            return best;
          }
          length += found;
          if (found == 0 || text1.substring(text_length - length) == text2.substring(0, length)) {
            best = length;
            length++;
          }
        }
      };
      diff_match_patch.prototype.diff_halfMatch_ = function(text1, text2) {
        if (this.Diff_Timeout <= 0) {
          return null;
        }
        var longtext = text1.length > text2.length ? text1 : text2;
        var shorttext = text1.length > text2.length ? text2 : text1;
        if (longtext.length < 4 || shorttext.length * 2 < longtext.length) {
          return null;
        }
        var dmp = this;
        function diff_halfMatchI_(longtext2, shorttext2, i) {
          var seed = longtext2.substring(i, i + Math.floor(longtext2.length / 4));
          var j = -1;
          var best_common = "";
          var best_longtext_a, best_longtext_b, best_shorttext_a, best_shorttext_b;
          while ((j = shorttext2.indexOf(seed, j + 1)) != -1) {
            var prefixLength = dmp.diff_commonPrefix(longtext2.substring(i), shorttext2.substring(j));
            var suffixLength = dmp.diff_commonSuffix(longtext2.substring(0, i), shorttext2.substring(0, j));
            if (best_common.length < suffixLength + prefixLength) {
              best_common = shorttext2.substring(j - suffixLength, j) + shorttext2.substring(j, j + prefixLength);
              best_longtext_a = longtext2.substring(0, i - suffixLength);
              best_longtext_b = longtext2.substring(i + prefixLength);
              best_shorttext_a = shorttext2.substring(0, j - suffixLength);
              best_shorttext_b = shorttext2.substring(j + prefixLength);
            }
          }
          if (best_common.length * 2 >= longtext2.length) {
            return [
              best_longtext_a,
              best_longtext_b,
              best_shorttext_a,
              best_shorttext_b,
              best_common
            ];
          } else {
            return null;
          }
        }
        var hm1 = diff_halfMatchI_(longtext, shorttext, Math.ceil(longtext.length / 4));
        var hm2 = diff_halfMatchI_(longtext, shorttext, Math.ceil(longtext.length / 2));
        var hm;
        if (!hm1 && !hm2) {
          return null;
        } else if (!hm2) {
          hm = hm1;
        } else if (!hm1) {
          hm = hm2;
        } else {
          hm = hm1[4].length > hm2[4].length ? hm1 : hm2;
        }
        var text1_a, text1_b, text2_a, text2_b;
        if (text1.length > text2.length) {
          text1_a = hm[0];
          text1_b = hm[1];
          text2_a = hm[2];
          text2_b = hm[3];
        } else {
          text2_a = hm[0];
          text2_b = hm[1];
          text1_a = hm[2];
          text1_b = hm[3];
        }
        var mid_common = hm[4];
        return [text1_a, text1_b, text2_a, text2_b, mid_common];
      };
      diff_match_patch.prototype.diff_cleanupSemantic = function(diffs) {
        var changes = false;
        var equalities = [];
        var equalitiesLength = 0;
        var lastEquality = null;
        var pointer = 0;
        var length_insertions1 = 0;
        var length_deletions1 = 0;
        var length_insertions2 = 0;
        var length_deletions2 = 0;
        while (pointer < diffs.length) {
          if (diffs[pointer][0] == DIFF_EQUAL) {
            equalities[equalitiesLength++] = pointer;
            length_insertions1 = length_insertions2;
            length_deletions1 = length_deletions2;
            length_insertions2 = 0;
            length_deletions2 = 0;
            lastEquality = diffs[pointer][1];
          } else {
            if (diffs[pointer][0] == DIFF_INSERT) {
              length_insertions2 += diffs[pointer][1].length;
            } else {
              length_deletions2 += diffs[pointer][1].length;
            }
            if (lastEquality && lastEquality.length <= Math.max(length_insertions1, length_deletions1) && lastEquality.length <= Math.max(length_insertions2, length_deletions2)) {
              diffs.splice(equalities[equalitiesLength - 1], 0, new diff_match_patch.Diff(DIFF_DELETE, lastEquality));
              diffs[equalities[equalitiesLength - 1] + 1][0] = DIFF_INSERT;
              equalitiesLength--;
              equalitiesLength--;
              pointer = equalitiesLength > 0 ? equalities[equalitiesLength - 1] : -1;
              length_insertions1 = 0;
              length_deletions1 = 0;
              length_insertions2 = 0;
              length_deletions2 = 0;
              lastEquality = null;
              changes = true;
            }
          }
          pointer++;
        }
        if (changes) {
          this.diff_cleanupMerge(diffs);
        }
        this.diff_cleanupSemanticLossless(diffs);
        pointer = 1;
        while (pointer < diffs.length) {
          if (diffs[pointer - 1][0] == DIFF_DELETE && diffs[pointer][0] == DIFF_INSERT) {
            var deletion = diffs[pointer - 1][1];
            var insertion = diffs[pointer][1];
            var overlap_length1 = this.diff_commonOverlap_(deletion, insertion);
            var overlap_length2 = this.diff_commonOverlap_(insertion, deletion);
            if (overlap_length1 >= overlap_length2) {
              if (overlap_length1 >= deletion.length / 2 || overlap_length1 >= insertion.length / 2) {
                diffs.splice(pointer, 0, new diff_match_patch.Diff(DIFF_EQUAL, insertion.substring(0, overlap_length1)));
                diffs[pointer - 1][1] = deletion.substring(0, deletion.length - overlap_length1);
                diffs[pointer + 1][1] = insertion.substring(overlap_length1);
                pointer++;
              }
            } else {
              if (overlap_length2 >= deletion.length / 2 || overlap_length2 >= insertion.length / 2) {
                diffs.splice(pointer, 0, new diff_match_patch.Diff(DIFF_EQUAL, deletion.substring(0, overlap_length2)));
                diffs[pointer - 1][0] = DIFF_INSERT;
                diffs[pointer - 1][1] = insertion.substring(0, insertion.length - overlap_length2);
                diffs[pointer + 1][0] = DIFF_DELETE;
                diffs[pointer + 1][1] = deletion.substring(overlap_length2);
                pointer++;
              }
            }
            pointer++;
          }
          pointer++;
        }
      };
      diff_match_patch.prototype.diff_cleanupSemanticLossless = function(diffs) {
        function diff_cleanupSemanticScore_(one, two) {
          if (!one || !two) {
            return 6;
          }
          var char1 = one.charAt(one.length - 1);
          var char2 = two.charAt(0);
          var nonAlphaNumeric1 = char1.match(diff_match_patch.nonAlphaNumericRegex_);
          var nonAlphaNumeric2 = char2.match(diff_match_patch.nonAlphaNumericRegex_);
          var whitespace1 = nonAlphaNumeric1 && char1.match(diff_match_patch.whitespaceRegex_);
          var whitespace2 = nonAlphaNumeric2 && char2.match(diff_match_patch.whitespaceRegex_);
          var lineBreak1 = whitespace1 && char1.match(diff_match_patch.linebreakRegex_);
          var lineBreak2 = whitespace2 && char2.match(diff_match_patch.linebreakRegex_);
          var blankLine1 = lineBreak1 && one.match(diff_match_patch.blanklineEndRegex_);
          var blankLine2 = lineBreak2 && two.match(diff_match_patch.blanklineStartRegex_);
          if (blankLine1 || blankLine2) {
            return 5;
          } else if (lineBreak1 || lineBreak2) {
            return 4;
          } else if (nonAlphaNumeric1 && !whitespace1 && whitespace2) {
            return 3;
          } else if (whitespace1 || whitespace2) {
            return 2;
          } else if (nonAlphaNumeric1 || nonAlphaNumeric2) {
            return 1;
          }
          return 0;
        }
        var pointer = 1;
        while (pointer < diffs.length - 1) {
          if (diffs[pointer - 1][0] == DIFF_EQUAL && diffs[pointer + 1][0] == DIFF_EQUAL) {
            var equality1 = diffs[pointer - 1][1];
            var edit = diffs[pointer][1];
            var equality2 = diffs[pointer + 1][1];
            var commonOffset = this.diff_commonSuffix(equality1, edit);
            if (commonOffset) {
              var commonString = edit.substring(edit.length - commonOffset);
              equality1 = equality1.substring(0, equality1.length - commonOffset);
              edit = commonString + edit.substring(0, edit.length - commonOffset);
              equality2 = commonString + equality2;
            }
            var bestEquality1 = equality1;
            var bestEdit = edit;
            var bestEquality2 = equality2;
            var bestScore = diff_cleanupSemanticScore_(equality1, edit) + diff_cleanupSemanticScore_(edit, equality2);
            while (edit.charAt(0) === equality2.charAt(0)) {
              equality1 += edit.charAt(0);
              edit = edit.substring(1) + equality2.charAt(0);
              equality2 = equality2.substring(1);
              var score = diff_cleanupSemanticScore_(equality1, edit) + diff_cleanupSemanticScore_(edit, equality2);
              if (score >= bestScore) {
                bestScore = score;
                bestEquality1 = equality1;
                bestEdit = edit;
                bestEquality2 = equality2;
              }
            }
            if (diffs[pointer - 1][1] != bestEquality1) {
              if (bestEquality1) {
                diffs[pointer - 1][1] = bestEquality1;
              } else {
                diffs.splice(pointer - 1, 1);
                pointer--;
              }
              diffs[pointer][1] = bestEdit;
              if (bestEquality2) {
                diffs[pointer + 1][1] = bestEquality2;
              } else {
                diffs.splice(pointer + 1, 1);
                pointer--;
              }
            }
          }
          pointer++;
        }
      };
      diff_match_patch.nonAlphaNumericRegex_ = /[^a-zA-Z0-9]/;
      diff_match_patch.whitespaceRegex_ = /\s/;
      diff_match_patch.linebreakRegex_ = /[\r\n]/;
      diff_match_patch.blanklineEndRegex_ = /\n\r?\n$/;
      diff_match_patch.blanklineStartRegex_ = /^\r?\n\r?\n/;
      diff_match_patch.prototype.diff_cleanupEfficiency = function(diffs) {
        var changes = false;
        var equalities = [];
        var equalitiesLength = 0;
        var lastEquality = null;
        var pointer = 0;
        var pre_ins = false;
        var pre_del = false;
        var post_ins = false;
        var post_del = false;
        while (pointer < diffs.length) {
          if (diffs[pointer][0] == DIFF_EQUAL) {
            if (diffs[pointer][1].length < this.Diff_EditCost && (post_ins || post_del)) {
              equalities[equalitiesLength++] = pointer;
              pre_ins = post_ins;
              pre_del = post_del;
              lastEquality = diffs[pointer][1];
            } else {
              equalitiesLength = 0;
              lastEquality = null;
            }
            post_ins = post_del = false;
          } else {
            if (diffs[pointer][0] == DIFF_DELETE) {
              post_del = true;
            } else {
              post_ins = true;
            }
            if (lastEquality && (pre_ins && pre_del && post_ins && post_del || lastEquality.length < this.Diff_EditCost / 2 && pre_ins + pre_del + post_ins + post_del == 3)) {
              diffs.splice(equalities[equalitiesLength - 1], 0, new diff_match_patch.Diff(DIFF_DELETE, lastEquality));
              diffs[equalities[equalitiesLength - 1] + 1][0] = DIFF_INSERT;
              equalitiesLength--;
              lastEquality = null;
              if (pre_ins && pre_del) {
                post_ins = post_del = true;
                equalitiesLength = 0;
              } else {
                equalitiesLength--;
                pointer = equalitiesLength > 0 ? equalities[equalitiesLength - 1] : -1;
                post_ins = post_del = false;
              }
              changes = true;
            }
          }
          pointer++;
        }
        if (changes) {
          this.diff_cleanupMerge(diffs);
        }
      };
      diff_match_patch.prototype.diff_cleanupMerge = function(diffs) {
        diffs.push(new diff_match_patch.Diff(DIFF_EQUAL, ""));
        var pointer = 0;
        var count_delete = 0;
        var count_insert = 0;
        var text_delete = "";
        var text_insert = "";
        var commonlength;
        while (pointer < diffs.length) {
          switch (diffs[pointer][0]) {
            case DIFF_INSERT:
              count_insert++;
              text_insert += diffs[pointer][1];
              pointer++;
              break;
            case DIFF_DELETE:
              count_delete++;
              text_delete += diffs[pointer][1];
              pointer++;
              break;
            case DIFF_EQUAL:
              if (count_delete + count_insert > 1) {
                if (count_delete !== 0 && count_insert !== 0) {
                  commonlength = this.diff_commonPrefix(text_insert, text_delete);
                  if (commonlength !== 0) {
                    if (pointer - count_delete - count_insert > 0 && diffs[pointer - count_delete - count_insert - 1][0] == DIFF_EQUAL) {
                      diffs[pointer - count_delete - count_insert - 1][1] += text_insert.substring(0, commonlength);
                    } else {
                      diffs.splice(0, 0, new diff_match_patch.Diff(DIFF_EQUAL, text_insert.substring(0, commonlength)));
                      pointer++;
                    }
                    text_insert = text_insert.substring(commonlength);
                    text_delete = text_delete.substring(commonlength);
                  }
                  commonlength = this.diff_commonSuffix(text_insert, text_delete);
                  if (commonlength !== 0) {
                    diffs[pointer][1] = text_insert.substring(text_insert.length - commonlength) + diffs[pointer][1];
                    text_insert = text_insert.substring(0, text_insert.length - commonlength);
                    text_delete = text_delete.substring(0, text_delete.length - commonlength);
                  }
                }
                pointer -= count_delete + count_insert;
                diffs.splice(pointer, count_delete + count_insert);
                if (text_delete.length) {
                  diffs.splice(pointer, 0, new diff_match_patch.Diff(DIFF_DELETE, text_delete));
                  pointer++;
                }
                if (text_insert.length) {
                  diffs.splice(pointer, 0, new diff_match_patch.Diff(DIFF_INSERT, text_insert));
                  pointer++;
                }
                pointer++;
              } else if (pointer !== 0 && diffs[pointer - 1][0] == DIFF_EQUAL) {
                diffs[pointer - 1][1] += diffs[pointer][1];
                diffs.splice(pointer, 1);
              } else {
                pointer++;
              }
              count_insert = 0;
              count_delete = 0;
              text_delete = "";
              text_insert = "";
              break;
          }
        }
        if (diffs[diffs.length - 1][1] === "") {
          diffs.pop();
        }
        var changes = false;
        pointer = 1;
        while (pointer < diffs.length - 1) {
          if (diffs[pointer - 1][0] == DIFF_EQUAL && diffs[pointer + 1][0] == DIFF_EQUAL) {
            if (diffs[pointer][1].substring(diffs[pointer][1].length - diffs[pointer - 1][1].length) == diffs[pointer - 1][1]) {
              diffs[pointer][1] = diffs[pointer - 1][1] + diffs[pointer][1].substring(0, diffs[pointer][1].length - diffs[pointer - 1][1].length);
              diffs[pointer + 1][1] = diffs[pointer - 1][1] + diffs[pointer + 1][1];
              diffs.splice(pointer - 1, 1);
              changes = true;
            } else if (diffs[pointer][1].substring(0, diffs[pointer + 1][1].length) == diffs[pointer + 1][1]) {
              diffs[pointer - 1][1] += diffs[pointer + 1][1];
              diffs[pointer][1] = diffs[pointer][1].substring(diffs[pointer + 1][1].length) + diffs[pointer + 1][1];
              diffs.splice(pointer + 1, 1);
              changes = true;
            }
          }
          pointer++;
        }
        if (changes) {
          this.diff_cleanupMerge(diffs);
        }
      };
      diff_match_patch.prototype.diff_xIndex = function(diffs, loc) {
        var chars1 = 0;
        var chars2 = 0;
        var last_chars1 = 0;
        var last_chars2 = 0;
        var x;
        for (x = 0; x < diffs.length; x++) {
          if (diffs[x][0] !== DIFF_INSERT) {
            chars1 += diffs[x][1].length;
          }
          if (diffs[x][0] !== DIFF_DELETE) {
            chars2 += diffs[x][1].length;
          }
          if (chars1 > loc) {
            break;
          }
          last_chars1 = chars1;
          last_chars2 = chars2;
        }
        if (diffs.length != x && diffs[x][0] === DIFF_DELETE) {
          return last_chars2;
        }
        return last_chars2 + (loc - last_chars1);
      };
      diff_match_patch.prototype.diff_prettyHtml = function(diffs) {
        var html = [];
        var pattern_amp = /&/g;
        var pattern_lt = /</g;
        var pattern_gt = />/g;
        var pattern_para = /\n/g;
        for (var x = 0; x < diffs.length; x++) {
          var op = diffs[x][0];
          var data = diffs[x][1];
          var text = data.replace(pattern_amp, "&amp;").replace(pattern_lt, "&lt;").replace(pattern_gt, "&gt;").replace(pattern_para, "&para;<br>");
          switch (op) {
            case DIFF_INSERT:
              html[x] = '<ins style="background:#e6ffe6;">' + text + "</ins>";
              break;
            case DIFF_DELETE:
              html[x] = '<del style="background:#ffe6e6;">' + text + "</del>";
              break;
            case DIFF_EQUAL:
              html[x] = "<span>" + text + "</span>";
              break;
          }
        }
        return html.join("");
      };
      diff_match_patch.prototype.diff_text1 = function(diffs) {
        var text = [];
        for (var x = 0; x < diffs.length; x++) {
          if (diffs[x][0] !== DIFF_INSERT) {
            text[x] = diffs[x][1];
          }
        }
        return text.join("");
      };
      diff_match_patch.prototype.diff_text2 = function(diffs) {
        var text = [];
        for (var x = 0; x < diffs.length; x++) {
          if (diffs[x][0] !== DIFF_DELETE) {
            text[x] = diffs[x][1];
          }
        }
        return text.join("");
      };
      diff_match_patch.prototype.diff_levenshtein = function(diffs) {
        var levenshtein = 0;
        var insertions = 0;
        var deletions = 0;
        for (var x = 0; x < diffs.length; x++) {
          var op = diffs[x][0];
          var data = diffs[x][1];
          switch (op) {
            case DIFF_INSERT:
              insertions += data.length;
              break;
            case DIFF_DELETE:
              deletions += data.length;
              break;
            case DIFF_EQUAL:
              levenshtein += Math.max(insertions, deletions);
              insertions = 0;
              deletions = 0;
              break;
          }
        }
        levenshtein += Math.max(insertions, deletions);
        return levenshtein;
      };
      diff_match_patch.prototype.diff_toDelta = function(diffs) {
        var text = [];
        for (var x = 0; x < diffs.length; x++) {
          switch (diffs[x][0]) {
            case DIFF_INSERT:
              text[x] = "+" + encodeURI(diffs[x][1]);
              break;
            case DIFF_DELETE:
              text[x] = "-" + diffs[x][1].length;
              break;
            case DIFF_EQUAL:
              text[x] = "=" + diffs[x][1].length;
              break;
          }
        }
        return text.join("	").replace(/%20/g, " ");
      };
      diff_match_patch.prototype.diff_fromDelta = function(text1, delta) {
        var diffs = [];
        var diffsLength = 0;
        var pointer = 0;
        var tokens = delta.split(/\t/g);
        for (var x = 0; x < tokens.length; x++) {
          var param = tokens[x].substring(1);
          switch (tokens[x].charAt(0)) {
            case "+":
              try {
                diffs[diffsLength++] = new diff_match_patch.Diff(DIFF_INSERT, decodeURI(param));
              } catch (ex) {
                throw new Error("Illegal escape in diff_fromDelta: " + param);
              }
              break;
            case "-":
            case "=":
              var n = parseInt(param, 10);
              if (isNaN(n) || n < 0) {
                throw new Error("Invalid number in diff_fromDelta: " + param);
              }
              var text = text1.substring(pointer, pointer += n);
              if (tokens[x].charAt(0) == "=") {
                diffs[diffsLength++] = new diff_match_patch.Diff(DIFF_EQUAL, text);
              } else {
                diffs[diffsLength++] = new diff_match_patch.Diff(DIFF_DELETE, text);
              }
              break;
            default:
              if (tokens[x]) {
                throw new Error("Invalid diff operation in diff_fromDelta: " + tokens[x]);
              }
          }
        }
        if (pointer != text1.length) {
          throw new Error("Delta length (" + pointer + ") does not equal source text length (" + text1.length + ").");
        }
        return diffs;
      };
      diff_match_patch.prototype.match_main = function(text, pattern, loc) {
        if (text == null || pattern == null || loc == null) {
          throw new Error("Null input. (match_main)");
        }
        loc = Math.max(0, Math.min(loc, text.length));
        if (text == pattern) {
          return 0;
        } else if (!text.length) {
          return -1;
        } else if (text.substring(loc, loc + pattern.length) == pattern) {
          return loc;
        } else {
          return this.match_bitap_(text, pattern, loc);
        }
      };
      diff_match_patch.prototype.match_bitap_ = function(text, pattern, loc) {
        if (pattern.length > this.Match_MaxBits) {
          throw new Error("Pattern too long for this browser.");
        }
        var s = this.match_alphabet_(pattern);
        var dmp = this;
        function match_bitapScore_(e, x) {
          var accuracy = e / pattern.length;
          var proximity = Math.abs(loc - x);
          if (!dmp.Match_Distance) {
            return proximity ? 1 : accuracy;
          }
          return accuracy + proximity / dmp.Match_Distance;
        }
        var score_threshold = this.Match_Threshold;
        var best_loc = text.indexOf(pattern, loc);
        if (best_loc != -1) {
          score_threshold = Math.min(match_bitapScore_(0, best_loc), score_threshold);
          best_loc = text.lastIndexOf(pattern, loc + pattern.length);
          if (best_loc != -1) {
            score_threshold = Math.min(match_bitapScore_(0, best_loc), score_threshold);
          }
        }
        var matchmask = 1 << pattern.length - 1;
        best_loc = -1;
        var bin_min, bin_mid;
        var bin_max = pattern.length + text.length;
        var last_rd;
        for (var d = 0; d < pattern.length; d++) {
          bin_min = 0;
          bin_mid = bin_max;
          while (bin_min < bin_mid) {
            if (match_bitapScore_(d, loc + bin_mid) <= score_threshold) {
              bin_min = bin_mid;
            } else {
              bin_max = bin_mid;
            }
            bin_mid = Math.floor((bin_max - bin_min) / 2 + bin_min);
          }
          bin_max = bin_mid;
          var start = Math.max(1, loc - bin_mid + 1);
          var finish = Math.min(loc + bin_mid, text.length) + pattern.length;
          var rd = Array(finish + 2);
          rd[finish + 1] = (1 << d) - 1;
          for (var j = finish; j >= start; j--) {
            var charMatch = s[text.charAt(j - 1)];
            if (d === 0) {
              rd[j] = (rd[j + 1] << 1 | 1) & charMatch;
            } else {
              rd[j] = (rd[j + 1] << 1 | 1) & charMatch | ((last_rd[j + 1] | last_rd[j]) << 1 | 1) | last_rd[j + 1];
            }
            if (rd[j] & matchmask) {
              var score = match_bitapScore_(d, j - 1);
              if (score <= score_threshold) {
                score_threshold = score;
                best_loc = j - 1;
                if (best_loc > loc) {
                  start = Math.max(1, 2 * loc - best_loc);
                } else {
                  break;
                }
              }
            }
          }
          if (match_bitapScore_(d + 1, loc) > score_threshold) {
            break;
          }
          last_rd = rd;
        }
        return best_loc;
      };
      diff_match_patch.prototype.match_alphabet_ = function(pattern) {
        var s = {};
        for (var i = 0; i < pattern.length; i++) {
          s[pattern.charAt(i)] = 0;
        }
        for (var i = 0; i < pattern.length; i++) {
          s[pattern.charAt(i)] |= 1 << pattern.length - i - 1;
        }
        return s;
      };
      diff_match_patch.prototype.patch_addContext_ = function(patch, text) {
        if (text.length == 0) {
          return;
        }
        if (patch.start2 === null) {
          throw Error("patch not initialized");
        }
        var pattern = text.substring(patch.start2, patch.start2 + patch.length1);
        var padding = 0;
        while (text.indexOf(pattern) != text.lastIndexOf(pattern) && pattern.length < this.Match_MaxBits - this.Patch_Margin - this.Patch_Margin) {
          padding += this.Patch_Margin;
          pattern = text.substring(patch.start2 - padding, patch.start2 + patch.length1 + padding);
        }
        padding += this.Patch_Margin;
        var prefix = text.substring(patch.start2 - padding, patch.start2);
        if (prefix) {
          patch.diffs.unshift(new diff_match_patch.Diff(DIFF_EQUAL, prefix));
        }
        var suffix = text.substring(patch.start2 + patch.length1, patch.start2 + patch.length1 + padding);
        if (suffix) {
          patch.diffs.push(new diff_match_patch.Diff(DIFF_EQUAL, suffix));
        }
        patch.start1 -= prefix.length;
        patch.start2 -= prefix.length;
        patch.length1 += prefix.length + suffix.length;
        patch.length2 += prefix.length + suffix.length;
      };
      diff_match_patch.prototype.patch_make = function(a, opt_b, opt_c) {
        var text1, diffs;
        if (typeof a == "string" && typeof opt_b == "string" && typeof opt_c == "undefined") {
          text1 = a;
          diffs = this.diff_main(text1, opt_b, true);
          if (diffs.length > 2) {
            this.diff_cleanupSemantic(diffs);
            this.diff_cleanupEfficiency(diffs);
          }
        } else if (a && typeof a == "object" && typeof opt_b == "undefined" && typeof opt_c == "undefined") {
          diffs = a;
          text1 = this.diff_text1(diffs);
        } else if (typeof a == "string" && opt_b && typeof opt_b == "object" && typeof opt_c == "undefined") {
          text1 = a;
          diffs = opt_b;
        } else if (typeof a == "string" && typeof opt_b == "string" && opt_c && typeof opt_c == "object") {
          text1 = a;
          diffs = opt_c;
        } else {
          throw new Error("Unknown call format to patch_make.");
        }
        if (diffs.length === 0) {
          return [];
        }
        var patches = [];
        var patch = new diff_match_patch.patch_obj();
        var patchDiffLength = 0;
        var char_count1 = 0;
        var char_count2 = 0;
        var prepatch_text = text1;
        var postpatch_text = text1;
        for (var x = 0; x < diffs.length; x++) {
          var diff_type = diffs[x][0];
          var diff_text = diffs[x][1];
          if (!patchDiffLength && diff_type !== DIFF_EQUAL) {
            patch.start1 = char_count1;
            patch.start2 = char_count2;
          }
          switch (diff_type) {
            case DIFF_INSERT:
              patch.diffs[patchDiffLength++] = diffs[x];
              patch.length2 += diff_text.length;
              postpatch_text = postpatch_text.substring(0, char_count2) + diff_text + postpatch_text.substring(char_count2);
              break;
            case DIFF_DELETE:
              patch.length1 += diff_text.length;
              patch.diffs[patchDiffLength++] = diffs[x];
              postpatch_text = postpatch_text.substring(0, char_count2) + postpatch_text.substring(char_count2 + diff_text.length);
              break;
            case DIFF_EQUAL:
              if (diff_text.length <= 2 * this.Patch_Margin && patchDiffLength && diffs.length != x + 1) {
                patch.diffs[patchDiffLength++] = diffs[x];
                patch.length1 += diff_text.length;
                patch.length2 += diff_text.length;
              } else if (diff_text.length >= 2 * this.Patch_Margin) {
                if (patchDiffLength) {
                  this.patch_addContext_(patch, prepatch_text);
                  patches.push(patch);
                  patch = new diff_match_patch.patch_obj();
                  patchDiffLength = 0;
                  prepatch_text = postpatch_text;
                  char_count1 = char_count2;
                }
              }
              break;
          }
          if (diff_type !== DIFF_INSERT) {
            char_count1 += diff_text.length;
          }
          if (diff_type !== DIFF_DELETE) {
            char_count2 += diff_text.length;
          }
        }
        if (patchDiffLength) {
          this.patch_addContext_(patch, prepatch_text);
          patches.push(patch);
        }
        return patches;
      };
      diff_match_patch.prototype.patch_deepCopy = function(patches) {
        var patchesCopy = [];
        for (var x = 0; x < patches.length; x++) {
          var patch = patches[x];
          var patchCopy = new diff_match_patch.patch_obj();
          patchCopy.diffs = [];
          for (var y = 0; y < patch.diffs.length; y++) {
            patchCopy.diffs[y] = new diff_match_patch.Diff(patch.diffs[y][0], patch.diffs[y][1]);
          }
          patchCopy.start1 = patch.start1;
          patchCopy.start2 = patch.start2;
          patchCopy.length1 = patch.length1;
          patchCopy.length2 = patch.length2;
          patchesCopy[x] = patchCopy;
        }
        return patchesCopy;
      };
      diff_match_patch.prototype.patch_apply = function(patches, text) {
        if (patches.length == 0) {
          return [text, []];
        }
        patches = this.patch_deepCopy(patches);
        var nullPadding = this.patch_addPadding(patches);
        text = nullPadding + text + nullPadding;
        this.patch_splitMax(patches);
        var delta = 0;
        var results = [];
        for (var x = 0; x < patches.length; x++) {
          var expected_loc = patches[x].start2 + delta;
          var text1 = this.diff_text1(patches[x].diffs);
          var start_loc;
          var end_loc = -1;
          if (text1.length > this.Match_MaxBits) {
            start_loc = this.match_main(text, text1.substring(0, this.Match_MaxBits), expected_loc);
            if (start_loc != -1) {
              end_loc = this.match_main(text, text1.substring(text1.length - this.Match_MaxBits), expected_loc + text1.length - this.Match_MaxBits);
              if (end_loc == -1 || start_loc >= end_loc) {
                start_loc = -1;
              }
            }
          } else {
            start_loc = this.match_main(text, text1, expected_loc);
          }
          if (start_loc == -1) {
            results[x] = false;
            delta -= patches[x].length2 - patches[x].length1;
          } else {
            results[x] = true;
            delta = start_loc - expected_loc;
            var text2;
            if (end_loc == -1) {
              text2 = text.substring(start_loc, start_loc + text1.length);
            } else {
              text2 = text.substring(start_loc, end_loc + this.Match_MaxBits);
            }
            if (text1 == text2) {
              text = text.substring(0, start_loc) + this.diff_text2(patches[x].diffs) + text.substring(start_loc + text1.length);
            } else {
              var diffs = this.diff_main(text1, text2, false);
              if (text1.length > this.Match_MaxBits && this.diff_levenshtein(diffs) / text1.length > this.Patch_DeleteThreshold) {
                results[x] = false;
              } else {
                this.diff_cleanupSemanticLossless(diffs);
                var index1 = 0;
                var index2;
                for (var y = 0; y < patches[x].diffs.length; y++) {
                  var mod = patches[x].diffs[y];
                  if (mod[0] !== DIFF_EQUAL) {
                    index2 = this.diff_xIndex(diffs, index1);
                  }
                  if (mod[0] === DIFF_INSERT) {
                    text = text.substring(0, start_loc + index2) + mod[1] + text.substring(start_loc + index2);
                  } else if (mod[0] === DIFF_DELETE) {
                    text = text.substring(0, start_loc + index2) + text.substring(start_loc + this.diff_xIndex(diffs, index1 + mod[1].length));
                  }
                  if (mod[0] !== DIFF_DELETE) {
                    index1 += mod[1].length;
                  }
                }
              }
            }
          }
        }
        text = text.substring(nullPadding.length, text.length - nullPadding.length);
        return [text, results];
      };
      diff_match_patch.prototype.patch_addPadding = function(patches) {
        var paddingLength = this.Patch_Margin;
        var nullPadding = "";
        for (var x = 1; x <= paddingLength; x++) {
          nullPadding += String.fromCharCode(x);
        }
        for (var x = 0; x < patches.length; x++) {
          patches[x].start1 += paddingLength;
          patches[x].start2 += paddingLength;
        }
        var patch = patches[0];
        var diffs = patch.diffs;
        if (diffs.length == 0 || diffs[0][0] != DIFF_EQUAL) {
          diffs.unshift(new diff_match_patch.Diff(DIFF_EQUAL, nullPadding));
          patch.start1 -= paddingLength;
          patch.start2 -= paddingLength;
          patch.length1 += paddingLength;
          patch.length2 += paddingLength;
        } else if (paddingLength > diffs[0][1].length) {
          var extraLength = paddingLength - diffs[0][1].length;
          diffs[0][1] = nullPadding.substring(diffs[0][1].length) + diffs[0][1];
          patch.start1 -= extraLength;
          patch.start2 -= extraLength;
          patch.length1 += extraLength;
          patch.length2 += extraLength;
        }
        patch = patches[patches.length - 1];
        diffs = patch.diffs;
        if (diffs.length == 0 || diffs[diffs.length - 1][0] != DIFF_EQUAL) {
          diffs.push(new diff_match_patch.Diff(DIFF_EQUAL, nullPadding));
          patch.length1 += paddingLength;
          patch.length2 += paddingLength;
        } else if (paddingLength > diffs[diffs.length - 1][1].length) {
          var extraLength = paddingLength - diffs[diffs.length - 1][1].length;
          diffs[diffs.length - 1][1] += nullPadding.substring(0, extraLength);
          patch.length1 += extraLength;
          patch.length2 += extraLength;
        }
        return nullPadding;
      };
      diff_match_patch.prototype.patch_splitMax = function(patches) {
        var patch_size = this.Match_MaxBits;
        for (var x = 0; x < patches.length; x++) {
          if (patches[x].length1 <= patch_size) {
            continue;
          }
          var bigpatch = patches[x];
          patches.splice(x--, 1);
          var start1 = bigpatch.start1;
          var start2 = bigpatch.start2;
          var precontext = "";
          while (bigpatch.diffs.length !== 0) {
            var patch = new diff_match_patch.patch_obj();
            var empty = true;
            patch.start1 = start1 - precontext.length;
            patch.start2 = start2 - precontext.length;
            if (precontext !== "") {
              patch.length1 = patch.length2 = precontext.length;
              patch.diffs.push(new diff_match_patch.Diff(DIFF_EQUAL, precontext));
            }
            while (bigpatch.diffs.length !== 0 && patch.length1 < patch_size - this.Patch_Margin) {
              var diff_type = bigpatch.diffs[0][0];
              var diff_text = bigpatch.diffs[0][1];
              if (diff_type === DIFF_INSERT) {
                patch.length2 += diff_text.length;
                start2 += diff_text.length;
                patch.diffs.push(bigpatch.diffs.shift());
                empty = false;
              } else if (diff_type === DIFF_DELETE && patch.diffs.length == 1 && patch.diffs[0][0] == DIFF_EQUAL && diff_text.length > 2 * patch_size) {
                patch.length1 += diff_text.length;
                start1 += diff_text.length;
                empty = false;
                patch.diffs.push(new diff_match_patch.Diff(diff_type, diff_text));
                bigpatch.diffs.shift();
              } else {
                diff_text = diff_text.substring(0, patch_size - patch.length1 - this.Patch_Margin);
                patch.length1 += diff_text.length;
                start1 += diff_text.length;
                if (diff_type === DIFF_EQUAL) {
                  patch.length2 += diff_text.length;
                  start2 += diff_text.length;
                } else {
                  empty = false;
                }
                patch.diffs.push(new diff_match_patch.Diff(diff_type, diff_text));
                if (diff_text == bigpatch.diffs[0][1]) {
                  bigpatch.diffs.shift();
                } else {
                  bigpatch.diffs[0][1] = bigpatch.diffs[0][1].substring(diff_text.length);
                }
              }
            }
            precontext = this.diff_text2(patch.diffs);
            precontext = precontext.substring(precontext.length - this.Patch_Margin);
            var postcontext = this.diff_text1(bigpatch.diffs).substring(0, this.Patch_Margin);
            if (postcontext !== "") {
              patch.length1 += postcontext.length;
              patch.length2 += postcontext.length;
              if (patch.diffs.length !== 0 && patch.diffs[patch.diffs.length - 1][0] === DIFF_EQUAL) {
                patch.diffs[patch.diffs.length - 1][1] += postcontext;
              } else {
                patch.diffs.push(new diff_match_patch.Diff(DIFF_EQUAL, postcontext));
              }
            }
            if (!empty) {
              patches.splice(++x, 0, patch);
            }
          }
        }
      };
      diff_match_patch.prototype.patch_toText = function(patches) {
        var text = [];
        for (var x = 0; x < patches.length; x++) {
          text[x] = patches[x];
        }
        return text.join("");
      };
      diff_match_patch.prototype.patch_fromText = function(textline) {
        var patches = [];
        if (!textline) {
          return patches;
        }
        var text = textline.split("\n");
        var textPointer = 0;
        var patchHeader = /^@@ -(\d+),?(\d*) \+(\d+),?(\d*) @@$/;
        while (textPointer < text.length) {
          var m = text[textPointer].match(patchHeader);
          if (!m) {
            throw new Error("Invalid patch string: " + text[textPointer]);
          }
          var patch = new diff_match_patch.patch_obj();
          patches.push(patch);
          patch.start1 = parseInt(m[1], 10);
          if (m[2] === "") {
            patch.start1--;
            patch.length1 = 1;
          } else if (m[2] == "0") {
            patch.length1 = 0;
          } else {
            patch.start1--;
            patch.length1 = parseInt(m[2], 10);
          }
          patch.start2 = parseInt(m[3], 10);
          if (m[4] === "") {
            patch.start2--;
            patch.length2 = 1;
          } else if (m[4] == "0") {
            patch.length2 = 0;
          } else {
            patch.start2--;
            patch.length2 = parseInt(m[4], 10);
          }
          textPointer++;
          while (textPointer < text.length) {
            var sign = text[textPointer].charAt(0);
            try {
              var line = decodeURI(text[textPointer].substring(1));
            } catch (ex) {
              throw new Error("Illegal escape in patch_fromText: " + line);
            }
            if (sign == "-") {
              patch.diffs.push(new diff_match_patch.Diff(DIFF_DELETE, line));
            } else if (sign == "+") {
              patch.diffs.push(new diff_match_patch.Diff(DIFF_INSERT, line));
            } else if (sign == " ") {
              patch.diffs.push(new diff_match_patch.Diff(DIFF_EQUAL, line));
            } else if (sign == "@") {
              break;
            } else if (sign === "") {
            } else {
              throw new Error('Invalid patch mode "' + sign + '" in: ' + line);
            }
            textPointer++;
          }
        }
        return patches;
      };
      diff_match_patch.patch_obj = function() {
        this.diffs = [];
        this.start1 = null;
        this.start2 = null;
        this.length1 = 0;
        this.length2 = 0;
      };
      diff_match_patch.patch_obj.prototype.toString = function() {
        var coords1, coords2;
        if (this.length1 === 0) {
          coords1 = this.start1 + ",0";
        } else if (this.length1 == 1) {
          coords1 = this.start1 + 1;
        } else {
          coords1 = this.start1 + 1 + "," + this.length1;
        }
        if (this.length2 === 0) {
          coords2 = this.start2 + ",0";
        } else if (this.length2 == 1) {
          coords2 = this.start2 + 1;
        } else {
          coords2 = this.start2 + 1 + "," + this.length2;
        }
        var text = ["@@ -" + coords1 + " +" + coords2 + " @@\n"];
        var op;
        for (var x = 0; x < this.diffs.length; x++) {
          switch (this.diffs[x][0]) {
            case DIFF_INSERT:
              op = "+";
              break;
            case DIFF_DELETE:
              op = "-";
              break;
            case DIFF_EQUAL:
              op = " ";
              break;
          }
          text[x + 1] = op + encodeURI(this.diffs[x][1]) + "\n";
        }
        return text.join("").replace(/%20/g, " ");
      };
      module.exports = diff_match_patch;
      module.exports["diff_match_patch"] = diff_match_patch;
      module.exports["DIFF_DELETE"] = DIFF_DELETE;
      module.exports["DIFF_INSERT"] = DIFF_INSERT;
      module.exports["DIFF_EQUAL"] = DIFF_EQUAL;
    }
  });

  // src/Resources/app/administration/src/api/frosh-tools.js
  var { ApiService } = Shopware.Classes;
  var FroshTools = class extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = "_action/frosh-tools") {
      super(httpClient, loginService, apiEndpoint);
    }
    getCacheInfo() {
      const apiRoute = `${this.getApiBasePath()}/cache`;
      return this.httpClient.get(apiRoute, {
        headers: this.getBasicHeaders()
      }).then((response) => {
        return ApiService.handleResponse(response);
      });
    }
    clearCache(folder) {
      const apiRoute = `${this.getApiBasePath()}/cache/${folder}`;
      return this.httpClient.delete(apiRoute, {
        headers: this.getBasicHeaders()
      }).then((response) => {
        return ApiService.handleResponse(response);
      });
    }
    getQueue() {
      const apiRoute = `${this.getApiBasePath()}/queue/list`;
      return this.httpClient.get(apiRoute, {
        headers: this.getBasicHeaders()
      }).then((response) => {
        return ApiService.handleResponse(response);
      });
    }
    resetQueue() {
      const apiRoute = `${this.getApiBasePath()}/queue`;
      return this.httpClient.delete(apiRoute, {
        headers: this.getBasicHeaders()
      }).then((response) => {
        return ApiService.handleResponse(response);
      });
    }
    runScheduledTask(id) {
      const apiRoute = `${this.getApiBasePath()}/scheduled-task/${id}`;
      return this.httpClient.post(apiRoute, {}, {
        headers: this.getBasicHeaders()
      }).then((response) => {
        return ApiService.handleResponse(response);
      });
    }
    scheduledTasksRegister() {
      const apiRoute = `${this.getApiBasePath()}/scheduled-tasks/register`;
      return this.httpClient.post(apiRoute, {}, {
        headers: this.getBasicHeaders()
      }).then((response) => {
        return ApiService.handleResponse(response);
      });
    }
    healthStatus() {
      const apiRoute = `${this.getApiBasePath()}/health/status`;
      return this.httpClient.get(apiRoute, {
        headers: this.getBasicHeaders()
      }).then((response) => {
        return ApiService.handleResponse(response);
      });
    }
    performanceStatus() {
      const apiRoute = `${this.getApiBasePath()}/performance/status`;
      return this.httpClient.get(apiRoute, {
        headers: this.getBasicHeaders()
      }).then((response) => {
        return ApiService.handleResponse(response);
      });
    }
    getLogFiles() {
      const apiRoute = `${this.getApiBasePath()}/logs/files`;
      return this.httpClient.get(apiRoute, {
        headers: this.getBasicHeaders()
      }).then((response) => {
        return ApiService.handleResponse(response);
      });
    }
    getLogFile(file, offset = 0, limit = 20) {
      const apiRoute = `${this.getApiBasePath()}/logs/file`;
      return this.httpClient.get(apiRoute, {
        params: {
          file,
          offset,
          limit
        },
        headers: this.getBasicHeaders()
      }).then((response) => {
        return response;
      });
    }
    getShopwareFiles() {
      const apiRoute = `${this.getApiBasePath()}/shopware-files`;
      return this.httpClient.get(apiRoute, {
        headers: this.getBasicHeaders()
      }).then((response) => {
        return response;
      });
    }
    getFileContents(file) {
      const apiRoute = `${this.getApiBasePath()}/file-contents`;
      return this.httpClient.get(apiRoute, {
        params: {
          file
        },
        headers: this.getBasicHeaders()
      }).then((response) => {
        return response;
      });
    }
    restoreShopwareFile(file) {
      const apiRoute = `${this.getApiBasePath()}/shopware-file/restore`;
      return this.httpClient.get(apiRoute, {
        params: {
          file
        },
        headers: this.getBasicHeaders()
      }).then((response) => {
        return response;
      });
    }
    getFeatureFlags() {
      const apiRoute = `${this.getApiBasePath()}/feature-flag/list`;
      return this.httpClient.get(apiRoute, {
        headers: this.getBasicHeaders()
      }).then((response) => {
        return ApiService.handleResponse(response);
      });
    }
    toggleFeatureFlag(flag) {
      const apiRoute = `${this.getApiBasePath()}/feature-flag/toggle`;
      return this.httpClient.post(apiRoute, { flag }, {
        headers: this.getBasicHeaders()
      }).then((response) => {
        return ApiService.handleResponse(response);
      });
    }
    stateMachines(stateMachine) {
      const apiRoute = `${this.getApiBasePath()}/state-machines/load`;
      return this.httpClient.get(apiRoute, {
        params: {
          stateMachine
        },
        headers: this.getBasicHeaders()
      }).then((response) => {
        return ApiService.handleResponse(response);
      });
    }
  };
  var frosh_tools_default = FroshTools;

  // src/Resources/app/administration/src/api/elasticsearch.js
  var { ApiService: ApiService2 } = Shopware.Classes;
  var Elasticsearch = class extends ApiService2 {
    constructor(httpClient, loginService, apiEndpoint = "_action/frosh-tools/elasticsearch") {
      super(httpClient, loginService, apiEndpoint);
    }
    status() {
      const apiRoute = `${this.getApiBasePath()}/status`;
      return this.httpClient.get(apiRoute, {
        headers: this.getBasicHeaders()
      }).then((response) => {
        return ApiService2.handleResponse(response);
      });
    }
    indices() {
      const apiRoute = `${this.getApiBasePath()}/indices`;
      return this.httpClient.get(apiRoute, {
        headers: this.getBasicHeaders()
      }).then((response) => {
        return ApiService2.handleResponse(response);
      });
    }
    deleteIndex(indexName) {
      const apiRoute = `${this.getApiBasePath()}/index/` + indexName;
      return this.httpClient.delete(apiRoute, {
        headers: this.getBasicHeaders()
      }).then((response) => {
        return ApiService2.handleResponse(response);
      });
    }
    console(method, path, payload) {
      const apiRoute = `${this.getApiBasePath()}/console` + path;
      return this.httpClient.request({
        url: apiRoute,
        method,
        headers: {
          ...this.getBasicHeaders(),
          "content-type": "application/json"
        },
        data: payload
      }).then((response) => {
        return ApiService2.handleResponse(response);
      });
    }
    flushAll() {
      const apiRoute = `${this.getApiBasePath()}/flush_all`;
      return this.httpClient.post(apiRoute, {}, {
        headers: this.getBasicHeaders()
      }).then((response) => {
        return ApiService2.handleResponse(response);
      });
    }
    reindex() {
      const apiRoute = `${this.getApiBasePath()}/reindex`;
      return this.httpClient.post(apiRoute, {}, {
        headers: this.getBasicHeaders()
      }).then((response) => {
        return ApiService2.handleResponse(response);
      });
    }
    switchAlias() {
      const apiRoute = `${this.getApiBasePath()}/switch_alias`;
      return this.httpClient.post(apiRoute, {}, {
        headers: this.getBasicHeaders()
      }).then((response) => {
        return ApiService2.handleResponse(response);
      });
    }
    cleanup() {
      const apiRoute = `${this.getApiBasePath()}/cleanup`;
      return this.httpClient.post(apiRoute, {}, {
        headers: this.getBasicHeaders()
      }).then((response) => {
        return ApiService2.handleResponse(response);
      });
    }
    reset() {
      const apiRoute = `${this.getApiBasePath()}/reset`;
      return this.httpClient.post(apiRoute, {}, {
        headers: this.getBasicHeaders()
      }).then((response) => {
        return ApiService2.handleResponse(response);
      });
    }
  };
  var elasticsearch_default = Elasticsearch;

  // src/Resources/app/administration/src/api/index.js
  var { Application } = Shopware;
  Application.addServiceProvider("froshToolsService", (container) => {
    const initContainer = Application.getContainer("init");
    return new frosh_tools_default(initContainer.httpClient, container.loginService);
  });
  Application.addServiceProvider("froshElasticSearch", (container) => {
    const initContainer = Application.getContainer("init");
    return new elasticsearch_default(initContainer.httpClient, container.loginService);
  });

  // src/Resources/app/administration/src/overrides/sw-data-grid-inline-edit/template.twig
  var template_default = `{% block sw_data_grid_inline_edit_type_unknown %}
    <sw-datepicker
        v-else-if="column.inlineEdit === 'date'"
        dateType="date"
        v-model="currentValue">
    </sw-datepicker>

    <sw-datepicker
        v-else-if="column.inlineEdit === 'datetime'"
        dateType="datetime"
        v-model="currentValue">
    </sw-datepicker>

    {% parent() %}
{% endblock %}
`;

  // src/Resources/app/administration/src/overrides/sw-data-grid-inline-edit/index.js
  var { Component } = Shopware;
  Component.override("sw-data-grid-inline-edit", {
    template: template_default
  });

  // src/Resources/app/administration/src/overrides/sw-version/template.twig
  var template_default2 = `{% block sw_version_status %}
    <router-link
        :to="{ name: 'frosh.tools.index.index' }"
        class="sw-version__status"
        v-tooltip="{
            showDelay: 300,
            message: healthPlaceholder
        }"
    >
        {% block sw_version_status_badge %}
            <sw-color-badge v-if="health" :variant="healthVariant" :rounded="true"></sw-color-badge>
        {% endblock %}
    </router-link>
{% endblock %}
`;

  // src/Resources/app/administration/src/overrides/sw-version/index.js
  var { Component: Component2 } = Shopware;
  Component2.override("sw-version", {
    template: template_default2,
    inject: ["froshToolsService"],
    async created() {
      await this.checkHealth();
    },
    data() {
      return {
        health: null
      };
    },
    computed: {
      healthVariant() {
        let variant = "success";
        for (let health of this.health) {
          if (health.state === "STATE_ERROR") {
            variant = "error";
            continue;
          }
          if (health.state === "STATE_WARNING" && variant === "success") {
            variant = "warning";
          }
        }
        return variant;
      },
      healthPlaceholder() {
        let msg = "Shop Status: Ok";
        if (this.health === null) {
          return msg;
        }
        for (let health of this.health) {
          if (health.state === "STATE_ERROR") {
            msg = "Shop Status: May outage, Check System Status";
            continue;
          }
          if (health.state === "STATE_WARNING" && msg === "Shop Status: Ok") {
            msg = "Shop Status: Issues, Check System Status";
          }
        }
        return msg;
      }
    },
    methods: {
      async checkHealth() {
        this.health = await this.froshToolsService.healthStatus();
        setInterval(async () => {
          this.health = await this.froshToolsService.healthStatus();
        }, 3e4);
      }
    }
  });

  // src/Resources/app/administration/src/module/frosh-tools/component/frosh-tools-tab-index/template.twig
  var template_default3 = `<sw-card-view>
    <sw-card class="frosh-tools-tab-index__health-card" :isLoading="isLoading" :large="true">

        <template #toolbar>
            <!-- @todo: Make the refresh button fancy -->
            <sw-button variant="ghost" @click="refresh">
                <sw-icon :small="true" name="default-arrow-360-left"></sw-icon>
            </sw-button>
        </template>

        <sw-card class="frosh-tools-tab-index__health-card" :title="$tc('frosh-tools.tabs.index.title')" :large="true">
            <sw-data-grid
                v-if="health"
                :showSelection="false"
                :showActions="false"
                :dataSource="health"
                :columns="columns">

                <template #column-current="{ item }">
                    {{ item.current }}
                </template>

                <template #column-name="{ item }">
                    <template>
                        <sw-label variant="success" appearance="pill" v-if="item.state === 'STATE_OK'">
                            {{ $tc('frosh-tools.good') }}
                        </sw-label>
                        <sw-label variant="warning" appearance="pill" v-if="item.state === 'STATE_WARNING'">
                            {{ $tc('frosh-tools.warning') }}
                        </sw-label>
                        <sw-label variant="danger" appearance="pill" v-if="item.state === 'STATE_ERROR'">
                            {{ $tc('frosh-tools.error') }}
                        </sw-label>
                        <sw-label variant="info" appearance="pill" v-if="item.state === 'STATE_INFO'">
                            {{ $tc('frosh-tools.info') }}
                        </sw-label>
                    </template>

                    <template v-if="item.url">
                        <a :href="item.url" target="_blank">{{ $tc(item.snippet) }}</a>
                    </template>
                    <template v-else>{{ $tc(item.snippet) }}</template>
                </template>
            </sw-data-grid>
        </sw-card>

        <sw-card class="frosh-tools-tab-index__health-card" :title="$tc('frosh-tools.tabs.index.performance')" :large="true">
            <sw-data-grid
                v-if="performanceStatus"
                :showSelection="false"
                :showActions="false"
                :dataSource="performanceStatus"
                :columns="columns">

                <template #column-current="{ item }">
                    {{ item.current }}
                </template>

                <template #column-name="{ item }">
                    <template>
                        <sw-label variant="success" appearance="pill" v-if="item.state === 'STATE_OK'">
                            {{ $tc('frosh-tools.good') }}
                        </sw-label>
                        <sw-label variant="warning" appearance="pill" v-if="item.state === 'STATE_WARNING'">
                            {{ $tc('frosh-tools.warning') }}
                        </sw-label>
                        <sw-label variant="danger" appearance="pill" v-if="item.state === 'STATE_ERROR'">
                            {{ $tc('frosh-tools.error') }}
                        </sw-label>
                        <sw-label variant="info" appearance="pill" v-if="item.state === 'STATE_INFO'">
                            {{ $tc('frosh-tools.info') }}
                        </sw-label>
                    </template>

                    <template v-if="item.url">
                        <a :href="item.url" target="_blank">{{ $tc(item.snippet) }}</a>
                    </template>
                    <template v-else>{{ $tc(item.snippet) }}</template>
                </template>
            </sw-data-grid>
        </sw-card>
    </sw-card>
</sw-card-view>
`;

  // src/Resources/app/administration/src/module/frosh-tools/component/frosh-tools-tab-index/index.js
  var { Component: Component3 } = Shopware;
  Component3.register("frosh-tools-tab-index", {
    inject: ["froshToolsService"],
    template: template_default3,
    data() {
      return {
        isLoading: true,
        health: null,
        performanceStatus: null
      };
    },
    created() {
      this.createdComponent();
    },
    computed: {
      columns() {
        return [
          {
            property: "name",
            label: "frosh-tools.name",
            rawData: true
          },
          {
            property: "current",
            label: "frosh-tools.current",
            rawData: true
          },
          {
            property: "recommended",
            label: "frosh-tools.recommended",
            rawData: true
          }
        ];
      }
    },
    methods: {
      async refresh() {
        this.isLoading = true;
        await this.createdComponent();
      },
      async createdComponent() {
        this.health = await this.froshToolsService.healthStatus();
        this.performanceStatus = await this.froshToolsService.performanceStatus();
        this.isLoading = false;
      }
    }
  });

  // src/Resources/app/administration/src/module/frosh-tools/component/frosh-tools-tab-cache/template.twig
  var template_default4 = `<sw-card-view>
    <sw-card class="frosh-tools-tab-cache__cache-card" :title="$tc('frosh-tools.tabs.cache.title')" :isLoading="isLoading" :large="true">
        <template #toolbar>
            <!-- @todo: Make the refresh button fancy -->
            <sw-button variant="ghost" @click="createdComponent"><sw-icon :small="true" name="default-arrow-360-left"></sw-icon></sw-button>
        </template>

        <sw-data-grid
            :showSelection="false"
            :dataSource="cacheFolders"
            :columns="columns"
        >

            <template #column-name="{ item }">
                <sw-label variant="success" appearance="pill" v-if="item.active" >
                    {{ $tc('frosh-tools.active') }}
                </sw-label>
                <sw-label variant="primary" appearance="pill" v-if="item.type" >
                    {{ item.type }}
                </sw-label>
                {{ item.name }}
            </template>

            <template #column-size="{ item }">
                <template v-if="item.size < 0">
                    unknown
                </template>
                <template v-else>
                    {{ formatSize(item.size) }}
                </template>
            </template>

            <template #column-freeSpace="{ item }">
                <template v-if="item.freeSpace < 0">
                    unknown
                </template>
                <template v-else>
                    {{ formatSize(item.freeSpace) }}
                </template>
            </template>

            <template #actions="{ item }">
                <sw-context-menu-item variant="danger" @click="clearCache(item)">
                    {{ $tc('frosh-tools.clear') }}
                </sw-context-menu-item>
            </template>
        </sw-data-grid>
    </sw-card>

    <sw-card class="frosh-tools-tab-cache__action-card" :title="$tc('frosh-tools.actions')" :isLoading="isLoading" :large="true">
        <sw-button variant="primary" @click="compileTheme">{{ $tc('frosh-tools.compileTheme') }}</sw-button>
    </sw-card>
</sw-card-view>
`;

  // src/Resources/app/administration/src/module/frosh-tools/component/frosh-tools-tab-cache/index.js
  var { Component: Component4, Mixin } = Shopware;
  var { Criteria } = Shopware.Data;
  Component4.register("frosh-tools-tab-cache", {
    template: template_default4,
    inject: ["froshToolsService", "repositoryFactory", "themeService"],
    mixins: [
      Mixin.getByName("notification")
    ],
    data() {
      return {
        cacheInfo: null,
        isLoading: true,
        numberFormater: null
      };
    },
    async created() {
      const language = Shopware.Application.getContainer("factory").locale.getLastKnownLocale();
      this.numberFormater = new Intl.NumberFormat(language, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      this.createdComponent();
    },
    computed: {
      columns() {
        return [
          {
            property: "name",
            label: "frosh-tools.name",
            rawData: true
          },
          {
            property: "size",
            label: "frosh-tools.used",
            rawData: true,
            align: "right"
          },
          {
            property: "freeSpace",
            label: "frosh-tools.free",
            rawData: true,
            align: "right"
          }
        ];
      },
      cacheFolders() {
        if (this.cacheInfo === null) {
          return [];
        }
        return this.cacheInfo;
      },
      salesChannelRepository() {
        return this.repositoryFactory.create("sales_channel");
      }
    },
    methods: {
      async createdComponent() {
        this.isLoading = true;
        this.cacheInfo = await this.froshToolsService.getCacheInfo();
        this.isLoading = false;
      },
      formatSize(bytes) {
        bytes /= 1024 * 1024;
        return this.numberFormater.format(bytes) + " MiB";
      },
      async clearCache(item) {
        this.isLoading = true;
        await this.froshToolsService.clearCache(item.name);
        await this.createdComponent();
      },
      async compileTheme() {
        const criteria = new Criteria();
        criteria.addAssociation("themes");
        this.isLoading = true;
        let salesChannels = await this.salesChannelRepository.search(criteria, Shopware.Context.api);
        for (let salesChannel of salesChannels) {
          const theme = salesChannel.extensions.themes.first();
          if (theme) {
            await this.themeService.assignTheme(theme.id, salesChannel.id);
            this.createNotificationSuccess({
              message: `${salesChannel.translated.name}: ` + this.$tc("frosh-tools.themeCompiled")
            });
          }
        }
        this.isLoading = false;
      }
    }
  });

  // src/Resources/app/administration/src/module/frosh-tools/component/frosh-tools-tab-queue/template.twig
  var template_default5 = `<sw-card-view>
    <sw-card class="frosh-tools-tab-queue__manager-card" :title="$tc('frosh-tools.tabs.queue.title')" :isLoading="isLoading" :large="true">
        <template #toolbar>
            <!-- @todo: Make the refresh button fancy -->
            <sw-button variant="ghost" @click="refresh"><sw-icon :small="true" name="default-arrow-360-left"></sw-icon></sw-button>
        </template>

        <sw-data-grid
            :showSelection="false"
            :dataSource="queueEntries"
            :columns="columns"
        >
        </sw-data-grid>
    </sw-card>

    <sw-card class="frosh-tools-tab-queue__action-card" title="Actions" :large="true">
        <sw-button variant="danger" @click="showResetModal = true">{{ $tc('frosh-tools.resetQueue') }}</sw-button>
    </sw-card>

    <sw-modal v-if="showResetModal" title="Reset Queue" variant="small" @modal-close="showResetModal = false">
        Resetting Queue will remove all outgoing tasks.

        <template #modal-footer>
            <sw-button @click="showResetModal = false">Cancel</sw-button>
            <sw-button variant="danger" @click="resetQueue">Reset</sw-button>
        </template>
    </sw-modal>
</sw-card-view>
`;

  // src/Resources/app/administration/src/module/frosh-tools/component/frosh-tools-tab-queue/index.js
  var { Component: Component5, Mixin: Mixin2 } = Shopware;
  var { Criteria: Criteria2 } = Shopware.Data;
  Component5.register("frosh-tools-tab-queue", {
    template: template_default5,
    inject: ["repositoryFactory", "froshToolsService"],
    mixins: [
      Mixin2.getByName("notification")
    ],
    data() {
      return {
        queueEntries: null,
        showResetModal: false,
        isLoading: true
      };
    },
    created() {
      this.createdComponent();
    },
    computed: {
      columns() {
        return [
          {
            property: "name",
            label: "Name",
            rawData: true
          },
          {
            property: "size",
            label: "Size",
            rawData: true
          }
        ];
      }
    },
    methods: {
      async refresh() {
        this.isLoading = true;
        await this.createdComponent();
      },
      async createdComponent() {
        this.queueEntries = await this.froshToolsService.getQueue();
        for (let queue of this.queueEntries) {
          let nameSplit = queue.name.split("\\");
          queue.name = nameSplit[nameSplit.length - 1];
        }
        this.isLoading = false;
      },
      async resetQueue() {
        this.isLoading = true;
        await this.froshToolsService.resetQueue();
        this.showResetModal = false;
        this.createdComponent();
        this.createNotificationSuccess({
          message: "The queue has been cleared"
        });
        this.isLoading = false;
      }
    }
  });

  // src/Resources/app/administration/src/module/frosh-tools/component/frosh-tools-tab-scheduled/template.twig
  var template_default6 = `<sw-card-view>
    <sw-card class="frosh-tools-tab-scheduled__tasks-card" :title="$tc('frosh-tools.tabs.scheduledTaskOverview.title')" :isLoading="isLoading" :large="true">

        <template #toolbar>
            <sw-button variant="ghost" @click="refresh"><sw-icon :small="true" name="default-arrow-360-left"></sw-icon></sw-button>
            <sw-button variant="primary" @click="registerScheduledTasks">{{ $tc('frosh-tools.scheduledTasksRegisterStarted') }}</sw-button>
        </template>

        <sw-entity-listing
            :showSelection="false"
            :fullPage="false"
            :allowInlineEdit="true"
            :allowEdit="false"
            :allowDelete="false"
            :showActions="true"
            :repository="scheduledRepository"
            :items="items"
            :columns="columns">

            <template #column-lastExecutionTime="{ item }">
                {{ item.lastExecutionTime | date({hour: '2-digit', minute: '2-digit'}) }}
            </template>
            <template #column-nextExecutionTime="{ item, column, compact, isInlineEdit }">
                <sw-data-grid-inline-edit
                    v-if="isInlineEdit"
                    :column="column"
                    :compact="compact"
                    :value="item[column.property]"
                    @input="item[column.property] = $event">
                </sw-data-grid-inline-edit>

                <span v-else>
                     {{ item.nextExecutionTime | date({hour: '2-digit', minute: '2-digit'}) }}
                </span>
            </template>

            <template #actions="{ item }">
                <sw-context-menu-item variant="primary" @click="runTask(item)">
                    {{ $tc('frosh-tools.runManually') }}
                </sw-context-menu-item>
            </template>
        </sw-entity-listing>
    </sw-card>
</sw-card-view>
`;

  // src/Resources/app/administration/src/module/frosh-tools/component/frosh-tools-tab-scheduled/index.js
  var { Component: Component6, Mixin: Mixin3 } = Shopware;
  var { Criteria: Criteria3 } = Shopware.Data;
  Component6.register("frosh-tools-tab-scheduled", {
    template: template_default6,
    inject: ["repositoryFactory", "froshToolsService"],
    mixins: [
      Mixin3.getByName("notification")
    ],
    data() {
      return {
        items: null,
        showResetModal: false,
        isLoading: true
      };
    },
    created() {
      this.createdComponent();
    },
    computed: {
      scheduledRepository() {
        return this.repositoryFactory.create("scheduled_task");
      },
      columns() {
        return [
          {
            property: "name",
            label: "frosh-tools.name",
            rawData: true,
            primary: true
          },
          {
            property: "runInterval",
            label: "frosh-tools.interval",
            rawData: true,
            inlineEdit: "number"
          },
          {
            property: "lastExecutionTime",
            label: "frosh-tools.lastExecutionTime",
            rawData: true
          },
          {
            property: "nextExecutionTime",
            label: "frosh-tools.nextExecutionTime",
            rawData: true,
            inlineEdit: "datetime"
          },
          {
            property: "status",
            label: "frosh-tools.status",
            rawData: true
          }
        ];
      }
    },
    methods: {
      async refresh() {
        this.isLoading = true;
        await this.createdComponent();
      },
      async createdComponent() {
        const criteria = new Criteria3();
        criteria.addSorting(Criteria3.sort("nextExecutionTime", "ASC"));
        this.items = await this.scheduledRepository.search(criteria, Shopware.Context.api);
        this.isLoading = false;
      },
      async runTask(item) {
        this.isLoading = true;
        try {
          this.createNotificationInfo({
            message: this.$tc("frosh-tools.scheduledTaskStarted", 0, { "name": item.name })
          });
          await this.froshToolsService.runScheduledTask(item.id);
          this.createNotificationSuccess({
            message: this.$tc("frosh-tools.scheduledTaskSucceed", 0, { "name": item.name })
          });
        } catch (e) {
          this.createNotificationError({
            message: this.$tc("frosh-tools.scheduledTaskFailed", 0, { "name": item.name })
          });
        }
        this.createdComponent();
      },
      async registerScheduledTasks() {
        this.isLoading = true;
        try {
          this.createNotificationInfo({
            message: this.$tc("frosh-tools.scheduledTasksRegisterStarted")
          });
          await this.froshToolsService.scheduledTasksRegister();
          this.createNotificationSuccess({
            message: this.$tc("frosh-tools.scheduledTasksRegisterSucceed")
          });
        } catch (e) {
          this.createNotificationError({
            message: this.$tc("frosh-tools.scheduledTasksRegisterFailed")
          });
        }
        this.createdComponent();
      }
    }
  });

  // src/Resources/app/administration/src/module/frosh-tools/component/frosh-tools-tab-elasticsearch/template.twig
  var template_default7 = `<sw-card-view>
    <sw-card :title="$tc('frosh-tools.tabs.elasticsearch.title')" :large="true" :isLoading="isLoading">
        <sw-alert variant="error" v-if="!isLoading && !isActive">Elasticsearch is not enabled</sw-alert>

        <div v-if="!isLoading && isActive">
            <div><strong>Elasticsearch version: </strong> {{ statusInfo.info.version.number }}</div>
            <div><strong>Nodes: </strong> {{ statusInfo.health.number_of_nodes }}</div>
            <div><strong>Cluster status: </strong> {{ statusInfo.health.status }}</div>
        </div>
    </sw-card>

    <sw-card title="Indices" v-if="!isLoading && isActive" :large="true">
        <template #toolbar>
            <!-- @todo: Make the refresh button fancy -->
            <sw-button variant="ghost" @click="createdComponent"><sw-icon :small="true" name="default-arrow-360-left"></sw-icon></sw-button>
        </template>

        <sw-data-grid
            v-if="indices"
            :showSelection="false"
            :dataSource="indices"
            :columns="columns">

            <template #column-name="{ item }">
                <sw-label variant="primary" appearance="pill" v-if="item.aliases.length">
                    {{ $tc('frosh-tools.active') }}
                </sw-label>

                {{ item.name }}<br>
            </template>

            <template #column-indexSize="{ item }">
                {{ formatSize(item.indexSize) }}<br>
            </template>

            <template #actions="{ item }">
                <sw-context-menu-item variant="danger" @click="deleteIndex(item.name)">
                    {{ $tc('frosh-tools.delete') }}
                </sw-context-menu-item>
            </template>
        </sw-data-grid>
    </sw-card>

    <sw-card title="Actions" v-if="!isLoading && isActive" :large="true">
        <sw-button @click="reindex" variant="primary">Reindex</sw-button>
        <sw-button @click="switchAlias">Trigger alias switching</sw-button>
        <sw-button @click="flushAll">Flush all indices</sw-button>

        <sw-button @click="cleanup">Cleanup unused Indices</sw-button>
        <sw-button @click="resetElasticsearch" variant="danger">Delete all indices</sw-button>
    </sw-card>

    <sw-card title="Elasticsearch Console" v-if="!isLoading && isActive" :large="true">
        <sw-code-editor
            completionMode="text"
            mode="twig"
            :softWraps="true"
            :setFocus="false"
            :disabled="false"
            :sanitizeInput="false"
            v-model="consoleInput"
        ></sw-code-editor>

        <sw-button @click="onConsoleEnter">Send</sw-button>

        <div><strong>Output:</strong></div>

        <pre>{{ consoleOutput }}</pre>
    </sw-card>
</sw-card-view>
`;

  // src/Resources/app/administration/src/module/frosh-tools/component/frosh-tools-tab-elasticsearch/index.js
  var { Mixin: Mixin4, Component: Component7 } = Shopware;
  Component7.register("frosh-tools-tab-elasticsearch", {
    template: template_default7,
    inject: ["froshElasticSearch"],
    mixins: [
      Mixin4.getByName("notification")
    ],
    data() {
      return {
        isLoading: true,
        isActive: true,
        statusInfo: {},
        indices: [],
        consoleInput: "GET /_cat/indices",
        consoleOutput: {}
      };
    },
    computed: {
      columns() {
        return [
          {
            property: "name",
            label: "frosh-tools.name",
            rawData: true,
            primary: true
          },
          {
            property: "indexSize",
            label: "frosh-tools.size",
            rawData: true,
            primary: true
          },
          {
            property: "docs",
            label: "frosh-tools.docs",
            rawData: true,
            primary: true
          }
        ];
      }
    },
    async created() {
      this.createdComponent();
    },
    methods: {
      async createdComponent() {
        this.isLoading = true;
        try {
          this.statusInfo = await this.froshElasticSearch.status();
        } catch (err) {
          this.isActive = false;
          this.isLoading = false;
          return;
        } finally {
          this.isLoading = false;
        }
        this.indices = await this.froshElasticSearch.indices();
      },
      formatSize(bytes) {
        const thresh = 1024;
        const dp = 1;
        if (Math.abs(bytes) < thresh) {
          return bytes + " B";
        }
        const units = ["KiB", "MiB", "GiB", "TiB", "PiB", "EiB", "ZiB", "YiB"];
        let u = -1;
        const r = 10 ** dp;
        do {
          bytes /= thresh;
          ++u;
        } while (Math.round(Math.abs(bytes) * r) / r >= thresh && u < units.length - 1);
        return bytes.toFixed(dp) + " " + units[u];
      },
      async deleteIndex(indexName) {
        await this.froshElasticSearch.deleteIndex(indexName);
        await this.createdComponent();
      },
      async onConsoleEnter() {
        const lines = this.consoleInput.split("\n");
        const requestLine = lines.shift();
        const payload = lines.join("\n").trim();
        const [method, uri] = requestLine.split(" ");
        try {
          this.consoleOutput = await this.froshElasticSearch.console(method, uri, payload);
        } catch (e) {
          this.consoleOutput = e.response.data;
        }
      },
      async reindex() {
        await this.froshElasticSearch.reindex();
        this.createNotificationSuccess({
          message: this.$tc("global.default.success")
        });
        await this.createdComponent();
      },
      async switchAlias() {
        await this.froshElasticSearch.switchAlias();
        this.createNotificationSuccess({
          message: this.$tc("global.default.success")
        });
        await this.createdComponent();
      },
      async flushAll() {
        await this.froshElasticSearch.flushAll();
        this.createNotificationSuccess({
          message: this.$tc("global.default.success")
        });
        await this.createdComponent();
      },
      async resetElasticsearch() {
        await this.froshElasticSearch.reset();
        this.createNotificationSuccess({
          message: this.$tc("global.default.success")
        });
        await this.createdComponent();
      },
      async cleanup() {
        await this.froshElasticSearch.cleanup();
        this.createNotificationSuccess({
          message: this.$tc("global.default.success")
        });
        await this.createdComponent();
      }
    }
  });

  // src/Resources/app/administration/src/module/frosh-tools/component/frosh-tools-tab-logs/template.twig
  var template_default8 = `<sw-card-view>
    <sw-card class="frosh-tools-tab-logs__logs-card" :title="$tc('frosh-tools.tabs.logs.title')" :isLoading="isLoading" :large="true">
        <template #toolbar>
            <!-- @todo: Make the refresh button fancy -->
            <sw-button variant="ghost" @click="refresh"><sw-icon :small="true" name="default-arrow-360-left"></sw-icon></sw-button>
            <sw-single-select
                :options="logFiles"
                :isLoading="isLoading"
                :placeholder="$tc('frosh-tools.tabs.logs.logFileSelect.placeholder')"
                labelProperty="name"
                valueProperty="name"
                v-model="selectedLogFile"
                @change="onFileSelected"
            ></sw-single-select>
        </template>

        <sw-data-grid
            :showSelection="false"
            :showActions="false"
            :dataSource="logEntries"
            :columns="columns">
            <template slot="column-date" slot-scope="{ item }">
                {{ item.date | date({hour: '2-digit', minute: '2-digit', second: '2-digit'}) }}
            </template>
            <template slot="column-message" slot-scope="{ item }">
                <a @click="showInfoModal(item)">{{ item.message | raw }}</a>
            </template>
        </sw-data-grid>

        <sw-pagination
            :total="totalLogEntries"
            :limit="limit"
            :page="page"
            @page-change="onPageChange"
        ></sw-pagination>
    </sw-card>

    <sw-modal v-if="displayedLog"
              variant="large">

        <template slot="modal-header">
            <div class="sw-modal__titles">
                <h4 class="sw-modal__title">
                    {{ displayedLog.channel }} - {{ displayedLog.level }}
                </h4>

                <h5 class="sw-modal__subtitle">
                    {{ displayedLog.date | date({hour: '2-digit', minute: '2-digit', second: '2-digit'}) }}
                </h5>
            </div>

            <button
                class="sw-modal__close"
                :title="$tc('global.sw-modal.labelClose')"
                :aria-label="$tc('global.sw-modal.labelClose')"
                @click="closeInfoModal"
            >
                <sw-icon
                    name="regular-times-s"
                    small
                />
            </button>
        </template>

        <div v-html="displayedLog.message"></div>
    </sw-modal>
</sw-card-view>
`;

  // src/Resources/app/administration/src/module/frosh-tools/component/frosh-tools-tab-logs/index.js
  var { Component: Component8, Mixin: Mixin5 } = Shopware;
  Component8.register("frosh-tools-tab-logs", {
    template: template_default8,
    inject: ["froshToolsService"],
    mixins: [
      Mixin5.getByName("notification")
    ],
    data() {
      return {
        logFiles: [],
        selectedLogFile: null,
        logEntries: [],
        totalLogEntries: 0,
        limit: 25,
        page: 1,
        isLoading: true,
        displayedLog: null
      };
    },
    created() {
      this.createdComponent();
    },
    computed: {
      columns() {
        return [
          {
            property: "date",
            label: "frosh-tools.date",
            rawData: true
          },
          {
            property: "channel",
            label: "frosh-tools.channel",
            rawData: true
          },
          {
            property: "level",
            label: "frosh-tools.level",
            rawData: true
          },
          {
            property: "message",
            label: "frosh-tools.message",
            rawData: true
          }
        ];
      }
    },
    methods: {
      async refresh() {
        this.isLoading = true;
        await this.createdComponent();
        await this.onFileSelected();
      },
      async createdComponent() {
        this.logFiles = await this.froshToolsService.getLogFiles();
        this.isLoading = false;
      },
      async onFileSelected() {
        if (!this.selectedLogFile) {
          return;
        }
        const logEntries = await this.froshToolsService.getLogFile(this.selectedLogFile, (this.page - 1) * this.limit, this.limit);
        this.logEntries = logEntries.data;
        this.totalLogEntries = parseInt(logEntries.headers["file-size"]);
      },
      async onPageChange(page) {
        this.page = page.page;
        this.limit = page.limit;
        await this.onFileSelected();
      },
      showInfoModal(entryContents) {
        this.displayedLog = entryContents;
      },
      closeInfoModal() {
        this.displayedLog = null;
      }
    }
  });

  // src/Resources/app/administration/src/module/frosh-tools/component/frosh-tools-tab-files/template.twig
  var template_default9 = `<sw-card-view>
    <sw-card class="frosh-tools-tab-files__files-card" :class="isLoadingClass" :title="$tc('frosh-tools.tabs.files.title')" :isLoading="isLoading" :large="true">
        <sw-alert variant="error" v-if="items.error">{{ items.error }}</sw-alert>

        <sw-alert variant="success" v-if="items.ok">{{ $tc('frosh-tools.tabs.files.allFilesOk') }}</sw-alert>
        <sw-alert variant="warning" v-else-if="items.files">{{ $tc('frosh-tools.tabs.files.notOk') }}</sw-alert>

        <template #toolbar>
            <!-- @todo: Make the refresh button fancy -->
            <sw-button variant="ghost" @click="refresh"><sw-icon :small="true" name="default-arrow-360-left"></sw-icon></sw-button>
        </template>

        <sw-data-grid
            v-if="items.files && items.files.length"
            :showSelection="false"
            :dataSource="items.files"
            :columns="columns">

            <template #column-expected="{ item }">
                <span v-if="item.expected">{{ $tc('frosh-tools.tabs.files.expectedProject') }}</span>
                <span v-else>{{ $tc('frosh-tools.tabs.files.expectedAll') }}</span>
            </template>

            <template #actions="{ item }">
                <sw-context-menu-item @click="openUrl(item.shopwareUrl)">
                    {{ $tc('frosh-tools.tabs.files.openOriginal') }}
                </sw-context-menu-item>
                <sw-context-menu-item @click="diff(item)">
                    {{ $tc('frosh-tools.tabs.files.restore.diff') }}
                </sw-context-menu-item>
            </template>
        </sw-data-grid>
    </sw-card>

    <sw-modal v-if="showModal"
        variant="large"
        @modal-close="closeModal"
        :title="diffData.file">

        <span style="white-space: pre" v-html="diffData.html"></span>

        <template slot="modal-footer">
            <sw-button variant="ghost-danger" @click="restoreFile(diffData.file.name)" :disabled="diffData.file.expected">
                <sw-icon name="default-badge-warning"></sw-icon>
                {{ $tc('frosh-tools.tabs.files.restore.restoreFile') }}
            </sw-button>
        </template>
    </sw-modal>
</sw-card-view>
`;

  // src/Resources/app/administration/src/module/frosh-tools/component/frosh-tools-tab-files/index.js
  var import_diff_match_patch = __toESM(require_diff_match_patch());
  var { Component: Component9, Mixin: Mixin6 } = Shopware;
  Component9.register("frosh-tools-tab-files", {
    template: template_default9,
    inject: ["repositoryFactory", "froshToolsService"],
    mixins: [
      Mixin6.getByName("notification")
    ],
    data() {
      return {
        items: {},
        isLoading: true,
        diffData: {
          html: "",
          file: ""
        },
        showModal: false
      };
    },
    created() {
      this.createdComponent();
    },
    computed: {
      columns() {
        return [
          {
            property: "name",
            label: "frosh-tools.name",
            rawData: true,
            primary: true
          },
          {
            property: "expected",
            label: "frosh-tools.status",
            rawData: true,
            primary: true
          }
        ];
      },
      isLoadingClass() {
        return {
          "is-loading": this.isLoading
        };
      }
    },
    methods: {
      async refresh() {
        this.isLoading = true;
        await this.createdComponent();
      },
      async createdComponent() {
        this.items = (await this.froshToolsService.getShopwareFiles()).data;
        this.isLoading = false;
      },
      openUrl(url) {
        window.open(url, "_blank");
      },
      async diff(file) {
        const fileContents = (await this.froshToolsService.getFileContents(file.name)).data;
        const dmp = new import_diff_match_patch.default();
        const diff = dmp.diff_main(fileContents.originalContent, fileContents.content);
        dmp.diff_cleanupSemantic(diff);
        this.diffData.html = dmp.diff_prettyHtml(diff).replace(new RegExp("background:#e6ffe6;", "g"), "background:#ABF2BC;").replace(new RegExp("background:#ffe6e6;", "g"), "background:rgba(255,129,130,0.4);");
        this.diffData.file = file;
        this.openModal();
      },
      async restoreFile(name) {
        this.closeModal();
        this.isLoading = true;
        const response = await this.froshToolsService.restoreShopwareFile(name);
        if (response.data.status) {
          this.createNotificationSuccess({
            message: response.data.status
          });
        } else {
          this.createNotificationError({
            message: response.data.error
          });
        }
        await this.refresh();
      },
      openModal() {
        this.showModal = true;
      },
      closeModal() {
        this.showModal = false;
      }
    }
  });

  // src/Resources/app/administration/src/module/frosh-tools/component/frosh-tools-tab-feature-flags/template.html.twig
  var template_html_default = `<sw-card-view>
    <sw-card
        class="frosh-tools-tab-feature-flags__feature-flags-card"
        :title="$tc('frosh-tools.tabs.feature-flags.title')"
        :isLoading="isLoading"
        :large="true"
    >

        <template #toolbar>
            <!-- @todo: Make the refresh button fancy -->
            <sw-button variant="ghost" @click="refresh"><sw-icon :small="true" name="default-arrow-360-left"></sw-icon></sw-button>
        </template>

        <sw-data-grid
            :showSelection="false"
            :dataSource="featureFlags"
            :columns="columns"
        >
            <template #column-active="{ item }">
                <sw-icon
                    v-if="item.active"
                    color="#37d046"
                    name="small-default-checkmark-line-medium"
                    small
                />
                <sw-icon
                    v-else
                    color="#de294c"
                    name="small-default-x-line-medium"
                    small
                />
            </template>

            <template #column-major="{ item }">
                <sw-icon
                    v-if="item.major"
                    color="#37d046"
                    name="small-default-checkmark-line-medium"
                    small
                />
                <sw-icon
                    v-else
                    color="#de294c"
                    name="small-default-x-line-medium"
                    small
                />
            </template>

            <template #column-default="{ item }">
                <sw-icon
                    v-if="item.default"
                    color="#37d046"
                    name="small-default-checkmark-line-medium"
                    small
                />
                <sw-icon
                    v-else
                    color="#de294c"
                    name="small-default-x-line-medium"
                    small
                />
            </template>

            <template #actions="{ item }">
                <sw-context-menu-item variant="danger" @click="toggle(item.flag)">
                    <template v-if="item.active">
                        {{ $tc('frosh-tools.tabs.feature-flags.deactivate') }}
                    </template>
                    <template v-else>
                        {{ $tc('frosh-tools.tabs.feature-flags.activate') }}
                    </template>
                </sw-context-menu-item>
            </template>
        </sw-data-grid>
    </sw-card>
</sw-card-view>
`;

  // src/Resources/app/administration/src/module/frosh-tools/component/frosh-tools-tab-feature-flags/index.js
  var { Component: Component10, Mixin: Mixin7 } = Shopware;
  Component10.register("frosh-tools-tab-feature-flags", {
    template: template_html_default,
    inject: ["froshToolsService"],
    mixins: [
      Mixin7.getByName("notification")
    ],
    data() {
      return {
        featureFlags: null,
        isLoading: true
      };
    },
    async created() {
      this.createdComponent();
    },
    computed: {
      columns() {
        return [
          {
            property: "flag",
            label: "frosh-tools.tabs.feature-flags.flag",
            rawData: true
          },
          {
            property: "active",
            label: "frosh-tools.active",
            rawData: true
          },
          {
            property: "description",
            label: "frosh-tools.tabs.feature-flags.description",
            rawData: true
          },
          {
            property: "major",
            label: "frosh-tools.tabs.feature-flags.major",
            rawData: true
          },
          {
            property: "default",
            label: "frosh-tools.tabs.feature-flags.default",
            rawData: true
          }
        ];
      }
    },
    methods: {
      async refresh() {
        await this.createdComponent();
      },
      async createdComponent() {
        this.isLoading = true;
        this.featureFlags = await this.froshToolsService.getFeatureFlags();
        this.isLoading = false;
      },
      async toggle(flag) {
        this.isLoading = true;
        await this.froshToolsService.toggleFeatureFlag(flag).then(async () => {
          this.featureFlags = await this.froshToolsService.getFeatureFlags();
          window.location.reload();
        }).catch((error) => {
          try {
            this.createNotificationError({
              message: error.response.data.errors[0].detail
            });
          } catch (e) {
            console.error(error);
          }
          this.isLoading = false;
        });
      }
    }
  });

  // src/Resources/app/administration/src/module/frosh-tools/component/frosh-tools-tab-state-machines/template.html.twig
  var template_html_default2 = `<sw-card-view>
    <sw-card
        class="frosh-tools-tab-state-machines__state-machines-card"
        :title="$tc('frosh-tools.tabs.state-machines.title')"
        :isLoading="isLoading"
        :large="true"
    >

        <div class="frosh-tools-tab-state-machines__state-machines-card-image-wrapper">
            <img id="state_machine" class="frosh-tools-tab-state-machines__state-machines-card-image" type="image/svg+xml" src="/bundles/administration/static/img/empty-states/media-empty-state.svg" alt="State Machine" width="100%" height="auto" style="text-align:center; display:inline-block; opacity:0; "/>
        </div>
            
        <template #toolbar>
            <sw-select-field size="small" aside="true" @change="onStateMachineChange(event)" :label=" $tc('frosh-tools.tabs.state-machines.label') " :helpText="$tc('frosh-tools.tabs.state-machines.helpText')">
                <option selected="selected" value="">{{ $tc('frosh-tools.chooseStateMachine') }}</option>
                <option value="order.state">{{ $tc('frosh-tools.order') }}</option>
                <option value="order_transaction.state">{{ $tc('frosh-tools.transaction') }}</option>
                <option value="order_delivery.state">{{ $tc('frosh-tools.delivery') }}</option>
            </sw-select-field>
        </template>

    </sw-card>
</sw-card-view>
`;

  // src/Resources/app/administration/src/module/frosh-tools/component/frosh-tools-tab-state-machines/index.js
  var { Component: Component11, Mixin: Mixin8 } = Shopware;
  Component11.register("frosh-tools-tab-state-machines", {
    template: template_html_default2,
    inject: ["froshToolsService"],
    mixins: [
      Mixin8.getByName("notification")
    ],
    data() {
      return {
        image: null,
        featureFlags: null,
        isLoading: true
      };
    },
    created() {
      this.createdComponent();
    },
    computed: {
      columns() {
        return [
          {
            property: "flag",
            label: "frosh-tools.tabs.feature-flags.flag",
            rawData: true
          },
          {
            property: "active",
            label: "frosh-tools.active",
            rawData: true
          },
          {
            property: "description",
            label: "frosh-tools.tabs.feature-flags.description",
            rawData: true
          },
          {
            property: "major",
            label: "frosh-tools.tabs.feature-flags.major",
            rawData: true
          },
          {
            property: "default",
            label: "frosh-tools.tabs.feature-flags.default",
            rawData: true
          }
        ];
      }
    },
    methods: {
      createdComponent() {
        this.isLoading = false;
      },
      async onStateMachineChange(event) {
        const response = await this.froshToolsService.stateMachines(event.srcElement.value);
        const elem = document.getElementById("state_machine");
        if ("svg" in response) {
          this.image = response.svg;
          elem.src = this.image;
          elem.style.opacity = "1";
          elem.style.width = "100%";
          elem.style.height = "auto";
        } else {
          elem.style.opacity = "0";
        }
      }
    }
  });

  // src/Resources/app/administration/src/module/frosh-tools/page/index/template.twig
  var template_default10 = `<sw-page class="frosh-tools">
    <template slot="content">
        <sw-container>
            <sw-tabs :small="false">
                <sw-tabs-item :route="{ name: 'frosh.tools.index.index' }">
                    {{ $tc('frosh-tools.tabs.index.title') }}
                </sw-tabs-item>

{#                <sw-tabs-item :route="{ name: 'frosh.tools.index.index' }">#}
{#                    {{ $tc('frosh-tools.tabs.systemInfo.title') }}#}
{#                </sw-tabs-item>#}

                <sw-tabs-item :route="{ name: 'frosh.tools.index.cache' }">
                    {{ $tc('frosh-tools.tabs.cache.title') }}
                </sw-tabs-item>

                <sw-tabs-item :route="{ name: 'frosh.tools.index.scheduled' }">
                    {{ $tc('frosh-tools.tabs.scheduledTaskOverview.title') }}
                </sw-tabs-item>

                <sw-tabs-item :route="{ name: 'frosh.tools.index.queue' }">
                    {{ $tc('frosh-tools.tabs.queue.title') }}
                </sw-tabs-item>

                <sw-tabs-item :route="{ name: 'frosh.tools.index.logs' }">
                    {{ $tc('frosh-tools.tabs.logs.title') }}
                </sw-tabs-item>

                <sw-tabs-item :route="{ name: 'frosh.tools.index.files' }">
                    {{ $tc('frosh-tools.tabs.files.title') }}
                </sw-tabs-item>

                <sw-tabs-item :route="{ name: 'frosh.tools.index.elasticsearch' }">
                    {{ $tc('frosh-tools.tabs.elasticsearch.title') }}
                </sw-tabs-item>

                <sw-tabs-item :route="{ name: 'frosh.tools.index.featureflags' }">
                    {{ $tc('frosh-tools.tabs.feature-flags.title') }}
                </sw-tabs-item>
                
                <sw-tabs-item :route="{ name: 'frosh.tools.index.statemachines' }">
                    {{ $tc('frosh-tools.tabs.state-machines.title') }}
                </sw-tabs-item>

            </sw-tabs>
        </sw-container>

        <router-view></router-view>
    </template>
</sw-page>
`;

  // src/Resources/app/administration/src/module/frosh-tools/page/index/index.js
  var { Component: Component12 } = Shopware;
  Component12.register("frosh-tools-index", {
    template: template_default10
  });

  // src/Resources/app/administration/src/module/frosh-tools/index.js
  Shopware.Module.register("frosh-tools", {
    type: "plugin",
    name: "frosh-tools.title",
    title: "frosh-tools.title",
    description: "",
    color: "#303A4F",
    icon: "default-device-dashboard",
    routes: {
      index: {
        component: "frosh-tools-index",
        path: "index",
        children: {
          index: {
            component: "frosh-tools-tab-index",
            path: "index",
            meta: {
              parentPath: "frosh.tools.index"
            }
          },
          cache: {
            component: "frosh-tools-tab-cache",
            path: "cache",
            meta: {
              parentPath: "frosh.tools.index"
            }
          },
          queue: {
            component: "frosh-tools-tab-queue",
            path: "queue",
            meta: {
              parentPath: "frosh.tools.index"
            }
          },
          scheduled: {
            component: "frosh-tools-tab-scheduled",
            path: "scheduled",
            meta: {
              parentPath: "frosh.tools.index"
            }
          },
          elasticsearch: {
            component: "frosh-tools-tab-elasticsearch",
            path: "elasticsearch",
            meta: {
              parentPath: "frosh.tools.index"
            }
          },
          logs: {
            component: "frosh-tools-tab-logs",
            path: "logs",
            meta: {
              parentPath: "frosh.tools.index"
            }
          },
          files: {
            component: "frosh-tools-tab-files",
            path: "files",
            meta: {
              parentPath: "frosh.tools.index"
            }
          },
          featureflags: {
            component: "frosh-tools-tab-feature-flags",
            path: "feature-flags",
            meta: {
              parentPath: "frosh.tools.index"
            }
          },
          statemachines: {
            component: "frosh-tools-tab-state-machines",
            path: "state-machines",
            meta: {
              parentPath: "frosh.tools.index"
            }
          }
        }
      }
    },
    settingsItem: [
      {
        group: "plugins",
        to: "frosh.tools.index.cache",
        icon: "default-action-settings",
        name: "frosh-tools.title"
      }
    ]
  });
})();
