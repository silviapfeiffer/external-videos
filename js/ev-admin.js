/**
 * External Videos Settings Page Scripts
 * Handles admin AJAX for external videos settings page.
 *
 * since 1.0
 *
 * Variables passed to this script by wp_localize_script:
 * evSettings.ajax_url
 * evSettings.nonce
 *
 * for testing:
 // set = JSON.stringify(data, null, 4);
 // alert(set);
 *
*/

(function( $ ) {

  // SETTINGS TABS
  var tabChange = function(){
    $('.nav-tab-wrapper a').click( function() {
      $('.nav-tab').removeClass('nav-tab-active');
      $('section').fadeOut();
      var chosen = $('section').eq($(this).index()),
          choose = function(){ chosen.fadeIn(); };
      window.setTimeout( choose, 300 );
      $(this).addClass('nav-tab-active');
      return false;
    });
  };

  // fadeOut
  var fadeCallback = function(feedback){
    $(feedback).children().fadeOut( 1000 );
  };

  // fadeOut
  var fadeNotice = function(feedback){
    window.setTimeout( function(){
      fadeCallback(feedback);
    }, 5000 );
  };

  // Plugin Settings handler
  var settingsUpdate = function(){
    $('#ev_plugin_settings').submit(function(e){

      e.preventDefault();
      var pluginSettings = $('#ev_plugin_settings').serialize();

      $.post( evSettings.ajax_url, {
        _ajax_nonce: evSettings.nonce,
        action: "plugin_settings_handler",
        data: pluginSettings,
        dataType:'json'
      }, function(data){
        $("#ev_plugin_settings .feedback").html(data);
      });
    });
  };

  // Check Videos handler
  var checkVideos = function(){
    $(document).on("click", "#ev_author_list .button-update", function(e){

      e.preventDefault();

      var hostId = $(this).attr("data-host"),
          authorId = $(this).attr("data-author"),
          spinner = $(this).siblings(".spinner"),
          feedback = $("#ev_author_list .feedback");

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
        fadeNotice(feedback);
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
          feedback = $("#ev_author_list .feedback");

      if (!confirm('Are you sure you want to delete channel ' + authorId + ' on ' + hostId + '?')){
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
        fadeNotice(feedback);
      });
    });
  };

  // Update Videos handler
  var updateVideos = function(){
    $('#ev_update_videos').submit(function(e){

      e.preventDefault();

      var feedback = $("#ev_update_videos .feedback"),
          spinner = $("#ev_update_videos .spinner");

      $(spinner).addClass("is-active");

      $.post( evSettings.ajax_url, {
        _ajax_nonce: evSettings.nonce,
        action: "update_videos_handler"
        // data: No data sent,
      }, function(data){
        $(spinner).removeClass("is-active");
        $("#ev_update_videos .feedback").html(data);
        $(feedback).html(data);
        fadeNotice(feedback);
      });
    });
  };

  // Delete All handler
  var deleteAll = function(){
    $('#ev_delete_all').submit(function(e){

      e.preventDefault();

      var feedback = $("#ev_delete_all .feedback"),
          spinner = $("#ev_delete_all .spinner");

      if (!confirm('Are you sure you want to delete all external video posts?')){
        return false;
      }
      $(spinner).addClass("is-active");

      $.post( evSettings.ajax_url, {
        _ajax_nonce: evSettings.nonce,
        data: '',
        dataType: 'html',
        action: "delete_all_videos_handler"
      }, function(data){
        $(spinner).removeClass("is-active");
        $(feedback).html(data);
        fadeNotice(feedback);
      });
    });
  };

  // Add author handler
  var addAuthor = function(){
    $('.ev_add_author').submit(function(e){

      e.preventDefault();

      var particular = $(this).attr("id"),
          feedback = $("#" + particular + " .feedback")
          author = $("#" + particular).serialize();

      $.post( evSettings.ajax_url, {
        _ajax_nonce: evSettings.nonce,
        data: author,
        dataType:'json',
        action: "add_author_handler"
      }, function(data){
        $("#" + particular + " .feedback").html(data);
        var message = data;
        authorList(message); //rebuild the author list from the database
        fadeNotice(feedback);
      });
    });
  };


  // Author List refresher
  // Note that ajax loading of html f's up event binding on all loaded elements
  // events herein must be bound to document henceforth
  var authorList = function(message){

    var feedback = $("#ev_author_list .feedback");

    $.get( evSettings.ajax_url, {
      _ajax_nonce: evSettings.nonce,
      action: "author_list_handler",
      dataType:'html'
    }, function(data){
      $("#ev_author_list").html(data);
      $("#ev_author_list .feedback").html(message);
      fadeNotice(feedback);
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
