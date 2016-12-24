/**
 * External Videos Settings Page Scripts
 * Handles admin AJAX for external videos settings page.
 *
 * since 1.0
 *
 * Variables passed to this script by wp_localize_script:
 * evSettings.ajax_url
 * evSettings.nonce
*/

(function( $ ) {

	// SETTINGS TABS
	var tabChange = function(){
		$('.nav-tab-wrapper a').click( function() {
			$('.nav-tab').removeClass('nav-tab-active');
			$('section').fadeOut();
			var chosen = $('section').eq($(this).index());
			var choose = function(){
				chosen.fadeIn();
			};
			window.setTimeout( choose, 300 );
			$(this).addClass('nav-tab-active');
			return false;
		});
	};

	// Plugin Settings handler
	var settingsUpdate = function(){
		$('#ev_plugin_settings').submit(function(e){
			e.preventDefault();
			// alert('You got it');
			var pluginSettings = $('#ev_plugin_settings').serialize();
			// set = JSON.stringify(pluginSettings, null, 4);
			// alert('Plugin Settings: ' + set);
			// alert('evSettings.ajax_url: ' + evSettings.ajax_url );
			// alert('_ajax_nonce: ' + evSettings.nonce );
			// pluginSettings = stringify(pluginSettings);
			$.post( evSettings.ajax_url, {
				_ajax_nonce: evSettings.nonce,
				action: "plugin_settings_handler",
				data: pluginSettings,
				dataType:'json'
			}, function(data){
				$("#ev_plugin_settings .feedback").html(data);
				// set = JSON.stringify(data, null, 4);
				// alert(set);
			});
		});
	};

	// Update Videos handler
	var updateVideos = function(){
		$('#ev_update_videos').submit(function(e){
			e.preventDefault();
			$.post( evSettings.ajax_url, {
				_ajax_nonce: evSettings.nonce,
				action: "update_videos_handler"
				// data: No data sent,
			}, function(data){
				$("#ev_update_videos .feedback").html(data);
			});
		});
	};

  // Check Videos handler
	var checkVideos = function(){
    $(document).on("click", "#ev_author_list .button-update", function(e){
			e.preventDefault();
      var hostId = $(this).attr("data-host"),
			    authorId = $(this).attr("data-author"),
			    // particular = $(this).closest("td"),
          // buttonparent = $(this).parent(),
          spinner = $(this).siblings(".spinner"),
          feedback = $("#ev_author_list .feedback"),
          fadeNotice = function(){
            $(feedback).children().fadeOut(1000);
          };
      $(spinner).addClass("is-active");

			$.post( evSettings.ajax_url, {
				_ajax_nonce: evSettings.nonce,
				action: "update_videos_handler",
        host_id: hostId,
				author_id: authorId,
				dataType:'html'
			}, function(data){
        $(feedback).html(data);
        $(spinner).removeClass("is-active");
        window.setTimeout( fadeNotice, 5000 );
        // set = JSON.stringify(data, null, 4);
				// alert(set);
			});
		});
	};

  // Delete author handler
  // AJAX loaded elements must click bind to document
  // http://stackoverflow.com/questions/16598213/how-to-bind-events-on-ajax-loaded-content
	var deleteAuthor = function(){
		$(document).on("click", "#ev_author_list .button-delete", function(e){
			e.preventDefault();
			var hostId = $(this).attr("data-host"),
  		    authorId = $(this).attr("data-author"),
			    particular = $(this).closest("td"),
          feedback = $("#ev_author_list .feedback"),
          fadeNotice = function(){
            $(feedback).children().fadeOut(1000);
          };
      // alert('hostId: ' + hostId);
      // alert('authorId: ' + authorId);
			// alert('particular: ' + particular);
      if (!confirm('Are you sure you want to delete channel' + authorId + ' on ' + hostId + '?')){
				return false;
			}

			$.post( evSettings.ajax_url, {
				_ajax_nonce: evSettings.nonce,
				host_id: hostId,
				author_id: authorId,
				dataType:'html',
				action: "delete_author_handler"
			}, function(data){
        var message = data;
				$(particular).fadeOut(); //first fade out this author
				authorList(message); //rebuild the author list from the database
        $(feedback).html(data);
        window.setTimeout( fadeNotice, 5000 );
				// set = JSON.stringify(data, null, 4);
				// alert(set);
			});
		});
	};

	// Delete All handler
	var deleteAll = function(){
		$('#ev_delete_all').submit(function(e){
			e.preventDefault();
      var feedback = $("#ev_delete_all .feedback"),
      fadeNotice = function(){
        $(feedback).children().fadeOut(1000);
      };
			if (!confirm('Are you sure you want to delete all external video posts?')){
				return false;
			}
			$.post( evSettings.ajax_url, {
				_ajax_nonce: evSettings.nonce,
        data: '',
        dataType: 'html',
				action: "delete_all_videos_handler"
			}, function(data){
        $(feedback).html(data);
        window.setTimeout( fadeNotice, 5000 );
        // set = JSON.stringify(data, null, 4);
				// alert(set);
			});
		});
	};

	// Add author handler
	var addAuthor = function(){
		$('.ev_add_author').submit(function(e){
			e.preventDefault();
			var particular = $(this).attr("id");
			// alert('Particular: ' + particular);
			var author = $("#" + particular).serialize();
			// set = JSON.stringify(author, null, 4);
			// alert('Author Settings: ' + set);

			$.post( evSettings.ajax_url, {
				_ajax_nonce: evSettings.nonce,
				data: author,
				dataType:'json',
				action: "add_author_handler"
			}, function(data){
				$("#" + particular + " .feedback").html(data);
        var message = data;
				// set = JSON.stringify(data, null, 4);
				// alert(set);
				authorList(message); //rebuild the author list from the database
			});
		});
	};


  // Author List refresher
  // Note that ajax loading of html f's up event binding on all loaded elements
  // events herein must be bound to document henceforth
	var authorList = function(message){
		$.get( evSettings.ajax_url, {
			_ajax_nonce: evSettings.nonce,
			action: "author_list_handler",
			dataType:'html'
		}, function(data){
			$("#ev_author_list").html(data);
      $("#ev_author_list .feedback").html(message);
		});
	};

	$(document).ready( function() {
		tabChange();
    settingsUpdate();
		updateVideos();
    checkVideos();
		deleteAll();
    authorList();
		addAuthor();
		deleteAuthor();
	});

})( jQuery );
