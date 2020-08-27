/*
System.config({
	baseUrl: "<?php echo Yii::app()->request->baseUrl; ?>/js",
	map: {
		"bootstrap-timepicker" : "/JiraTime/js/bootstrap-timepicker-0.5.2/js/bootstrap-timepicker.min.js",
		css: "/JiraTime/js/systemjs-css-loader.js",  // plugin to load css as a dependency
		fullcalendar: "/JiraTime/js/fullcalendar-3.9.0/fullcalendar.min.js",
		moment: "/JiraTime/js/fullcalendar-3.9.0/lib/moment.min.js"
		// NetworkConfig: "/js/app/NetworkConfig.js",
		// tinymce: "/js/app/tinymce_4.5.6/compressor/tinymce_gzip.js",
		// WebSocketInterface: "/js/app/WebSocketInterface.js"
	},
	meta: {
		"*.css": { loader: "css" },
		"bootstrap-timepicker" : {
			deps: [
				"/JiraTime/js/fullcalendar-3.9.0/css/bootstrap-timepicker.min.css"
			]
		},
		fullcalendar: {
			deps: [
				// not sure why, but moment.js needs placed under "globals"
				"/JiraTime/js/fullcalendar-3.9.0/fullcalendar.min.css"
			],
			format: "global",
			globals: {
				// moment: "/js/app/fullcalendar-3.3.1/lib/moment.min.js"
				moment: "moment",
			}
		},
		moment: { format: "global" }
	}
});
*/

