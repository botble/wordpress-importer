/******/ (function(modules) { // webpackBootstrap
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {}
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, { enumerable: true, get: getter });
/******/ 		}
/******/ 	};
/******/
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = function(exports) {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/
/******/ 	// create a fake namespace object
/******/ 	// mode & 1: value is a module id, require it
/******/ 	// mode & 2: merge all properties of value into the ns
/******/ 	// mode & 4: return value when already ns object
/******/ 	// mode & 8|1: behave like require
/******/ 	__webpack_require__.t = function(value, mode) {
/******/ 		if(mode & 1) value = __webpack_require__(value);
/******/ 		if(mode & 8) return value;
/******/ 		if((mode & 4) && typeof value === 'object' && value && value.__esModule) return value;
/******/ 		var ns = Object.create(null);
/******/ 		__webpack_require__.r(ns);
/******/ 		Object.defineProperty(ns, 'default', { enumerable: true, value: value });
/******/ 		if(mode & 2 && typeof value != 'string') for(var key in value) __webpack_require__.d(ns, key, function(key) { return value[key]; }.bind(null, key));
/******/ 		return ns;
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "/";
/******/
/******/
/******/ 	// Load entry module and return exports
/******/ 	return __webpack_require__(__webpack_require__.s = 51);
/******/ })
/************************************************************************/
/******/ ({

/***/ "./platform/plugins/wordpress-importer/resources/assets/js/wordpress-importer.js":
/*!***************************************************************************************!*\
  !*** ./platform/plugins/wordpress-importer/resources/assets/js/wordpress-importer.js ***!
  \***************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

function ownKeys(object, enumerableOnly) { var keys = Object.keys(object); if (Object.getOwnPropertySymbols) { var symbols = Object.getOwnPropertySymbols(object); if (enumerableOnly) symbols = symbols.filter(function (sym) { return Object.getOwnPropertyDescriptor(object, sym).enumerable; }); keys.push.apply(keys, symbols); } return keys; }

function _objectSpread(target) { for (var i = 1; i < arguments.length; i++) { var source = arguments[i] != null ? arguments[i] : {}; if (i % 2) { ownKeys(Object(source), true).forEach(function (key) { _defineProperty(target, key, source[key]); }); } else if (Object.getOwnPropertyDescriptors) { Object.defineProperties(target, Object.getOwnPropertyDescriptors(source)); } else { ownKeys(Object(source)).forEach(function (key) { Object.defineProperty(target, key, Object.getOwnPropertyDescriptor(source, key)); }); } } return target; }

function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } }

function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); return Constructor; }

var WordpressImporter = /*#__PURE__*/function () {
  function WordpressImporter() {
    _classCallCheck(this, WordpressImporter);

    this.categoryList = $('#category-select');
    this.categoryCheckbox = $('#copy_categories');
    this.listen();
  }

  _createClass(WordpressImporter, [{
    key: "listen",
    value: function listen() {
      var _this = this;

      $(document).on('click', '.import-wordpress-data', this["import"].bind(this));
      this.categoryCheckbox.on('change', function (e) {
        _this.toggleCategory(e.target.checked);
      });
    }
  }, {
    key: "toggleCategory",
    value: function toggleCategory() {
      var show = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : true;

      if (show) {
        this.categoryList.slideUp();
      } else {
        this.categoryList.slideDown();
        setTimeout(this.loadCategory.bind(this), 500);
      }
    }
  }, {
    key: "loadCategory",
    value: function loadCategory() {
      var _this2 = this;

      if (this.categoryList.hasClass('loaded')) {
        return;
      }

      this.categoryList.addClass('loaded');
      this.call({
        url: '/api/v1/categories'
      }).then(function (res) {
        var $ul = _this2.categoryList.find('ul');

        $ul.empty();

        if (!res.error && res.data.length) {
          res.data.forEach(function (item, index) {
            $ul.append("<li class=\"".concat(item.slug, "\">\n                        <label for=\"").concat(item.slug, "\" class=\"control-label\">\n                            <input ").concat(index === 0 ? 'checked' : '', " type=\"radio\" value=\"").concat(item.id, "\" name=\"default_category_id\" id=\"").concat(item.slug, "\">\n                            <span>").concat(item.name, "</span>\n                        </label>\n                    </li>"));
          });
        }
      });
    }
  }, {
    key: "import",
    value: function _import(event) {
      event.preventDefault();

      var _self = $(event.currentTarget);

      $('.wordpress-importer .alert').addClass('hidden');

      _self.addClass('button-loading');

      this.call({
        type: 'POST',
        url: _self.closest('form').prop('action'),
        data: new FormData(_self.closest('form')[0])
      }).then(function (res) {
        if (!res.error) {
          Botble.showSuccess(res.message);
          $('.wordpress-importer .success-message').removeClass('hidden').text(res.message);
        } else {
          Botble.showError(res.message);
          $('.wordpress-importer .error-message').removeClass('hidden').text(res.message);
        }

        _self.removeClass('button-loading');
      }, function (error) {
        Botble.handleError(error);

        _self.removeClass('button-loading');
      });
    }
  }, {
    key: "call",
    value: function call(obj) {
      return new Promise(function (resolve, reject) {
        $.ajax(_objectSpread(_objectSpread({
          type: 'GET',
          contentType: false,
          processData: false
        }, obj), {}, {
          success: function success(res) {
            resolve(res);
          },
          error: function error(res) {
            reject(res);
          }
        }));
      });
    }
  }]);

  return WordpressImporter;
}();

$(document).ready(function () {
  new WordpressImporter();
});

/***/ }),

/***/ 51:
/*!*********************************************************************************************!*\
  !*** multi ./platform/plugins/wordpress-importer/resources/assets/js/wordpress-importer.js ***!
  \*********************************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__(/*! /Users/mac/workspace/cms/platform/plugins/wordpress-importer/resources/assets/js/wordpress-importer.js */"./platform/plugins/wordpress-importer/resources/assets/js/wordpress-importer.js");


/***/ })

/******/ });