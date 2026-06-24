il = il || {};
il.UI = il.UI || {};
(function($, ui) {
	ui.page = (function($) {
		var _cls_page_content = '.il-layout-page-content',
		    _page_overlay = '.il-page-overlay',
			_id_right_col = '#il_right_col';

		var breakpoint_max_width = 992, //this corresponds to @grid-float-breakpoint-max, see mainbar.less/metabar.less
			resized_poppers_margin = 25, //dropdown, date-picker
			mq_orientation = window.matchMedia("(orientation: portrait)");

		var getOverlay = function () {
			return document.querySelector(_page_overlay);
		}

		var isSmallScreen = function() {
			var media_query = "only screen"
				+ " and (max-width: " + breakpoint_max_width + "px)";
			return window.matchMedia(media_query).matches;
		};

		var isLandscape = function() {
			return mq_orientation.matches === false;
		};
		var isPortrait = function() {
			return mq_orientation.matches;
		};
		var getOrientation = function() {
			return isPortrait() ? 'portrait' : 'landscape';
		};

		var fitContainerToPageContent = function(target_container) {
			var content_container = $(_cls_page_content)
				right_column = $(_id_right_col);

			if(!content_container.length || 
				!isContainerInPageContent(target_container)){
				return;
			}

			var	margin = resized_poppers_margin,
				max_width = content_container.width() - 2 * margin,
				target_left = content_container.offset().left - target_container.parent().offset().left + margin;

			if(right_column.length > 0) {
				max_width = max_width - right_column.width();
			}

			if( (target_container.width() < max_width && target_container.offset().left > content_container.offset().left)
				|| max_width < 0
			) {
				return;
			}

			window.setTimeout(function(){
				target_container.css({
					'left': target_left,
					'max-width': max_width
				});
			}, 100)
		};

		var isContainerInPageContent = function(container){
			return container.parents(_cls_page_content).length
		};

		return {
			getOverlay: getOverlay,
			isSmallScreen: isSmallScreen,
			getOrientation: getOrientation,
			isPortrait: isPortrait,
			isLandscape: isLandscape,
			fit: fitContainerToPageContent
		};

	})($);
})($, il.UI);
il.Util.addOnLoad(function () {
	window.setTimeout(
		function () {
			var marker = document.createElement("div");
			marker.textContent = "DIAGNOSTICO A11Y: stdpage.js cargado; forzando foco al formulario";
			marker.setAttribute("style", "position:fixed;z-index:999999;top:0;left:0;right:0;padding:12px;background:#b00020;color:#fff;font-size:18px;font-weight:bold;text-align:center;");
			document.body.appendChild(marker);

			var focusable = document.querySelector(
				"form.c-form .tagify [contenteditable='true'], " +
				"form.c-form input:not([type='hidden']):not([disabled]), " +
				"form.c-form select:not([disabled]), " +
				"form.c-form textarea:not([disabled]), " +
				"form.c-form button:not([disabled])"
			);

			if (focusable) {
				focusable.focus();
				focusable.scrollIntoView({ block: "center", inline: "nearest" });
				focusable.setAttribute("style", (focusable.getAttribute("style") || "") + ";outline:8px solid #b00020 !important;box-shadow:0 0 0 12px #ffd400 !important;");
				marker.textContent = "DIAGNOSTICO A11Y: foco forzado sobre " + focusable.tagName.toLowerCase();
			} else {
				marker.textContent = "DIAGNOSTICO A11Y: stdpage.js cargado, pero no se encontro ningun control en form.c-form";
			}
		}, 1000
	);
});
