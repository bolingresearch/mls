<div class="wrap" style="max-width:950px !important">
    <h2>MLS Settings</h2>
    <div id="poststuff" style="margin-top:10px;">
        <div id="mainblock" style="width:710px;">
            <div class="dbx-content">
                <form action="<?php echo $action_url; ?>" method="post">
                    <input type="hidden" name="submitted" value="1" />
                    <?php wp_nonce_field('mls-options'); ?>
                    <h3>Credentials</h3>
                    <p><em>Please enter the office ID issued to you by your Board of Realtors:</em></p>
                    <label for="office_id">Office ID:</label><input type="text" name="office_id" value="<?php echo $office_id; ?>" /><br />
                    <p><em>Please enter the MLS User ID issued to you by your Board of Realtors:</em></p>
                    <label for="agent_id">MLS User ID:</label><input type="text" name="agent_id" value="<?php echo $agent_id; ?>" /><br />
                    <p><em>Please enter your license number issued by the DRE (Dept. of Real Estate):</em></p>
                    <label for="agent_license">DRE License Number:</label><input type="text" name="agent_license" value="<?php echo $agent_license; ?>" /><br />
                    <div class="submit"><input type="submit" name="Submit" value="Update" /></div>
                </form>
            </div>
        </div>
    </div>
</div>