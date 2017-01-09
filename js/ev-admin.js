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
          choose = function(){ chosen.fadeIn(); },
          feedback = $("#ev_author_list .feedback");
      window.setTimeout( choose, 300 );
      $(this).addClass('nav-tab-active');
      fadeNotice(feedback);
      return false;
    });
  };

  // EDIT AUTHOR COLLAPSING FORMS
  var formExpand = function(){
    $(document).on("click", ".ev-host-table a.edit-author", function(e) {
      e.preventDefault();
      var table = $(this).parents("table"),
          save = $(this).siblings("button"),
          open = $(this).children(".open"),
          closed = $(this).children(".closed"),
          panel = $(table).children(".info-author");

      $(closed).toggle();
      $(open).toggle();
      $(panel).fadeToggle();
      $(save).fadeToggle();
      return false;
    });
  };

  // fadeCallback for fadeNotice
  var fadeCallback = function(feedback){
    $(feedback).children().fadeOut( 1000 );
  };

  // fadeNotice
  var fadeNotice = function(feedback){
    window.setTimeout( function(){
      fadeCallback(feedback);
    }, 8000 );
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
        dataType: 'json'
      }, function(data){
        var message = $(data).prop("message"),
            slug = $(data).prop("slug");
        $("#ev_plugin_settings .feedback").html(message);
        $("#ev_plugin_settings input#ev-slug").val(slug);
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

  // Check Videos handler
  var checkVideos = function(){
    $(document).on("click", "#ev_author_list .button-update", function(e){

      e.preventDefault();
      // alert( evSettings.nonce );

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
  // needs to refresh ev_edit_author table for appropriate host, also
  var deleteAuthor = function(){
    $(document).on("click", ".host-authors-list .delete-author", function(e){

      e.preventDefault();

      var hostId = $(this).attr("data-host"),
          authorId = $(this).attr("data-author"),
          form = $(this).parents("form"),
          list = $(form).parents(".host-authors-list"),
          feedback = $(list).siblings(".feedback");
          host = $(form).parents(".ev_edit_authors_host").data("host");

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
        $(form).fadeOut(); //first fade out this author
        refreshSettingsAuthorList(feedback,message); //rebuild the author list from the database
        refreshHostAuthorList(feedback,list,host,message); //rebuild the host authors list from the database
        $(feedback).html(message);
        fadeNotice(feedback);
      });
    });
  };

  // addAuthor needs to refresh ev_edit_author table for appropriate host
  // also needs a varation editAuthor to update db via add_author_handler cb
  // that is bound on document click bc the form is AJAX printed
  var addAuthor = function(){
    $(document).on("submit", ".ev_add_author", function(e){
      e.preventDefault();
      var form = $(this);
      authorAjax(form);
    });
  };

  var editAuthor = function(){
    $(document).on("submit", ".ev_edit_author", function(e){
      e.preventDefault();
      // $(this).css({backgroundColor: "blue"});
      var form = $(this);
      // set = JSON.stringify(form, null, 4);
      // alert(set);
      authorAjax(form);
    });
  };

  var authorAjax = function(form){
    var author = $(form).serialize(),
        list = $(form).parents(".host-authors-list"),
        feedback = $(list).siblings(".feedback");
        host = $(form).parents(".ev_edit_authors_host").data("host");

        // set = JSON.stringify(author, null, 4);
        // alert(set);

    $.post( evSettings.ajax_url, {
      _ajax_nonce: evSettings.nonce,
      data: author,
      dataType:'json',
      action: "add_author_handler"
    }, function(data){
      // set = JSON.stringify(data, null, 4);
      // alert(set);
      var message = data;
      // $(feedback).html(message);
      refreshSettingsAuthorList(feedback,message); //rebuild the author list from the database
      refreshHostAuthorList(feedback,list,host,message); //rebuild the host authors list from the database
      // fadeNotice(feedback);
    });
  }

  // Settings Author List refresher
  // Note that ajax loading of html f's up event binding on all loaded elements
  // events herein must be bound to document henceforth
  var refreshSettingsAuthorList = function(feedback,message){
    $.get( evSettings.ajax_url, {
      _ajax_nonce: evSettings.nonce,
      action: "author_list_handler",
      dataType:'html'
    }, function(data){
      $("#ev_author_list").html(data);
      $(feedback).html(message);
      fadeNotice(feedback);
    });
  };

  // Host Author List refresher
  // Note that ajax loading of html f's up event binding on all loaded elements
  // events herein must be bound to document henceforth
  var refreshHostAuthorList = function(feedback,list,host,message){
    $.post( evSettings.ajax_url, {
      _ajax_nonce: evSettings.nonce,
      action: "author_host_list_handler",
      dataType: 'html',
      data: { host_id: host }
    }, function(data){
      // set = JSON.stringify(data, null, 4);
      // alert(set);
      $(list).html(data);
      $(feedback).html(message);
      fadeNotice(feedback);
    });
  };

  var generateAllHostAuthorLists = function(){
    var hostAuthorLists = $(".ev_edit_authors_host");
    // listlist = JSON.stringify(hostAuthorLists, null, 4);
    // alert(listlist);

    $(hostAuthorLists).each(function(){
      var feedback = $(this).children(".feedback"),
          list = $(this).children(".host-authors-list"),
          host = $(this).data("host"),
          message = '';
      // $(feedback).css({backgroundColor: "red"});
      refreshHostAuthorList(feedback,list,host,message);
    });
  };

  $(document).ready( function() {
    tabChange();
    formExpand();
    settingsUpdate();
    updateVideos();
    checkVideos();
    deleteAll();
    refreshSettingsAuthorList();
    generateAllHostAuthorLists();
    addAuthor();
    editAuthor();
    deleteAuthor();
  });

})( jQuery );
