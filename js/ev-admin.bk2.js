/**
 * External Videos Settings Page Scripts
 * Handles admin AJAX for external videos settings page.
 *
 * since 1.0
 *
 * Variables passed to this script by wp_localize_script:
 * videohosts
 * evSettings.ajax_url
 * evSettings.nonce
 * evSettings.confirmtext
 * evSettings.videohosts
*/

(function( $ ) {

	/*
	* SETTINGS TABS
	*/

	$(document).on( 'click', '.nav-tab-wrapper a', function() {
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

	// $('#ev_plugin_settings').submit(function(e){
	// 	e.preventDefault();
	// 	// alert('You got it');
	// 	var pluginSettings = $('#ev_plugin_settings').serialize();
	// 	// set = JSON.stringify(pluginSettings, null, 4);
	// 	// alert('Plugin Settings: ' + set);
	// 	// alert('evSettings.ajax_url: ' + evSettings.ajax_url );
	// 	// alert('_ajax_nonce: ' + evSettings.nonce );
	// 	// pluginSettings = stringify(pluginSettings);
	// 	$.post( evSettings.ajax_url, {
	// 		// method: 'POST',
	// 		// url: evSettings.ajax_url,
	// 		_ajax_nonce: evSettings.nonce,
	// 		action: "plugin_settings_handler",
	// 		data: pluginSettings,
	// 		dataType:'json',
	// 		// title: this.value,
	// 	}, function(data){ // the fickin callback is key
	// 		$("#ev_plugin_settings .feedback").html(data);
	// 		// set = JSON.stringify(data, null, 4);
	// 		// alert(set);
	// 	});
	// });

	// $('#ev_update_videos input[type="submit"]').click(function(e){
	// 	e.preventDefault();
	// 	// alert('You got it');
	// 	// var pluginSettings = $('#ev_update_videos').serialize();
	// 	$.post( evSettings.ajax_url, {
	// 		_ajax_nonce: evSettings.nonce,
	// 		action: "update_videos_handler",
	// 		// data: pluginSettings,
	// 		// dataType:'json',
	// 	}, function(data){
	// 		$("#ev_update_videos .feedback").html(data);
	// 	});
	// });
	//
	// $('#ev_delete_all input[type="submit"]').click(function(e){
	// 	e.preventDefault();
	// 	if (!confirm('Are you sure you want to delete all external video posts?')){
	// 		return false;
	// 	}
	// 	$.post( evSettings.ajax_url, {
	// 		_ajax_nonce: evSettings.nonce,
	// 		action: "delete_videos_handler",
	// 	}, function(data){
	// 		$("#ev_delete_all .feedback").html(data);
	// 	});
	// });

	// Author List generator handler
	var authorList = function(){
		$.post( evSettings.ajax_url, {
			_ajax_nonce: evSettings.nonce,
			action: "author_list_handler",
			dataType:'html'
		}, function(data){
			$("#ev_author_list").html(data);
			// set = JSON.stringify(data, null, 4);
			// alert(set);
		});
	};

	$(window).load( function() {
		authorList();
	});

	$('#ev_author_list input[type="submit"]').click(function(e){
		e.preventDefault();
		var hostId = $(this).attr("data-host"),
				authorId = $(this).attr("data-author"),
				particular = $(this).closest("p");
		alert('Particular: ' + particular);

		$.post( evSettings.ajax_url, {
			_ajax_nonce: evSettings.nonce,
			host_id: hostId,
			author_id: authorId,
			dataType:'json',
			action: "delete_author_handler",
		}, function(data){
			// $(particular).fadeOut();
			// $(".feedback").html(data);
			set = JSON.stringify(data, null, 4);
			alert(set);
		});
	});

	// $('.ev_add_author').submit(function(e){
	// 	e.preventDefault();
	// 	var particular = $(this).attr("id");
	// 	// alert('Particular: ' + particular);
	// 	var author = $("#" + particular).serialize();
	// 	// set = JSON.stringify(author, null, 4);
	// 	// alert('Author Settings: ' + set);
	//
	// 	$.post( evSettings.ajax_url, {
	// 		_ajax_nonce: evSettings.nonce,
	// 		data: author,
	// 		dataType:'json',
	// 		action: "add_author_handler",
	// 	}, function(data){
	// 		$("#" + particular + " .feedback").html(data);
	// 		set = JSON.stringify(data, null, 4);
	// 		alert(set);
	// 	});
	// });

	// fill in hidden form values for author delete buttons
	// originally this named function fired on author delete button click
	// function delete_author(host_id, author_id) {
  //   jQuery('#delete_author [name="host_id"]').val(host_id);
  //   jQuery('#delete_author [name="author_id"]').val(author_id);
	//
	//   if (!confirm(confirmtext)) {
	//       return false;
	//   }
  //   jQuery('#delete_author').submit();
  // }

	// Swap out relevant info for adding an author.
	// Different hosts have different required info.
	// jQuery(document).ready( function(){
	//
	// 	var videohosts = evSettings.videohosts
	// 			otherHosts = videohosts,
	// 			vh1 = Object.keys(videohosts),
	// 			firstHost = null;
	// 	for(var i in otherHosts){
	// 			firstHost = otherHosts[i];
	// 			break;
	// 	}
	// 	delete otherHosts[i];
	// 	// console.log(i);
	// 	// printout = JSON.stringify(otherHosts);
	// 	// console.log(printout);
	//
	// 	var show = i,
	// 			hides = Object.keys(otherHosts);
	//
	//
	// 	var showem = function( show, hides ){
	// 				jQuery.each( hides, function( index, value ){
	// 					hide = jQuery('#ev_authors .ev-'+value);
	// 					jQuery.each( hide, function(){
	// 						// console.log(this);
	// 						jQuery(this).fadeOut(100);
	// 					});
	// 				});
	// 				jQuery('#ev_authors .ev-'+show).fadeIn(300);
	// 			},
	// 			swap = function( channelName ){
	// 				jQuery('#ev_authors .ev-swap').html( channelName );
	// 			};
	//
	// 	showem( show, hides );
	//
	// 	jQuery('#ev_host_id').change(function(event) {
	// 		var channel = jQuery('#ev_host_id').val(),
	// 				channelName = videohosts[channel],
	// 				show = channel,
	// 				hosts = vh1,
	// 				hides = jQuery.grep(hosts, function(value) {
	// 									return value != show;
	// 								});
	// 								// console.log('channel: ' + channel);
	// 								// printout = JSON.stringify(hides);
	// 								// console.log(hides);
	// 		showem( show, hides );
	// 		swap( channelName );
	// 	});
	// });

})( jQuery );
