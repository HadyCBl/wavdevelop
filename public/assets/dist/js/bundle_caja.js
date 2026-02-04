/*
 * ATTENTION: An "eval-source-map" devtool has been used.
 * This devtool is neither made for production nor for readable output files.
 * It uses "eval()" calls to create a separate source file with attached SourceMaps in the browser devtools.
 * If you are trying to read the output file, select a different devtool (https://webpack.js.org/configuration/devtool/)
 * or disable the default devtool with "devtool: false".
 * If you are looking for production-ready output files, see mode: "production" (https://webpack.js.org/configuration/mode/).
 */
/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./includes/js/bb_caja.js":
/*!********************************!*\
  !*** ./includes/js/bb_caja.js ***!
  \********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n/* harmony export */ __webpack_require__.d(__webpack_exports__, {\n/* harmony export */   updateSubtotal: () => (/* binding */ updateSubtotal),\n/* harmony export */   updateTotal: () => (/* binding */ updateTotal)\n/* harmony export */ });\n// Entry específico para el módulo CAJA\n// // Aquí inicializamos Alpine sólo para esta página\n// import Alpine from \"alpinejs\";\n\n// // Importa persist si lo necesitas en este módulo\n// // import persist from '@alpinejs/persist'\n// // Alpine.plugin(persist)\n\n// window.Alpine = Alpine;\n// Alpine.start();\n\nfunction updateSubtotal(inputId) {\n  var moneda = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : \"GTQ\";\n  var input = document.getElementById(inputId);\n  var den = parseFloat(input.dataset.den);\n  var qty = parseInt(input.value) || 0;\n  var subtotal = den * qty;\n  document.getElementById(\"s\" + inputId).textContent = moneda + \" \" + subtotal.toFixed(2);\n  updateTotal(moneda);\n}\nfunction updateTotal() {\n  var moneda = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : \"GTQ\";\n  var total = 0;\n  document.querySelectorAll(\".den-input\").forEach(function (inp) {\n    var den = parseFloat(inp.dataset.den);\n    var qty = parseInt(inp.value) || 0;\n    total += den * qty;\n  });\n  document.getElementById(\"totalGeneral\").textContent = moneda + \" \" + total.toFixed(2);\n  document.getElementById(\"montoTotal\").value = total.toFixed(2);\n}\nif (typeof window !== 'undefined') {\n  window.updateSubtotal = updateSubtotal;\n  window.updateTotal = updateTotal;\n}\n\n// export default Alpine;//# sourceURL=[module]\n//# sourceMappingURL=data:application/json;charset=utf-8;base64,eyJ2ZXJzaW9uIjozLCJmaWxlIjoiLi9pbmNsdWRlcy9qcy9iYl9jYWphLmpzIiwibWFwcGluZ3MiOiI7Ozs7O0FBQUE7QUFDQTtBQUNBOztBQUVBO0FBQ0E7QUFDQTs7QUFFQTtBQUNBOztBQUVPLFNBQVNBLGNBQWNBLENBQUNDLE9BQU8sRUFBa0I7RUFBQSxJQUFoQkMsTUFBTSxHQUFBQyxTQUFBLENBQUFDLE1BQUEsUUFBQUQsU0FBQSxRQUFBRSxTQUFBLEdBQUFGLFNBQUEsTUFBRyxLQUFLO0VBQ3BELElBQU1HLEtBQUssR0FBR0MsUUFBUSxDQUFDQyxjQUFjLENBQUNQLE9BQU8sQ0FBQztFQUM5QyxJQUFNUSxHQUFHLEdBQUdDLFVBQVUsQ0FBQ0osS0FBSyxDQUFDSyxPQUFPLENBQUNGLEdBQUcsQ0FBQztFQUN6QyxJQUFNRyxHQUFHLEdBQUdDLFFBQVEsQ0FBQ1AsS0FBSyxDQUFDUSxLQUFLLENBQUMsSUFBSSxDQUFDO0VBQ3RDLElBQU1DLFFBQVEsR0FBR04sR0FBRyxHQUFHRyxHQUFHO0VBQzFCTCxRQUFRLENBQUNDLGNBQWMsQ0FBQyxHQUFHLEdBQUdQLE9BQU8sQ0FBQyxDQUFDZSxXQUFXLEdBQ2hEZCxNQUFNLEdBQUcsR0FBRyxHQUFHYSxRQUFRLENBQUNFLE9BQU8sQ0FBQyxDQUFDLENBQUM7RUFDcENDLFdBQVcsQ0FBQ2hCLE1BQU0sQ0FBQztBQUNyQjtBQUVPLFNBQVNnQixXQUFXQSxDQUFBLEVBQWlCO0VBQUEsSUFBaEJoQixNQUFNLEdBQUFDLFNBQUEsQ0FBQUMsTUFBQSxRQUFBRCxTQUFBLFFBQUFFLFNBQUEsR0FBQUYsU0FBQSxNQUFHLEtBQUs7RUFDeEMsSUFBSWdCLEtBQUssR0FBRyxDQUFDO0VBQ2JaLFFBQVEsQ0FBQ2EsZ0JBQWdCLENBQUMsWUFBWSxDQUFDLENBQUNDLE9BQU8sQ0FBQyxVQUFDQyxHQUFHLEVBQUs7SUFDdkQsSUFBTWIsR0FBRyxHQUFHQyxVQUFVLENBQUNZLEdBQUcsQ0FBQ1gsT0FBTyxDQUFDRixHQUFHLENBQUM7SUFDdkMsSUFBTUcsR0FBRyxHQUFHQyxRQUFRLENBQUNTLEdBQUcsQ0FBQ1IsS0FBSyxDQUFDLElBQUksQ0FBQztJQUNwQ0ssS0FBSyxJQUFJVixHQUFHLEdBQUdHLEdBQUc7RUFDcEIsQ0FBQyxDQUFDO0VBQ0ZMLFFBQVEsQ0FBQ0MsY0FBYyxDQUFDLGNBQWMsQ0FBQyxDQUFDUSxXQUFXLEdBQ2pEZCxNQUFNLEdBQUcsR0FBRyxHQUFHaUIsS0FBSyxDQUFDRixPQUFPLENBQUMsQ0FBQyxDQUFDO0VBQ2pDVixRQUFRLENBQUNDLGNBQWMsQ0FBQyxZQUFZLENBQUMsQ0FBQ00sS0FBSyxHQUFHSyxLQUFLLENBQUNGLE9BQU8sQ0FBQyxDQUFDLENBQUM7QUFDaEU7QUFFQSxJQUFJLE9BQU9NLE1BQU0sS0FBSyxXQUFXLEVBQUU7RUFDakNBLE1BQU0sQ0FBQ3ZCLGNBQWMsR0FBR0EsY0FBYztFQUN0Q3VCLE1BQU0sQ0FBQ0wsV0FBVyxHQUFHQSxXQUFXO0FBQ2xDOztBQUVBIiwic291cmNlcyI6WyJ3ZWJwYWNrOi8vbWljcm9zeXN0ZW1wbHVzLy4vaW5jbHVkZXMvanMvYmJfY2FqYS5qcz9lMDEzIl0sInNvdXJjZXNDb250ZW50IjpbIi8vIEVudHJ5IGVzcGVjw61maWNvIHBhcmEgZWwgbcOzZHVsbyBDQUpBXG4vLyAvLyBBcXXDrSBpbmljaWFsaXphbW9zIEFscGluZSBzw7NsbyBwYXJhIGVzdGEgcMOhZ2luYVxuLy8gaW1wb3J0IEFscGluZSBmcm9tIFwiYWxwaW5lanNcIjtcblxuLy8gLy8gSW1wb3J0YSBwZXJzaXN0IHNpIGxvIG5lY2VzaXRhcyBlbiBlc3RlIG3Ds2R1bG9cbi8vIC8vIGltcG9ydCBwZXJzaXN0IGZyb20gJ0BhbHBpbmVqcy9wZXJzaXN0J1xuLy8gLy8gQWxwaW5lLnBsdWdpbihwZXJzaXN0KVxuXG4vLyB3aW5kb3cuQWxwaW5lID0gQWxwaW5lO1xuLy8gQWxwaW5lLnN0YXJ0KCk7XG5cbmV4cG9ydCBmdW5jdGlvbiB1cGRhdGVTdWJ0b3RhbChpbnB1dElkLCBtb25lZGEgPSBcIkdUUVwiKSB7XG4gIGNvbnN0IGlucHV0ID0gZG9jdW1lbnQuZ2V0RWxlbWVudEJ5SWQoaW5wdXRJZCk7XG4gIGNvbnN0IGRlbiA9IHBhcnNlRmxvYXQoaW5wdXQuZGF0YXNldC5kZW4pO1xuICBjb25zdCBxdHkgPSBwYXJzZUludChpbnB1dC52YWx1ZSkgfHwgMDtcbiAgY29uc3Qgc3VidG90YWwgPSBkZW4gKiBxdHk7XG4gIGRvY3VtZW50LmdldEVsZW1lbnRCeUlkKFwic1wiICsgaW5wdXRJZCkudGV4dENvbnRlbnQgPVxuICAgIG1vbmVkYSArIFwiIFwiICsgc3VidG90YWwudG9GaXhlZCgyKTtcbiAgdXBkYXRlVG90YWwobW9uZWRhKTtcbn1cblxuZXhwb3J0IGZ1bmN0aW9uIHVwZGF0ZVRvdGFsKG1vbmVkYSA9IFwiR1RRXCIpIHtcbiAgbGV0IHRvdGFsID0gMDtcbiAgZG9jdW1lbnQucXVlcnlTZWxlY3RvckFsbChcIi5kZW4taW5wdXRcIikuZm9yRWFjaCgoaW5wKSA9PiB7XG4gICAgY29uc3QgZGVuID0gcGFyc2VGbG9hdChpbnAuZGF0YXNldC5kZW4pO1xuICAgIGNvbnN0IHF0eSA9IHBhcnNlSW50KGlucC52YWx1ZSkgfHwgMDtcbiAgICB0b3RhbCArPSBkZW4gKiBxdHk7XG4gIH0pO1xuICBkb2N1bWVudC5nZXRFbGVtZW50QnlJZChcInRvdGFsR2VuZXJhbFwiKS50ZXh0Q29udGVudCA9XG4gICAgbW9uZWRhICsgXCIgXCIgKyB0b3RhbC50b0ZpeGVkKDIpO1xuICBkb2N1bWVudC5nZXRFbGVtZW50QnlJZChcIm1vbnRvVG90YWxcIikudmFsdWUgPSB0b3RhbC50b0ZpeGVkKDIpO1xufVxuXG5pZiAodHlwZW9mIHdpbmRvdyAhPT0gJ3VuZGVmaW5lZCcpIHtcbiAgd2luZG93LnVwZGF0ZVN1YnRvdGFsID0gdXBkYXRlU3VidG90YWw7XG4gIHdpbmRvdy51cGRhdGVUb3RhbCA9IHVwZGF0ZVRvdGFsO1xufVxuXG4vLyBleHBvcnQgZGVmYXVsdCBBbHBpbmU7XG4iXSwibmFtZXMiOlsidXBkYXRlU3VidG90YWwiLCJpbnB1dElkIiwibW9uZWRhIiwiYXJndW1lbnRzIiwibGVuZ3RoIiwidW5kZWZpbmVkIiwiaW5wdXQiLCJkb2N1bWVudCIsImdldEVsZW1lbnRCeUlkIiwiZGVuIiwicGFyc2VGbG9hdCIsImRhdGFzZXQiLCJxdHkiLCJwYXJzZUludCIsInZhbHVlIiwic3VidG90YWwiLCJ0ZXh0Q29udGVudCIsInRvRml4ZWQiLCJ1cGRhdGVUb3RhbCIsInRvdGFsIiwicXVlcnlTZWxlY3RvckFsbCIsImZvckVhY2giLCJpbnAiLCJ3aW5kb3ciXSwic291cmNlUm9vdCI6IiJ9\n//# sourceURL=webpack-internal:///./includes/js/bb_caja.js\n");

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The require scope
/******/ 	var __webpack_require__ = {};
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module can't be inlined because the eval-source-map devtool is used.
/******/ 	var __webpack_exports__ = {};
/******/ 	__webpack_modules__["./includes/js/bb_caja.js"](0, __webpack_exports__, __webpack_require__);
/******/ 	
/******/ })()
;