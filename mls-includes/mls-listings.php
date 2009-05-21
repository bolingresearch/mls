<div class="wrap">
  <h2>RealtyPress Listings</h2>
  <div id="poststuff" style="margin-top:10px;">
    <div id="mainblock">
      <div class="dbx-content">
        <form action="<?php echo $action_url ?>" method="post">
        <input type="hidden" name="createpost" value="1" />
        <?php wp_nonce_field('listings-nonce'); ?>
        <h3>Search for Listings and add them to your blog:</h3>
        <table id="listings_table" class="display">
          <thead>
            <tr>
              <?php $this->print_listing_column_headers(); ?>
            </tr>
          </thead>
          <tbody>
            <?php $this->print_listing_rows(); ?>
          </tbody>
          <tfoot>
            <tr>
              <?php $this->print_listing_column_headers(); ?>
            </tr>
          </tfoot>
        </table>
      <div class="submit">
        <input type="submit" name="Submit" value="Create Post" />
      </div>
      </form>
    </div>
  </div>
</div>
</div>
