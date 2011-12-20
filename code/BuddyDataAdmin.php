<?php
/**
 * Edit database records for some of the Buddy features in CMS
 *
 */
class BuddyDataAdmin extends ModelAdmin {
    public static $managed_models = array(
        'BuddyInterests',
        'Buddy',
        'BuddySearch'
    );

    static $url_segment = 'buddy'; // will be linked as /admin/buddy
    static $menu_title = 'Buddy data';

    public $showImportForm = array('BuddyInterests');
}
