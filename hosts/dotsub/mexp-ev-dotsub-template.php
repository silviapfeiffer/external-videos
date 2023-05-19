<?php

class MEXP_EV_Dotsub_Template extends MEXP_Template {

  /**
   * Template for single elements returned from the API
   *
   * @param string $id  the id of the view
   * @param string $tab the tab were the user is right now
   */
  public function item( $id, $tab ) {
    ?>
    <div id="mexp-item-dotsub-<?php echo esc_attr( $tab ); ?>-{{ data.id }}" class="mexp-item-area mexp-item-dotsub" data-id="{{ data.id }}">
      <div class="mexp-item-container clearfix">
        <div class="mexp-item-thumb">
          <img src="{{ data.thumbnail }}">
        </div>
        <div class="mexp-item-main">
          <div class="mexp-item-content">
            {{ data.content }}
          </div>
          <div class="mexp-item-channel">
            <?php _e( 'by', 'mexp' ) ?> {{ data.meta.user }}
          </div>
          <div class="mexp-item-date">
            {{ data.date }}
          </div>
        </div>
      </div>
    </div>
    <a href="#" id="mexp-check-{{ data.id }}" data-id="{{ data.id }}" class="check" title="<?php esc_attr_e( 'Deselect', 'mexp' ); ?>">
      <div class="media-modal-icon"></div>
    </a>
    <?php
  }

  public function thumbnail( $id ) {
    ?>
    <?php
  }

  /**
   * Template for the search form
   * This is tabbed by connected ev-authors
   * Note there is no page_token on Dotsub
   *
   * @param string $id  the id of the view
   * @param string $tab the tab were the user is right now
   */
  public function search( $id, $tab ) {
    ?>
    <form action="#" class="mexp-toolbar-container clearfix tab-all">
      <input
        type="text"
        name="q"
        value="{{ data.params.q }}"
        class="mexp-input-text mexp-input-search"
        size="40"
        placeholder="<?php esc_attr_e( sprintf( 'Search %s videos', $tab ), 'mexp' ); ?>"
      >
      <input type="hidden" name="author_id" value="<?php esc_attr_e( $tab ); ?>" />
      <input type="hidden" name="page_token" value="" id="page_token" class="<?php esc_attr_e( $tab ); ?>"/>
      <input class="button button-large" type="submit" value="<?php esc_attr_e( 'Load Videos', 'mexp') ?>">
      <div class="spinner"></div>
    </form>
    <?php
  }

}