$(function() {

	var $curDayHover = $('<span></span>'); // used in convoluted month view day hover solution
	var issues = {};	// for populating issue dropdown
	var totals = {};
	var uriRoot = document.location.pathname;
	var clearCache = false;

	$.fn.getDayCell = function(mouseEvent) {
		var calendarId = $(this).attr('id'),
			days = $('#'+calendarId + ' .fc-day'),
			day, mouseX, mouseY, offset, width, height,
			i,
			l = days.length;
		for(i = 0; i < l; i++) {
			day = $(days[i]);
			mouseX = mouseEvent.pageX;
			mouseY = mouseEvent.pageY;
			offset = day.offset();
			width  = day.width();
			height = day.height();
			if (   mouseX >= offset.left && mouseX <= offset.left+width
				&& mouseY >= offset.top  && mouseY <= offset.top+height
			) {
			   return day;
			}
		}
	};

	// page is now ready, initialize the calendar...

	$('#calendar').fullCalendar({
		// put your options and callbacks here
		allDayText: "Total",
		// defaultDate: "2018-07-13",
		defaultView: "agendaWeek",
		editable: true, // drag/drop/resize
		maxTime: "18:00:00",
		minTime: "07:00:00",
		nowIndicator: true,
		slotDuration: '00:30:00',
		weekends: false,

		events: function(start, end, timezone, callback) {
			console.info("events");
			/*
			if (!cookieGet("username") || !cookieGet("password")) {
				$("#modal-settings").modal("show");
				return;
			}
			*/
			$("body").addClass("loading");
			// $("#filter_start").val(start.format());
			// $("#filter_end").val(end.format());
			$.ajax({
				url: uriRoot + "worklog",
				method: "GET",
				data: {
					clearCache: clearCache,
					start: start.format(),
					end: end.format()
				}
			}).then(function(response){
				console.log('response', response);
				if (response.success == false && response.message == 'config') {
					$("#modal-settings").modal("show");
					return;
				}
				issues = response.issues;
				callback(response.events);
			}).fail(function(){
			}).always(function(){
				clearCache = false;
				$("body").removeClass("loading");
			});
		},

		customButtons: {
			// see also 'header'
			refresh: {
				icon: "glyphicon glyphicon glyphicon-refresh", // yes glyphicon is repeated... for reasons
				text: 'Clear cache & reload',
				click: function() {
					clearCache = true;
					$("#calendar").fullCalendar("refetchEvents");
				}
			}
		},

		header: {
			left: "title",
			center: "",
			right: "today refresh prev,next"
		},

		dayClick: function(date, evt, view) {
			// ie clicking on calendar, but not on event
			console.info('dayClick');
			console.log({
				date: date,
				evt: evt,
				view: view
			});
			/*
			if (view.type == 'month') {
				$("#calendar").fullCalendar('changeView', 'agendaWeek');
				$("#calendar").fullCalendar('gotoDate', date);
			} else if (view.type == "agendaWeek") {
				// open up create apt modal
				parent.openEditModal(null, {
					apt_date_scheduled : date.format("YYYY-MM-DD"),
					apt_time : date.format("h:mm A")
				});
			}
			*/
			if (!date.hasTime()) {
				// allDay...
				return;
			}
			openEditModal(null, {
				worklog_start_date : date.format("YYYY-MM-DD"),
				worklog_start_time : date.format("HH:mm")
			});
		},
		dayRender: function(date, cell) {
			console.log('dayRender');
			// totals[date.format("YYYY-MM-DD")] = 0;
		},
		eventAfterAllRender: function(view) {
			console.log('eventAfterAllRender');
			totals = {};
			$('#calendar').fullCalendar('clientEvents', function(event) {
				var date = event.start.format('YYYY-MM-DD');
				var duration = moment.duration(event.end.diff(event.start));
				if (totals[date] === undefined) {
					totals[date] = 0;
				}
				totals[date] += duration.asMinutes();
			});
			console.log('totals', totals);
			for (var date in totals) {
				$('.fc-day-grid .fc-day[data-date="' + date + '"]')
					.addClass("day-total")
					.html(formatDuration(totals[date], false));
			}
		},
		eventClick: function(event, evt, view) {
			var eventId = event.id;
			console.warn('event click', {
				event: event,
				evt: evt,
				view: view
			});
			openEditModal(eventId);
		},
		eventDragStart: function() {
			eventType = "drag";	// used by eventAllow
		},
		eventDrop: function(event, delta, revertFunc, evt, ui, view) {
			console.info('drop', event);
			if (window.confirm("Are you sure you want to update this entry?")) {
				$.ajax({
					url: uriRoot + 'editWorklog',
					type: "POST",
					data: {
						id: event.id,
						"start-date": event.start.format("YYYY-MM-DD"),
						"start-time": event.start.format("HH:mm:ss"),
						timeSpentSeconds: event.end.diff(event.start, "seconds"),
						issueKey: event.issueKey,
						comment: event.comment
					}
				});
			} else {
				revertFunc();
			}
		},
		eventMouseout: function(event, evt, view) {
			var $node = $(this);
			if (["month","agendaWeek"].indexOf(view.type) > -1) {
				$node.removeClass("hover");
			}
		},
		eventMouseover: function(event, evt, view) {
			var $node = $(this);
			if (["month","agendaWeek"].indexOf(view.type) > -1) {
				$node.addClass("hover");
			}
		},
		/*
		eventRender: function(event, $element, view) {
			console.log({
				event: event,
				element: $element,
				view: view
			});
		}
		*/
		eventResize: function(event, delta, revertFunc, evt, ui, view) {
			// console.info('resize', event);
			// fullcalendar doesn't give a lot of control over the durations when resizing..
			// find the greatest avail duration <= the resized duration
			/*
			var i, duration, durationResized = event.end.diff(event.start, 'minutes');
			for (i = 0; i < options.durations.length; i++) {
				if (options.durations[i] <= durationResized) {
					duration = options.durations[i];
				} else {
					break;
				}
			}
			*/
			if (window.confirm("Are you sure you want to update this entry?")) {
				$.ajax({
					url: uriRoot + 'editWorklog',
					type: "POST",
					data: {
						id: event.id,
						"start-date": event.start.format("YYYY-MM-DD"),
						"start-time": event.start.format("HH:mm:ss"),
						timeSpentSeconds: event.end.diff(event.start, "seconds"),
						issueKey: event.issueKey,
						comment: event.comment
					}
				});
			} else {
				revertFunc();
			}
		},
		eventResizeStart: function() {
			eventType = "resize"; // used by eventAllow
		}
	});

	/*
		https://stackoverflow.com/questions/18487056/select2-doesnt-work-when-embedded-in-a-bootstrap-modal/19574076#19574076
	*/
	$.fn.modal.Constructor.prototype.enforceFocus = function() {};

	$("#worklog_issueKey").select2({
		dropdownCssClass: "talldrop",
		placeholder: "Select",
		selectOnClose: true,
		theme: "bootstrap",
	});

	$("#worklog_timeSpent").select2({
		selectOnClose: true,
		theme: "bootstrap"
	}).on('select2-opening', function(){
		$(this).data('select2')
			.$dropdown
			.find(':input.select2-search__field')
			.prop('placeholder', 'Add new value');
	});
	// https://stackoverflow.com/questions/49089243/select-current-item-when-tabbing-off-select2-input

	/*
	$("#modal-settings").on("change", "input", function () {
		var name = $(this).prop('name'),
			val = $(this).val();
		cookieSave(name, val, 30);
	});
	*/

	$("#modal-settings").on("submit", function (evt) {
		evt.preventDefault();
		if (this.checkValidity()) {
			$("#modal-settings input").each(function () {
				var name = $(this).prop('name'),
					val = $(this).val();
				cookieSave(name, val, 30);
				cookieSave()
			});
			$(this).modal("hide");
			$("#calendar").fullCalendar("refetchEvents");
		}
	});

	/*
		These two convoluted event listeners provide "hover" functionality
		@see https://github.com/fullcalendar/fullcalendar-scheduler/issues/102
	*/
	$("#calendar").on("mouseenter", '.fc-widget-content', function(){
		var i;
		if (!$(this).html()) {
			for (i=0; i<5; i++){
				$(this).append('<td class="temp-cell" style="border: 0px; width:'+(Number($('.fc-day').width())+3)+'px"></td>');
			}
			$(this).children('td').each(function(){
				$(this).hover(function(){
					// console.log('time', $(this).parent().parent().data('time'));
					var time = moment($(this).parent().parent().data('time'), "HH:mm:ss");
					$(this).html('<div class="current-time text-center">'+
						//$(this).parent().parent().data('time').substring(0,5)+
						time.format("h:mma")+
						'</div>');
				},function(){
					$(this).html('');
				});
			});
		}
	});
	$("#calendar").on("mouseleave", '.fc-widget-content', function(){
		// console.log('mouseleave', this);
		$(this).children('.temp-cell').remove();
		$curDayHover.removeClass("hover"); // handles "leaving the calendar"
	});

	$("#calendar").on("mousemove", ".fc-month-view", _.debounce(function(evt){
		var $calendar = $("#calendar"),
			$cell = $calendar.getDayCell(evt);
		if ($cell && !$cell.hasClass("hover")) {
			$curDayHover.removeClass("hover");
			$cell.addClass("hover");
			$curDayHover = $cell;
		}
	}, 100));

	$("body").on("submit", "#modal_edit", function(e){
		e.preventDefault();
		$("body").addClass("loading");
		var $modal = $(this);
		$modal.find(".alert").remove();
		$modal.find(".error").removeClass("error");
		$.ajax({
			url: uriRoot + "editWorklog",
			type: "POST",
			data: $(this).serialize()
		}).done(function(response){
			console.log('done', response);
			$modal.modal("hide");
			// populate title
			if (issues[response.worklog.issueKey]) {
				response.worklog.title = issues[response.worklog.issueKey]
			}
			if ($("#worklog_id").val()) {
				findWorklog($("#worklog_id").val()).then(function(worklogExisting){
					var event = $.extend({}, worklogExisting, response.worklog);
					$("#calendar").fullCalendar("updateEvent", event);
				});
			} else {
				console.info('adding worklog', response.worklog);
				$("#calendar").fullCalendar("renderEvent", response.worklog);
			}
			$("body").removeClass("loading");
		}).fail(function(){
			console.log('fail');
			$("body").removeClass("loading");
		});
	});

	$(".input-shortcut").on("click", function(){
		var $target = $($(this).data("target")),
			value = $(this).data("value");
		$target.val(value);
	});

	/**
	 * Fullcalendar doesn't automatically refresh the today btn...
	 * poll every 15 minutes to see if today btn needs enabled
	 * (ie calendar left open over weekend in week view...  today btn should be enabled on Monday)
	 */
	setInterval(function() {
		var datetime = new Date();
		console.log('checking if need to activate today btn');
		if ($('#calendar').fullCalendar('getView').end < datetime) {
			$('#calendar button.fc-today-button').prop('disabled', false);
			$('#calendar button.fc-today-button').removeClass('fc-state-disabled');
		}
	 }, 1000*60*15);

	function openEditModal(id, values, opts) {
		var self = this;
		var event = {};
		var $modal = $("#modal_edit");
		var duration;
		var durationStr = '';

		id = parseInt(id, 10) || null;
		opts = opts || {};

		// console.info("openEditModal", id);
		/*
		if (!$modal.length) {
			// $modal doesn't exist yet
			$.ajax({
				url: '/ajax/appointment/modals',
				dataType: "html"
			}).then(function(response){
				$("body").append(response);
				self.devices.buildTemplates();
				self.initEditModal();
				self.openEditModal(id, values, opts);
			});
			return;
		}
		*/

		/*
		if (!jQuery.fn.timepicker) {
			// console.log('loading timepicker');
			System.import("bootstrap-timepicker").then(function(){
				// console.log('timepicker loaded');
				$('#worklog_start_time').timepicker({
					// defaultTime: false,
					explicitMode : true,
					snapToStep : true
				}).on('show.timepicker', function(e){
					// widget UI does a poor job if no initial value..
					$(this).val(e.time.value);
				});
				// stupid/only way to close the widget on bluring the input via tab-out
				$("#apt_date_scheduled, #apt_duration").on("focus", function(){
					$('#apt_time').timepicker("hideWidget");
				})
			});
		}
		*/

		findWorklog(id).then(function(event){
			console.log('event', id, event);
			var valuesDefault = {
				modal_edit_title: id ? "Edit Worklog" : "New Worklog",
				worklog_id: id ? event.id : "",
				worklog_issueKey : id ? event.issueKey : '',
				worklog_start_date : id ? event.start.format("YYYY-MM-DD") : "",
				worklog_start_time : id ? event.start.format("HH:mm") : "",
				worklog_timeSpent : id
					? formatDuration(event.end.diff(event.start, 'minutes'))
					: $("#worklog_timeSpent").data("default"),
				worklog_comment : id ? event.comment : ''
			};
			values = $.extend(valuesDefault, values);

			// $("#worklog_issueKey").html('<option disabled selected value="">select</option>');
			$("#worklog_issueKey").html('');
			$.each(issues, function(id, value){
				var $opt = $('<option>').val(id).text(value);
				$("#worklog_issueKey").append($opt);
			})
			$.each(values, function(id, value){
				var $node = $modal.find("#"+id);
				if ($node.is(".select2-hidden-accessible")) {
					addSelect2Opt($node, value);
				} else if ($node.is(":input")) {
					$node.val(value);
				} else {
					$node.text(value);
				}
			});
			$modal.find(".alert").remove();
			$modal.find(".error").removeClass("error");
			$modal.find(":input").prop("disabled", false)
				.closest(".control-group").removeClass("disabled");	// enable all fields
			if (id) {
				// edit
				$modal.find(".edit-only").show();
			} else {
				// create
				$modal.find(".edit-only").hide();
			}
			$modal.modal("show");
		}).catch(console.error.bind(console));
	}

	function addSelect2Opt($node, value) {
		console.warn('add', value);
		if ($node.find("option[value='" + value + "']").length === 0) {
			// Create a DOM Option and pre-select by default
			// var newOption = new Option(value, value, true, true);
			// Append it to the select
			var $option = $('<option />').prop('value', value).text(value);
			$node.append($option);
		}
		$node.val(value);
		$node.trigger("change");
	}

	function findWorklog(id) {
		return new Promise(function(resolve, reject){
			if (!id) {
				resolve({});
			} else if ($("#calendar").length) {
				resolve( $("#calendar").fullCalendar("clientEvents", id)[0] || {} );
			} else {
				/*
				for (i = 0, len = self.appointments.length; i < len; i++) {
					apt = self.appointments[i];
					if (aptId == apt.appointment_id) {
						resolve(apt);
						return;
					}
				}
				// appointment not found... load it via ajax
				$.ajax({
					url: '/ajax/appointment/read/'+aptId,
					dataType: 'json'
				}).then(function(response){
					resolve(self.appointmentToEvent(response));
				});
				*/
			}
		});
	}

	function formatDuration(min, jiraFormat) {
		if (jiraFormat == undefined) {
			jiraFormat = true;
		}
		var min = parseInt(min, 10); // don't forget the second param
		var hours   = Math.floor(min / 60);
		var min = min - (hours * 60);
		// var seconds = sec_num - (hours * 3600) - (minutes * 60);
		var str = '';
		// if (hours   < 10) {hours   = "0"+hours;}
		// if (minutes < 10) {minutes = "0"+minutes;}
		// if (seconds < 10) {seconds = "0"+seconds;}
		if (jiraFormat) {
			if (hours) {
				str += hours+'h ';
			}
			if (min) {
				str += min+'m'
			}
		} else {
			if (hours) {
				str += hours+'&#8201;hr'+(hours != 1 ? 's' : '')+' ';
			}
			if (min) {
				str += min+'&#8201;m'
			}
		}
		return str.trim();
	}

	function cookieGet(name) {
		var nameEQ = name + "=",
			ca = document.cookie.split(';'),
			c = null;
			i = 0;
		for ( i = 0; i < ca.length; i++ ) {
			c = ca[i];
			while (c.charAt(0) == ' ') c = c.substring(1, c.length);
			if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length, c.length);
		}
		return null;
	}

	function cookieSave(name, value, days) {
		var expires = '',
			date = new Date();
		if ( days ) {
			date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
			expires = "; expires=" + date.toGMTString();
		}
		document.cookie = name + "=" + encodeURIComponent(value) + expires + "; path=/";
	}

});
